<?php

namespace App\Excavation\Excavators;

use App\Excavation\Candidate;
use App\Excavation\DigParameters;
use App\Excavation\Excavator;
use App\Excavation\Support\CareerTimeline;
use Illuminate\Support\Collection;

/**
 * The race of a driver's 100th, 200th, 300th or 400th start; the first
 * driver ever to reach a milestone scores higher.
 */
class StartMilestoneExcavator implements Excavator
{
    public function __construct(private CareerTimeline $timeline) {}

    public function key(): string
    {
        return 'start_milestone';
    }

    public function version(): int
    {
        return 1;
    }

    public function dig(DigParameters $parameters): Collection
    {
        $milestones = config('excavators.start_milestone.milestones');
        $firstToReachBonus = (float) config('excavators.start_milestone.first_to_reach_bonus');

        return $this->timeline->rows()
            ->filter(fn (object $row) => isset($milestones[$row->start_number]))
            ->unique(fn (object $row) => $row->driver_id.':'.$row->start_number)
            ->groupBy('start_number')
            ->flatMap(fn (Collection $reached, int $milestone) => $reached->values()->map(fn (object $row, int $index) => [$row, $milestone, $index]))
            ->filter(fn (array $hit) => $parameters->includes($hit[0]->year))
            ->map(fn (array $hit) => new Candidate(
                roundId: $hit[0]->round_id,
                driverId: $hit[0]->driver_id,
                seasonTeamId: $hit[0]->season_team_id,
                score: min(100, $milestones[$hit[1]] + ($hit[2] === 0 ? $firstToReachBonus : 0)),
                evidence: [
                    'start_number' => $hit[1],
                    'drivers_before' => $hit[2],
                    'first_to_reach' => $hit[2] === 0,
                ],
            ))
            ->values();
    }
}
