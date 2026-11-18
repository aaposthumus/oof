<?php

namespace App\Achievements\Nicknames;

use App\Achievements\SeasonData;
use App\Enums\AwardScope;
use App\Enums\AwardStatus;
use App\Enums\Breakthrough;
use App\Enums\EvolutionStatus;
use App\Enums\NicknameStatus;
use App\Enums\RecipientType;
use App\Enums\SessionType;
use App\Events\NicknameEvolutionProposed;
use App\Models\AchievementAward;
use App\Models\DriverNickname;
use App\Models\DriverStanding;
use App\Models\NicknameEvolution;
use App\Models\RaceSession;
use App\Models\Result;
use App\Models\Round;
use App\Models\Season;
use App\RaceControl\MessageClassifier;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Proposes nickname evolutions after a breakthrough in a season, for every
 * driver with a planned next stage whose current stage is active:
 *
 * - First podium or first win: read from the Grand Prix results (sprints do
 *   not count), once the award behind it (Did the Thing or what replaced
 *   it, Silver Lining, Solid Bronze) is final. A first podium that is a win
 *   is a first win.
 * - Strong first season: once the season is final, a place in the drivers'
 *   championship at or above nicknames.strong_first_season_top in the
 *   driver's first full season (a race entry in every round).
 *
 * One proposal per target nickname, whatever the season or run, so re-runs
 * and backfills never propose or emit twice. NicknameEvolutionProposed is
 * dispatched only for seasons from achievements.events_from.
 */
class Breakthroughs
{
    /**
     * The achievements a podium place is stored as, by position.
     */
    public const PODIUM_AWARDS = [
        1 => ['did_the_thing', 'giant_slayer', 'took_the_scenic_route', 'clean_sweep'],
        2 => ['silver_lining'],
        3 => ['solid_bronze'],
    ];

    public function __construct(private MessageClassifier $classifier) {}

    /**
     * @return Collection<int, NicknameEvolution> The proposals made
     */
    public function check(Season $season, ?CarbonImmutable $now = null): Collection
    {
        $targets = DriverNickname::query()
            ->planned()
            ->whereDoesntHave('evolution')
            ->whereHas('evolvesFrom', fn ($query) => $query->where('status', NicknameStatus::Active))
            ->with('evolvesFrom')
            ->get();

        if ($targets->isEmpty()) {
            return collect();
        }

        $seasonIsFinal = (new SeasonData($season, $this->classifier))->isFinal($now ?? CarbonImmutable::now());

        return $targets
            ->map(function (DriverNickname $target) use ($season, $seasonIsFinal) {
                $found = $this->podium($target, $season) ?? ($seasonIsFinal ? $this->strongFirstSeason($target, $season) : null);

                return $found === null ? null : $this->propose($target, $season, ...$found);
            })
            ->filter()
            ->values();
    }

    /**
     * The driver's first podium or first win in this season, with the final award behind it.
     *
     * @return array{0: Breakthrough, 1: AchievementAward|null, 2: array<string, mixed>}|null
     */
    private function podium(DriverNickname $target, Season $season): ?array
    {
        $podiums = $this->podiumResults($target->driver_id);

        foreach ($podiums->where('season_id', $season->id) as $podium) {
            $earlier = $podiums->filter(fn (object $other) => $other->date < $podium->date);
            $breakthrough = match (true) {
                $podium->position === 1 && $earlier->where('position', 1)->isEmpty() && $target->evolvesOn(Breakthrough::FirstWin) => Breakthrough::FirstWin,
                $earlier->isEmpty() && $target->evolvesOn(Breakthrough::FirstPodium) => Breakthrough::FirstPodium,
                default => null,
            };

            if ($breakthrough === null) {
                continue;
            }

            $award = AchievementAward::query()
                ->where('race_session_id', $podium->race_session_id)
                ->where('scope', AwardScope::Session)
                ->where('recipient_type', RecipientType::Driver)
                ->where('driver_id', $target->driver_id)
                ->where('status', AwardStatus::Final)
                ->whereRelation('achievement', fn ($query) => $query->whereIn('key', self::PODIUM_AWARDS[$podium->position]))
                ->first();

            // Not final yet (or not awarded at all): the next run looks again.
            if ($award === null) {
                return null;
            }

            return [$breakthrough, $award, ['rows' => [['round' => $podium->round_number, 'race' => $podium->name, 'position' => $podium->position]]]];
        }

        return null;
    }

