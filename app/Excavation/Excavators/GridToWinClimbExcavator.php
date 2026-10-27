<?php

namespace App\Excavation\Excavators;

use App\Excavation\Candidate;
use App\Excavation\DigParameters;
use App\Excavation\Excavator;
use App\Excavation\Support\RaceResultQuery;
use App\Excavation\Support\RaceRoundStats;
use Illuminate\Support\Collection;

/**
 * Race winners who started furthest back, both in grid places and relative
 * to the size of the field. A pit-lane start (grid 0) counts as starting last.
 */
class GridToWinClimbExcavator implements Excavator
{
    public function __construct(private RaceRoundStats $stats) {}

    public function key(): string
    {
        return 'grid_to_win_climb';
    }

    public function version(): int
    {
        return 1;
    }

    public function dig(DigParameters $parameters): Collection
    {
        $minGrid = (int) config('excavators.grid_to_win_climb.min_grid');
        $minRelative = (float) config('excavators.grid_to_win_climb.min_relative');

        $races = $this->stats->forParameters($parameters);

        $query = RaceResultQuery::make()
            ->where('results.position', 1)
            ->whereNotNull('results.grid')
            ->select('rounds.id as round_id', 'driver_entries.driver_id', 'driver_entries.season_team_id', 'results.grid');

        return $parameters->applyTo($query)->get()
            ->filter(fn (object $winner) => $races->has($winner->round_id) && $races[$winner->round_id]->starters > 1)
            ->map(function (object $winner) use ($races) {
                $winner->grid = (int) $winner->grid;
                $starters = $races[$winner->round_id]->starters;
                $effectiveGrid = $winner->grid === 0 ? $starters : $winner->grid;

                return [$winner, $starters, $effectiveGrid, min(1, ($effectiveGrid - 1) / ($starters - 1))];
            })
            ->filter(fn (array $hit) => $hit[2] >= $minGrid && $hit[3] >= $minRelative)
            ->map(fn (array $hit) => new Candidate(
                roundId: $hit[0]->round_id,
                driverId: $hit[0]->driver_id,
                seasonTeamId: $hit[0]->season_team_id,
                score: Candidate::scoreFromShare($hit[3]),
                evidence: [
                    'grid' => $hit[0]->grid,
                    'effective_grid' => $hit[2],
                    'pit_lane_start' => $hit[0]->grid === 0,
                    'starters' => $hit[1],
                    'places_gained' => $hit[2] - 1,
                ],
            ))
            ->values();
    }
}
