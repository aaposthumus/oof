<?php

namespace App\Excavation\Excavators;

use App\Excavation\Candidate;
use App\Excavation\DigParameters;
use App\Excavation\Excavator;
use App\Excavation\Support\CareerTimeline;
use App\Excavation\Support\RaceStatuses;
use Illuminate\Support\Collection;

/**
 * Starters whose result status makes a story (out of fuel, a lost wheel, a
 * broken seat), the rarer the better. Statuses about injury, illness or a
 * fatal accident are never on the list.
 */
class CuriousRetirementExcavator implements Excavator
{
    public function __construct(private CareerTimeline $timeline) {}

    public function key(): string
    {
        return 'curious_retirement';
    }

    public function version(): int
    {
        return 1;
    }

    public function dig(DigParameters $parameters): Collection
    {
        $curious = RaceStatuses::curious();
        $hits = $this->timeline->rows()->filter(fn (object $row) => $row->is_start && in_array($row->status, $curious, true));
        $counts = $hits->countBy('status');
        $mostCommon = (int) $counts->max();

        return $hits
            ->filter(fn (object $row) => $parameters->includes($row->year))
            ->unique(fn (object $row) => $row->round_id.':'.$row->driver_id)
            ->map(fn (object $row) => new Candidate(
                roundId: $row->round_id,
                driverId: $row->driver_id,
                seasonTeamId: $row->season_team_id,
                score: Candidate::scoreFromShare(1 - $counts[$row->status] / ($mostCommon + 1)),
                evidence: [
                    'status' => $row->status,
                    'laps_completed' => $row->laps_completed,
                    'classified' => ctype_digit($row->position_text),
                    'status_count_in_history' => $counts[$row->status],
                ],
            ))
            ->values();
    }
}
