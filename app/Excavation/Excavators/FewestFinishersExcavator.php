<?php

namespace App\Excavation\Excavators;

use App\Excavation\Candidate;
use App\Excavation\DigParameters;
use App\Excavation\Excavator;
use App\Excavation\Support\RaceRound;
use App\Excavation\Support\RaceRoundStats;
use Illuminate\Support\Collection;

/**
 * Races with the fewest cars running at the flag, relative to the number of
 * starters. Classified cars that crashed out late do not count as running.
 */
class FewestFinishersExcavator implements Excavator
{
    public function __construct(private RaceRoundStats $stats) {}

    public function key(): string
    {
        return 'fewest_finishers';
    }

    public function version(): int
    {
        return 1;
    }

    public function dig(DigParameters $parameters): Collection
    {
        $maxRatio = (float) config('excavators.fewest_finishers.max_ratio');
        $maxRunning = (int) config('excavators.fewest_finishers.max_running');
        $minStarters = (int) config('excavators.fewest_finishers.min_starters');

        return $this->stats->forParameters($parameters)
            ->filter(fn (RaceRound $race) => $race->starters >= max(1, $minStarters))
            ->map(fn (RaceRound $race) => [$race, $race->running / $race->starters])
            ->filter(fn (array $hit) => $hit[1] <= $maxRatio || $hit[0]->running <= $maxRunning)
            ->map(fn (array $hit) => new Candidate(
                roundId: $hit[0]->roundId,
                driverId: null,
                seasonTeamId: null,
                score: Candidate::scoreFromShare(1 - $hit[1]),
                evidence: [
                    'running' => $hit[0]->running,
                    'classified' => $hit[0]->classified,
                    'starters' => $hit[0]->starters,
                    'ratio' => round($hit[1], 3),
                ],
            ))
            ->values();
    }
}
