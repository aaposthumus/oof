<?php

namespace App\Excavation\Excavators;

use App\Excavation\Candidate;
use App\Excavation\DigParameters;
use App\Excavation\Excavator;
use App\Excavation\Support\CareerTimeline;
use App\Excavation\Support\RaceStatuses;
use Illuminate\Support\Collection;

/**
 * Races with the most retirements: starters not running at the flag, also
 * when classified. Disqualifications and skipped statuses (injury, illness,
 * fatal accident) are not counted.
 */
class MostRetirementsExcavator implements Excavator
{
    public function __construct(private CareerTimeline $timeline) {}

    public function key(): string
    {
        return 'most_retirements';
    }

    public function version(): int
    {
        return 1;
    }

    public function dig(DigParameters $parameters): Collection
    {
        $minRetirements = (int) config('excavators.most_retirements.min_retirements');

        return $this->timeline->byRound()
            ->filter(fn (Collection $race) => $parameters->includes($race->first()->year))
            ->map(function (Collection $race) {
                $starters = $race->where('is_start', true);
                $retired = $starters->filter(fn (object $row) => ! RaceStatuses::isRunning($row->status) && ! $row->is_disqualified && ! RaceStatuses::isSkipped($row->status));

                return [$race->first(), $starters->count(), $retired];
            })
            ->filter(fn (array $hit) => $hit[2]->count() >= $minRetirements)
            ->map(fn (array $hit) => new Candidate(
                roundId: $hit[0]->round_id,
                driverId: null,
                seasonTeamId: null,
                score: (float) $hit[2]->count(),
                evidence: [
                    'retirements' => $hit[2]->count(),
                    'starters' => $hit[1],
                    'top_statuses' => $hit[2]->countBy('status')
                        ->sortDesc()
                        ->take(3)
                        ->map(fn (int $count, string $status) => "{$status} ×{$count}")
                        ->implode(', '),
                ],
            ))
            ->values();
    }
}
