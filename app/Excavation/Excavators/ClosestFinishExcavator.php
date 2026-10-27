<?php

namespace App\Excavation\Excavators;

use App\Excavation\Candidate;
use App\Excavation\DigParameters;
use App\Excavation\Excavator;
use App\Excavation\Support\CareerTimeline;
use App\Excavation\Support\RaceFinish;
use Illuminate\Support\Collection;

/**
 * The smallest winning margins. Staged and safety-car finishes cannot be
 * told apart without lap data.
 */
class ClosestFinishExcavator implements Excavator
{
    public function __construct(private CareerTimeline $timeline) {}

    public function key(): string
    {
        return 'closest_finish';
    }

    public function version(): int
    {
        return 1;
    }

    public function dig(DigParameters $parameters): Collection
    {
        $maxGap = (int) config('excavators.closest_finish.max_gap_ms');

        return $this->timeline->byRound()
            ->filter(fn (Collection $race) => $parameters->includes($race->first()->year))
            ->map(fn (Collection $race) => RaceFinish::of($race))
            ->filter(fn (?RaceFinish $finish) => $finish?->gapMillis() !== null && $finish->gapMillis() >= 0 && $finish->gapMillis() <= $maxGap)
            ->map(fn (RaceFinish $finish) => new Candidate(
                roundId: $finish->winner->round_id,
                driverId: $finish->winner->driver_id,
                seasonTeamId: $finish->winner->season_team_id,
                score: Candidate::scoreFromShare(1 - $finish->gapMillis() / ($maxGap + 1)),
                evidence: [
                    'gap_ms' => $finish->gapMillis(),
                    'runner_up' => $finish->runnerUp->driver_name,
                ],
            ))
            ->values();
    }
}
