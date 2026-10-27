<?php

namespace App\Excavation\Excavators;

use App\Excavation\Candidate;
use App\Excavation\DigParameters;
use App\Excavation\Excavator;
use App\Excavation\Support\CareerTimeline;
use Illuminate\Support\Collection;

/**
 * Races with disqualified drivers, headed by the one who lost the most. The
 * road position of a disqualified driver is only known when the data still
 * has their race time; otherwise completing the winner's laps is the best
 * sign of a lost result.
 */
class DisqualificationExcavator implements Excavator
{
    public function __construct(private CareerTimeline $timeline) {}

    public function key(): string
    {
        return 'disqualification';
    }

    public function version(): int
    {
        return 1;
    }

    public function dig(DigParameters $parameters): Collection
    {
        $extraDriverScore = (float) config('excavators.disqualification.extra_driver_score');

        return $this->timeline->byRound()
            ->filter(fn (Collection $race) => $race->contains('is_disqualified', true) && $parameters->includes($race->first()->year))
            ->map(function (Collection $race) use ($extraDriverScore) {
                $disqualified = $race->where('is_disqualified', true)
                    ->unique('driver_id')
                    ->map(fn (object $row) => $this->judge($row, $race))
                    ->sortByDesc('score')
                    ->values();
                $headline = $disqualified->first();

                return new Candidate(
                    roundId: $headline['row']->round_id,
                    driverId: $headline['row']->driver_id,
                    seasonTeamId: $headline['row']->season_team_id,
                    score: min(100, $headline['score'] + $extraDriverScore * ($disqualified->count() - 1)),
                    evidence: [
                        'disqualified' => $disqualified->count(),
                        'drivers' => $disqualified->map(fn (array $judged) => $judged['row']->driver_name)->implode(', '),
                        'statuses' => $disqualified->map(fn (array $judged) => $judged['row']->status)->unique()->implode(', '),
                        'road_position' => $headline['road_position'],
                        'full_distance' => $headline['full_distance'],
                        'laps_completed' => $headline['row']->laps_completed,
                    ],
                );
            })
            ->values();
    }

    /**
     * @param  Collection<int, object>  $race
     * @return array{row: object, road_position: int|null, full_distance: bool, score: float}
     */
    private function judge(object $row, Collection $race): array
    {
        $winnerLaps = (int) $race->where('is_start', true)->max('laps_completed');
        $roadPosition = $row->time_millis === null ? null : 1 + $race
            ->where('driver_id', '!=', $row->driver_id)
            ->filter(fn (object $other) => $other->laps_completed > $row->laps_completed
                || ($other->laps_completed === $row->laps_completed && $other->time_millis !== null && $other->time_millis < $row->time_millis))
            ->unique('car_number')
            ->count();
        $fullDistance = $row->laps_completed !== null && $row->laps_completed >= $winnerLaps;

        $score = match (true) {
            $roadPosition === 1 => config('excavators.disqualification.road_winner_score'),
            $roadPosition !== null && $roadPosition <= 3 => config('excavators.disqualification.road_podium_score'),
            $fullDistance => config('excavators.disqualification.full_distance_score'),
            default => config('excavators.disqualification.other_score'),
        };

        return ['row' => $row, 'road_position' => $roadPosition, 'full_distance' => $fullDistance, 'score' => (float) $score];
    }
}
