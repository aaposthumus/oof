<?php

namespace App\Excavation\Excavators;

use App\Excavation\Candidate;
use App\Excavation\DigParameters;
use App\Excavation\Excavator;
use App\Excavation\Support\CareerTimeline;
use Illuminate\Support\Collection;

/**
 * A driver's first win or podium after the most starts without one.
 */
abstract class DroughtEndedExcavator implements Excavator
{
    public function __construct(private CareerTimeline $timeline) {}

    public function version(): int
    {
        return 1;
    }

    /**
     * Whether this result ends the drought.
     */
    abstract protected function ends(object $row): bool;

    public function dig(DigParameters $parameters): Collection
    {
        $minStarts = (int) config("excavators.{$this->key()}.min_starts");

        return $this->timeline->rows()
            ->filter(fn (object $row) => $this->ends($row))
            ->unique('driver_id')
            ->filter(fn (object $row) => $row->starts_before >= $minStarts && $parameters->includes($row->year))
            ->map(fn (object $row) => new Candidate(
                roundId: $row->round_id,
                driverId: $row->driver_id,
                seasonTeamId: $row->season_team_id,
                score: (float) $row->starts_before,
                evidence: [
                    'starts_before' => $row->starts_before,
                    'start_number' => $row->start_number,
                    'position' => $row->position,
                ],
            ))
            ->values();
    }
}
