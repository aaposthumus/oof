<?php

namespace App\Excavation\Excavators;

use App\Excavation\Candidate;
use App\Excavation\DigParameters;
use App\Excavation\Excavator;
use App\Excavation\Support\RaceRound;
use App\Excavation\Support\RaceRoundStats;
use Illuminate\Support\Collection;

/**
 * Races with the fewest starters, judged against the season's median field
 * so a small field in a small-field era does not stand out.
 */
class FewestStartersExcavator implements Excavator
{
    public function __construct(private RaceRoundStats $stats) {}

    public function key(): string
    {
        return 'fewest_starters';
    }

    public function version(): int
    {
        return 1;
    }

    public function dig(DigParameters $parameters): Collection
    {
        $maxRatio = (float) config('excavators.fewest_starters.max_ratio');
        $maxStarters = (int) config('excavators.fewest_starters.max_starters');

        return $this->stats->forParameters($parameters)
            ->filter(fn (RaceRound $race) => $race->starters > 0)
            ->map(fn (RaceRound $race) => [$race, $race->starters / $race->seasonMedianStarters])
            ->filter(fn (array $hit) => $hit[1] <= $maxRatio || $hit[0]->starters <= $maxStarters)
            ->map(fn (array $hit) => new Candidate(
                roundId: $hit[0]->roundId,
                driverId: null,
                seasonTeamId: null,
                score: Candidate::scoreFromShare(1 - $hit[1]),
                evidence: [
                    'starters' => $hit[0]->starters,
                    'entries' => $hit[0]->entries,
                    'season_median_starters' => $hit[0]->seasonMedianStarters,
                    'ratio' => round($hit[1], 3),
                ],
            ))
            ->values();
    }
}
