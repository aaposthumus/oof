<?php

namespace App\Achievements\Rules;

use App\Achievements\Detection;
use App\Achievements\SessionData;
use App\Enums\AchievementSection;
use App\Enums\RecipientType;
use App\Enums\SensitivityLevel;
use App\Enums\SensitivitySeverity;
use App\Enums\SensitivitySource;
use App\Enums\SessionType;
use App\Models\Achievement;
use App\Models\AchievementAward;
use App\Models\DriverEntry;
use App\Models\MomentSensitivity;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use WeakMap;

/**
 * Sensitive and blocked weekends. Any sensitivity of a round that is not a
 * dismissed proposal makes the whole round sensitive, whether or not it
 * names a driver. On a sensitive round the achievements withheld on
 * sensitive weekends (crashes, retirements, incidents, the Safety Car,
 * Nothing to See Here) are never awarded; every other award is flagged.
 * A block stores nothing at all for its scope: the round, or the driver
 * (and the team awards of that driver's team in the round).
 *
 * Runs after Cascade, so a withheld offence takes its absorbed penalty with
 * it, and again after the late triggers. The state of a round is read once
 * per loaded session, after the engine has made its proposals.
 */
class Sensitivity
{
    public const SYSTEM_NOTE = 'Withheld: sensitive or blocked weekend';

    /**
     * The state of a context outside any round, such as a season.
     */
    public const NONE = ['sensitive' => false, 'blocked_round' => false, 'blocked_drivers' => [], 'blocked_season_teams' => [], 'note' => null];

    /**
     * @var WeakMap<SessionData, array{sensitive: bool, blocked_round: bool, blocked_drivers: list<int>, blocked_season_teams: list<int>, note: string|null}>|null
     */
    private ?WeakMap $cache = null;

    public function __construct(private HeavyCrash $heavyCrash) {}

    /**
     * @return array{sensitive: bool, blocked_round: bool, blocked_drivers: list<int>, blocked_season_teams: list<int>, note: string|null}
     */
    public function of(SessionData $data): array
    {
        $this->cache ??= new WeakMap;

        return $this->cache[$data] ??= self::forRound($data->session->round_id);
    }

    /**
     * @return array{sensitive: bool, blocked_round: bool, blocked_drivers: list<int>, blocked_season_teams: list<int>, note: string|null}
     */
    public static function forRound(int $roundId): array
    {
        $sensitivities = MomentSensitivity::query()->active()->where('round_id', $roundId)->orderByRaw('driver_id is not null')->orderBy('id')->get();
        $blocks = $sensitivities->where('level', SensitivityLevel::Blocked);
        $blockedDrivers = $blocks->pluck('driver_id')->filter()->values()->all();

        return [
            'sensitive' => $sensitivities->isNotEmpty(),
            'blocked_round' => $blocks->contains(fn (MomentSensitivity $block) => $block->driver_id === null),
            'blocked_drivers' => $blockedDrivers,
            // A driver block also holds back the team awards of that driver's team in the round (Phase 8 requirement).
            'blocked_season_teams' => $blockedDrivers === [] ? [] : DriverEntry::query()
                ->whereIn('driver_id', $blockedDrivers)
                ->whereHas('raceSession', fn ($query) => $query->where('round_id', $roundId))
                ->distinct()
                ->orderBy('season_team_id')
                ->pluck('season_team_id')
                ->all(),
            'note' => $sensitivities->isEmpty() ? null : $sensitivities
                ->map(fn (MomentSensitivity $sensitivity) => $sensitivity->isProposal() ? "Proposed, not yet confirmed: {$sensitivity->note}" : $sensitivity->note)
                ->unique()
                ->implode(' '),
        ];
    }

    /**
     * Whether an award of this achievement may exist for this driver, or
     * for this team (a team award's season team), in this round at all.
     *
     * @param  array{sensitive: bool, blocked_round: bool, blocked_drivers: list<int>, blocked_season_teams: list<int>, note: string|null}  $round
     */
    public static function allows(array $round, Achievement $achievement, ?int $driverId, ?int $seasonTeamId = null): bool
    {
        return ! $round['blocked_round']
            && ! ($driverId !== null && in_array($driverId, $round['blocked_drivers'], true))
            && ! ($seasonTeamId !== null && in_array($seasonTeamId, $round['blocked_season_teams'], true))
            && ! ($round['sensitive'] && $achievement->withheld_on_sensitive_weekends);
    }