    /**
     * The driver's Grand Prix podiums of every season, oldest first.
     *
     * @return Collection<int, object{season_id: int, round_number: int, name: string, date: string, race_session_id: int, position: int}>
     */
    private function podiumResults(int $driverId): Collection
    {
        return Result::query()
            ->join('driver_entries', 'driver_entries.id', '=', 'results.driver_entry_id')
            ->join('race_sessions', 'race_sessions.id', '=', 'driver_entries.race_session_id')
            ->join('rounds', 'rounds.id', '=', 'race_sessions.round_id')
            ->where('driver_entries.driver_id', $driverId)
            ->where('race_sessions.type', SessionType::Race)
            ->whereIn('results.position_text', ['1', '2', '3'])
            ->orderBy('rounds.date')
            ->toBase()
            ->get(['rounds.season_id', 'rounds.round_number', 'rounds.name', 'rounds.date', 'race_sessions.id as race_session_id', 'results.position_text'])
            ->map(function (object $row) {
                $row->position = (int) $row->position_text;

                return $row;
            });
    }

    /**
     * A top place in the final standings of the driver's first full season.
     *
     * @return array{0: Breakthrough, 1: null, 2: array<string, mixed>}|null
     */
    private function strongFirstSeason(DriverNickname $target, Season $season): ?array
    {
        if (! $target->evolvesOn(Breakthrough::StrongFirstSeason) || $this->firstFullSeason($target->driver_id)?->id !== $season->id) {
            return null;
        }

        $finalRound = Round::query()->where('season_id', $season->id)->orderByDesc('round_number')->first();
        $standing = $finalRound === null ? null : DriverStanding::query()->where('round_id', $finalRound->id)->where('driver_id', $target->driver_id)->first();

        if ($standing === null || $standing->position === null || $standing->position > (int) config('nicknames.strong_first_season_top')) {
            return null;
        }

        return [Breakthrough::StrongFirstSeason, null, ['rows' => [['season' => $season->year, 'position' => $standing->position, 'points' => (float) $standing->points]]]];
    }

    /**
     * The first season in which the driver had a race entry in every round.
     */
    private function firstFullSeason(int $driverId): ?Season
    {
        $seasonIds = RaceSession::query()
            ->join('rounds', 'rounds.id', '=', 'race_sessions.round_id')
            ->join('driver_entries', 'driver_entries.race_session_id', '=', 'race_sessions.id')
            ->where('race_sessions.type', SessionType::Race)
            ->where('driver_entries.driver_id', $driverId)
            ->distinct()
            ->pluck('rounds.season_id');

        return Season::query()
            ->whereIn('id', $seasonIds)
            ->orderBy('year')
            ->get()
            ->first(function (Season $season) use ($driverId) {
                $races = RaceSession::query()->where('type', SessionType::Race)->whereHas('round', fn ($query) => $query->where('season_id', $season->id));

                return (clone $races)->count() === (clone $races)->whereHas('driverEntries', fn ($query) => $query->where('driver_id', $driverId))->count();
            });
    }

    /**
     * @param  array<string, mixed>  $evidence
     */
    private function propose(DriverNickname $target, Season $season, Breakthrough $breakthrough, ?AchievementAward $award, array $evidence): ?NicknameEvolution
    {
        return DB::transaction(function () use ($target, $season, $breakthrough, $award, $evidence) {
            // Locked read: a concurrent run must not propose the same target twice.
            if (NicknameEvolution::query()->where('to_nickname_id', $target->id)->lockForUpdate()->exists()) {
                return null;
            }

            $evolution = NicknameEvolution::create([
                'driver_id' => $target->driver_id,
                'from_nickname_id' => $target->evolves_from_id,
                'to_nickname_id' => $target->id,
                'season_id' => $season->id,
                'trigger_award_id' => $award?->id,
                'reason' => $breakthrough,
                'evidence' => $evidence,
                'status' => EvolutionStatus::Proposed,
            ]);

            if ($season->year >= (int) config('achievements.events_from')) {
                NicknameEvolutionProposed::dispatch($evolution);
            }

            return $evolution;
        });
    }
}
