<?php

namespace App\Achievements\Rules;

use App\Achievements\Detection;
use App\Achievements\SessionData;
use App\Enums\SensitivityLevel;
use App\Enums\SensitivitySeverity;
use App\Enums\SensitivitySource;
use App\Enums\SessionType;
use App\Models\Achievement;
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
 * A block stores nothing at all for its scope: the round, or the driver.
 *
 * Runs after Cascade, so a withheld offence takes its absorbed penalty with
 * it, and again after the late detectors. The state of a round is read once
 * per loaded session, after the engine has made its proposals.
 */
class Sensitivity
{
    public const SYSTEM_NOTE = 'Withheld: sensitive weekend';

    /**
     * @var WeakMap<SessionData, array{sensitive: bool, blocked_round: bool, blocked_drivers: list<int>, note: string|null}>|null
     */
    private ?WeakMap $cache = null;

    public function __construct(private HeavyCrash $heavyCrash) {}

    /**
     * @return array{sensitive: bool, blocked_round: bool, blocked_drivers: list<int>, note: string|null}
     */
    public function of(SessionData $data): array
    {
        $this->cache ??= new WeakMap;

        return $this->cache[$data] ??= self::forRound($data->session->round_id);
    }

    /**
     * @return array{sensitive: bool, blocked_round: bool, blocked_drivers: list<int>, note: string|null}
     */
    public static function forRound(int $roundId): array
    {
        $sensitivities = MomentSensitivity::query()->active()->where('round_id', $roundId)->orderByRaw('driver_id is not null')->orderBy('id')->get();
        $blocks = $sensitivities->where('level', SensitivityLevel::Blocked);

        return [
            'sensitive' => $sensitivities->isNotEmpty(),
            'blocked_round' => $blocks->contains(fn (MomentSensitivity $block) => $block->driver_id === null),
            'blocked_drivers' => $blocks->pluck('driver_id')->filter()->values()->all(),
            'note' => $sensitivities->isEmpty() ? null : $sensitivities
                ->map(fn (MomentSensitivity $sensitivity) => $sensitivity->isProposal() ? "Proposed, not yet confirmed: {$sensitivity->note}" : $sensitivity->note)
                ->unique()
                ->implode(' '),
        ];
    }

    /**
     * Whether an award of this achievement may exist for this driver in this round at all.
     *
     * @param  array{sensitive: bool, blocked_round: bool, blocked_drivers: list<int>, note: string|null}  $round
     */
    public static function allows(array $round, Achievement $achievement, ?int $driverId): bool
    {
        return ! $round['blocked_round']
            && ! ($driverId !== null && in_array($driverId, $round['blocked_drivers'], true))
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

        return $detections
            ->filter(fn (Detection $detection) => self::allows($round, $catalogue[$detection->achievementKey], $data->entry($detection->driverEntryId)?->driver_id))
            ->values();
    }

    /**
     * On a live weekend, propose a sensitivity for each car that retired from
     * a race or sprint with strong heavy-crash signals, once per driver and
     * round: a proposal a person dismissed is not proposed again.
     *
     * @return Collection<int, MomentSensitivity> The proposals created
     */
    public function propose(SessionData $data): Collection
    {
        $round = $data->session->round;

        if (! in_array($data->type(), [SessionType::Race, SessionType::Sprint], true)
            || $round->date->lt(CarbonImmutable::parse(config('achievements.sensitivity.propose_from')))) {
            return collect();
        }

        $created = $this->heavyCrash->strongCrashes($data)
            ->map(function (array $crash) use ($data, $round) {
                $sensitivity = MomentSensitivity::firstOrCreate(
                    ['round_id' => $round->id, 'driver_id' => $crash['entry']->driver_id, 'source' => SensitivitySource::Engine],
                    [
                        'level' => SensitivityLevel::Sensitive,
                        'severity' => SensitivitySeverity::Injury,
                        'note' => "{$crash['entry']->driver->given_name} {$crash['entry']->driver->family_name} retired from the {$data->type()->label()} in a heavy crash ({$crash['signal']}).",
                    ],
                );

                return $sensitivity->wasRecentlyCreated ? $sensitivity : null;
            })
            ->filter()
            ->values();

        if ($this->cache !== null) {
            unset($this->cache[$data]);
        }

        return $created;
    }
}