    /**
     * @param  Collection<int, Detection>  $detections
     * @param  Collection<string, Achievement>  $catalogue  Keyed by key
     * @return Collection<int, Detection>
     */
    public function apply(Collection $detections, Collection $catalogue, SessionData $data): Collection
    {
        $round = $this->of($data);
        $allowed = fn (Detection $detection) => self::allows($round, $catalogue[$detection->achievementKey], $data->entry($detection->recipient->entryId)?->driver_id, $detection->recipient->seasonTeamId);

        // A heavier penalty stamp stays beside its offence (a lower tier never supersedes it), so it goes with a withheld offence of the same event too.
        $withheldEvents = $detections->reject($allowed)->map(fn (Detection $detection) => "{$detection->recipient->key()}|{$detection->eventKey}")->flip();

        return $detections
            ->filter($allowed)
            ->reject(fn (Detection $detection) => $catalogue[$detection->achievementKey]->section === AchievementSection::Penalty && $withheldEvents->has("{$detection->recipient->key()}|{$detection->eventKey}"))
            ->values();
    }

    /**
     * Whether a stored award may exist in this round: a driver award by its
     * driver, a team award by its season team.
     *
     * @param  array{sensitive: bool, blocked_round: bool, blocked_drivers: list<int>, blocked_season_teams: list<int>, note: string|null}  $round
     */
    public static function allowsAward(array $round, Achievement $achievement, AchievementAward $award): bool
    {
        return self::allows($round, $achievement, $award->driver_id, $award->recipient_type === RecipientType::Team ? $award->season_team_id : null);
    }

    /**
     * Whether the round's blocks leave out this stored award, whatever its achievement.
     *
     * @param  array{sensitive: bool, blocked_round: bool, blocked_drivers: list<int>, blocked_season_teams: list<int>, note: string|null}  $round
     */
    public static function blocks(array $round, AchievementAward $award): bool
    {
        return $round['blocked_round']
            || ($award->driver_id !== null && in_array($award->driver_id, $round['blocked_drivers'], true))
            || ($award->recipient_type === RecipientType::Team && in_array($award->season_team_id, $round['blocked_season_teams'], true));
    }

    /**
     * On a live weekend, propose one sensitivity for the round when cars
     * retired from a race or sprint with strong heavy-crash signals. It
     * covers the whole round and names every driver involved; a later crash
     * the same weekend is added while it is still a proposal. Once a person
     * confirmed or dismissed it, nothing changes and nothing new is proposed.
     */
    public function propose(SessionData $data): ?MomentSensitivity
    {
        $round = $data->session->round;

        if (! in_array($data->type(), [SessionType::Race, SessionType::Sprint], true)
            || $round->date->lt(CarbonImmutable::parse(config('achievements.sensitivity.propose_from')))) {
            return null;
        }

        $crashes = $this->heavyCrash->strongCrashes($data);

        if ($crashes->isEmpty()) {
            return null;
        }

        $proposal = MomentSensitivity::firstOrNew(
            ['round_id' => $round->id, 'driver_id' => null, 'source' => SensitivitySource::Engine],
            ['level' => SensitivityLevel::Sensitive, 'severity' => SensitivitySeverity::Injury, 'involved_driver_ids' => [], 'note' => ''],
        );

        if ($proposal->exists && ! $proposal->isProposal()) {
            return $proposal;
        }

        $lines = collect(explode("\n", $proposal->note))->filter();

        foreach ($crashes as $crash) {
            $driver = $crash['entry']->driver;
            $lines->push("{$driver->given_name} {$driver->family_name} retired from the {$data->type()->label()} in a heavy crash ({$crash['signal']}).");
        }

        $proposal->involved_driver_ids = collect($proposal->involved_driver_ids)->concat($crashes->map(fn (array $crash) => $crash['entry']->driver_id))->unique()->sort()->values()->all();
        $proposal->note = $lines->unique()->implode("\n");

        if ($proposal->isDirty()) {
            $proposal->save();
        }

        if ($this->cache !== null) {
            unset($this->cache[$data]);
        }

        return $proposal;
    }
}
