<?php

namespace App\Achievements;

use App\Enums\AwardStatus;
use App\Enums\RecipientType;
use App\Enums\SessionType;
use App\Events\OofScoreStandingsUpdated;
use App\Models\AchievementAward;
use App\Models\OofscoreStanding;
use App\Models\Round;
use App\Models\Season;
use App\RaceControl\MessageClassifier;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The OOFscore standings projection. Per season, the points of each
 * driver's final awards on the scoring shelves (their snapshot), summed
 * round by round, with a row per driver per round from their first award
 * on, for every round that has awards. Pending and suppressed awards never count. Equal points share a
 * place. A closed season is never rebuilt: its standings are frozen.
 */
class OofScoreStandings
{
    /**
     * Rebuild a season from scratch. Returns null for a closed season.
     *
     * @return array{rounds: int, rows: int, changed_rounds: int}|null
     */
    public function rebuild(Season $season): ?array
    {
        if ($season->isClosed()) {
            return null;
        }

        return DB::transaction(function () use ($season) {
            $before = $this->rowsOf($season);
            $rows = $this->compute($season);

            OofscoreStanding::query()->where('season_id', $season->id)->delete();

            foreach (array_chunk($rows, 500) as $chunk) {
                OofscoreStanding::query()->insert(array_map(fn (array $row) => [...$row, 'created_at' => now(), 'updated_at' => now()], $chunk));
            }

            $changed = collect($rows)->concat($before)
                ->groupBy('round_id')
                ->keys()
                ->filter(fn (int $roundId) => self::of($rows, $roundId) !== self::of($before, $roundId))
                ->values()
                ->all();

            if ($changed !== [] && $season->year >= (int) config('achievements.events_from')) {
                OofScoreStandingsUpdated::dispatch($season->id, $changed);
            }

            return ['rounds' => collect($rows)->pluck('round_id')->unique()->count(), 'rows' => count($rows), 'changed_rounds' => count($changed)];
        });
    }

    /**
     * Close a season: rebuild its standings a last time, then freeze them
     * and its awards' points. Only once the last round is final and nothing
     * of the season waits for review.
     *
     * @throws SeasonNotClosable
     */
    public function close(Season $season, ?CarbonImmutable $now = null): void
    {
        foreach ($this->reasonsNotToClose($season, $now ?? CarbonImmutable::now()) as $reason) {
            throw new SeasonNotClosable("{$season->year} cannot be closed: {$reason}.");
        }

        DB::transaction(function () use ($season) {
            $this->rebuild($season);
            $season->update(['oofscore_closed_at' => now()]);
        });
    }

    /**
     * @return list<string>
     */
    public function reasonsNotToClose(Season $season, CarbonImmutable $now): array
    {
        if ($season->isClosed()) {
            return ['it is closed already'];
        }

        $lastRound = Round::query()->where('season_id', $season->id)->orderByDesc('round_number')->first();
        $lastRace = $lastRound?->raceSessions()->where('type', SessionType::Race)->first();
        $reasons = [];

        if ($lastRace === null || $lastRace->achievements_checked_at === null) {
            $reasons[] = 'its last race has not been run by the achievements engine';
        } elseif (! SessionData::load($lastRace, app(MessageClassifier::class))->isFinal($now)) {
            $reasons[] = 'its last race is not final yet';
        }

        $pending = AchievementAward::query()->where('season_id', $season->id)->where('status', AwardStatus::Pending)->count();

        if ($pending > 0) {
            $reasons[] = "{$pending} awards still wait for review";
        }

        return $reasons;
    }

    /**
     * @return list<array{season_id: int, round_id: int, driver_id: int, points: int, awards_count: int, position: int, position_change: int|null}>
     */
    private function compute(Season $season): array
    {
        $awards = AchievementAward::query()
            ->where('season_id', $season->id)
            ->where('status', AwardStatus::Final)
            ->where('recipient_type', RecipientType::Driver)
            ->whereNotNull('driver_id')
            ->whereNotNull('round_id')
            ->whereIn('shelf', config('achievements.points.scoring_shelves'))
            ->toBase()
            ->get(['round_id', 'driver_id', 'points'])
            ->groupBy('round_id');

        // A round counts once it has any award, whatever its status: one without data so far adds no row.
        $rounds = Round::query()
            ->where('season_id', $season->id)
            ->whereIn('id', AchievementAward::query()->where('season_id', $season->id)->select('round_id'))
            ->orderBy('round_number')
            ->pluck('id');

        $totals = [];
        $previous = [];
        $rows = [];

        foreach ($rounds as $roundId) {
            foreach ($awards->get($roundId, collect()) as $award) {
                $totals[$award->driver_id] ??= ['points' => 0, 'awards_count' => 0];
                $totals[$award->driver_id]['points'] += (int) $award->points;
                $totals[$award->driver_id]['awards_count']++;
            }

            $positions = [];

            foreach ($totals as $driverId => $total) {
                $positions[$driverId] = 1 + count(array_filter($totals, fn (array $other) => $other['points'] > $total['points']));
            }

            foreach ($totals as $driverId => $total) {
                $rows[] = [
                    'season_id' => $season->id,
                    'round_id' => $roundId,
                    'driver_id' => $driverId,
                    'points' => $total['points'],
                    'awards_count' => $total['awards_count'],
                    'position' => $positions[$driverId],
                    'position_change' => isset($previous[$driverId]) ? $previous[$driverId] - $positions[$driverId] : null,
                ];
            }

            $previous = $positions;
        }

        return $rows;
    }

    /**
     * @return list<array<string, int|null>>
     */
    private function rowsOf(Season $season): array
    {
        return OofscoreStanding::query()
            ->where('season_id', $season->id)
            ->get(['season_id', 'round_id', 'driver_id', 'points', 'awards_count', 'position', 'position_change'])
            ->map(fn (OofscoreStanding $standing) => $standing->only(['season_id', 'round_id', 'driver_id', 'points', 'awards_count', 'position', 'position_change']))
            ->all();
    }

    /**
     * One round's rows, comparable.
     *
     * @param  list<array<string, int|null>>  $rows
     * @return list<array<string, int|null>>
     */
    private static function of(array $rows, int $roundId): array
    {
        return collect($rows)
            ->where('round_id', $roundId)
            ->map(fn (array $row) => array_map(fn ($value) => $value === null ? null : (int) $value, $row))
            ->sortBy('driver_id')
            ->values()
            ->all();
    }

    /**
     * Rebuild every open season of the given IDs.
     *
     * @param  iterable<int>  $seasonIds
     * @return Collection<int, array{rounds: int, rows: int, changed_rounds: int}|null> Keyed by year
     */
    public function rebuildSeasons(iterable $seasonIds): Collection
    {
        return Season::query()->whereKey(collect($seasonIds)->unique()->all())->orderBy('year')->get()
            ->mapWithKeys(fn (Season $season) => [$season->year => $this->rebuild($season)]);
    }
}
