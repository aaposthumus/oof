<?php

namespace App\Excavation\Excavators;

use App\Excavation\Candidate;
use App\Excavation\DigParameters;
use App\Excavation\Excavator;
use App\Excavation\Support\CareerTimeline;
use Illuminate\Support\Collection;

/**
 * Races cut short: the winner's laps against the scheduled distance, which
 * the data lacks, so it is estimated from the winners' laps at the same
 * circuit in nearby seasons. Half-points races always count.
 */
class ShortestRaceExcavator implements Excavator
{
    public function __construct(private CareerTimeline $timeline) {}

    public function key(): string
    {
        return 'shortest_race';
    }

    public function version(): int
    {
        return 1;
    }

    public function dig(DigParameters $parameters): Collection
    {
        $maxRatio = (float) config('excavators.shortest_race.max_ratio');
        $window = (int) config('excavators.shortest_race.window_years');

        $races = $this->timeline->byRound()
            ->map(fn (Collection $race) => (object) [
                'winner' => $race->firstWhere('is_win', true),
                'runner_up' => $race->first(fn (object $row) => $row->position === 2 && ! $row->is_disqualified),
            ])
            ->filter(fn (object $race) => $race->winner !== null && $race->winner->laps_completed !== null);

        $usualPoints = $races->groupBy(fn (object $race) => $race->winner->year)->map(fn (Collection $season) => [
            'winner' => $this->mode($season->map(fn (object $race) => $race->winner->points)),
            'runner_up' => $this->mode($season->map(fn (object $race) => $race->runner_up?->points)->filter(fn (?float $points) => $points !== null)),
        ]);
        $byCircuit = $races->groupBy(fn (object $race) => $race->winner->circuit_id);

        return $races
            ->filter(fn (object $race) => $parameters->includes($race->winner->year))
            ->map(function (object $race) use ($byCircuit, $usualPoints, $window, $maxRatio) {
                $winner = $race->winner;
                $nearby = $byCircuit[$winner->circuit_id]
                    ->filter(fn (object $other) => $other->winner->round_id !== $winner->round_id && abs($other->winner->year - $winner->year) <= $window)
                    ->map(fn (object $other) => $other->winner->laps_completed);
                $scheduledLaps = $nearby->isEmpty() ? null : (float) $nearby->median();
                $ratio = $scheduledLaps ? min(1, $winner->laps_completed / $scheduledLaps) : null;
                $halfPoints = $this->isHalfPoints($race, $usualPoints[$winner->year]);

                if (! $halfPoints && ($ratio === null || $ratio > $maxRatio)) {
                    return null;
                }

                return new Candidate(
                    roundId: $winner->round_id,
                    driverId: null,
                    seasonTeamId: null,
                    score: $ratio === null ? (float) config('excavators.shortest_race.half_points_score') : Candidate::scoreFromShare(1 - $ratio),
                    evidence: [
                        'laps' => $winner->laps_completed,
                        'estimated_scheduled_laps' => $scheduledLaps,
                        'ratio' => $ratio === null ? null : round($ratio, 3),
                        'half_points' => $halfPoints,
                        'winner_points' => $winner->points,
                    ],
                );
            })
            ->filter()
            ->values();
    }

    /**
     * Winner and runner-up both got about half their season's usual points.
     *
     * @param  array{winner: float|null, runner_up: float|null}  $usual
     */
    private function isHalfPoints(object $race, array $usual): bool
    {
        $isHalf = fn (?float $points, ?float $usualPoints) => $points !== null && $usualPoints
            && $points / $usualPoints >= (float) config('excavators.shortest_race.half_points_min')
            && $points / $usualPoints <= (float) config('excavators.shortest_race.half_points_max');

        return $isHalf($race->winner->points, $usual['winner']) && $isHalf($race->runner_up?->points, $usual['runner_up']);
    }

    /**
     * @param  Collection<int, float>  $values
     */
    private function mode(Collection $values): ?float
    {
        $mode = $values->map(fn (float $value) => (string) $value)->countBy()->sortDesc()->keys()->first();

        return $mode === null ? null : (float) $mode;
    }
}
