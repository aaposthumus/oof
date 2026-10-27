<?php

namespace App\Excavation\Excavators;

use App\Excavation\Candidate;
use App\Excavation\DigParameters;
use App\Excavation\Excavator;
use App\Excavation\Support\CareerTimeline;
use App\Excavation\Support\RaceFinish;
use Illuminate\Support\Collection;

/**
 * Wins in which the winner lapped the runner-up or almost the whole field,
 * or won by a huge share of the race time.
 */
class DominantWinExcavator implements Excavator
{
    public function __construct(private CareerTimeline $timeline) {}

    public function key(): string
    {
        return 'dominant_win';
    }

    public function version(): int
    {
        return 1;
    }

    public function dig(DigParameters $parameters): Collection
    {
        $minLappedShare = (float) config('excavators.dominant_win.min_lapped_share');
        $minGapShare = (float) config('excavators.dominant_win.min_gap_share');
        $lappedScore = (float) config('excavators.dominant_win.lapped_score');
        $extraLapScore = (float) config('excavators.dominant_win.extra_lap_score');

        return $this->timeline->byRound()
            ->filter(fn (Collection $race) => $parameters->includes($race->first()->year))
            ->map(fn (Collection $race) => RaceFinish::of($race))
            ->filter(fn (?RaceFinish $finish) => $finish?->runnerUp !== null && $finish->winner->laps_completed !== null)
            ->map(function (RaceFinish $finish) use ($minLappedShare, $minGapShare, $lappedScore, $extraLapScore) {
                $winner = $finish->winner;
                $lapsAhead = $winner->laps_completed - (int) $finish->runnerUp->laps_completed;
                $lappedShare = $finish->rivals->filter(fn (object $rival) => $rival->laps_completed < $winner->laps_completed)->count() / $finish->rivals->count();
                $gapShare = $finish->gapMillis() !== null && $winner->time_millis > 0 ? $finish->gapMillis() / $winner->time_millis : null;

                if ($lapsAhead < 1 && $lappedShare < $minLappedShare && ($gapShare ?? 0) < $minGapShare) {
                    return null;
                }

                $score = $lapsAhead >= 1
                    ? min(100, $lappedScore + $extraLapScore * ($lapsAhead - 1))
                    : $lappedScore * max($lappedShare, min(1, ($gapShare ?? 0) / (2 * $minGapShare)));

                return new Candidate(
                    roundId: $winner->round_id,
                    driverId: $winner->driver_id,
                    seasonTeamId: $winner->season_team_id,
                    score: round($score, 2),
                    evidence: [
                        'laps_ahead' => $lapsAhead,
                        'lapped_rivals' => $finish->rivals->filter(fn (object $rival) => $rival->laps_completed < $winner->laps_completed)->count(),
                        'classified_rivals' => $finish->rivals->count(),
                        'gap_ms' => $finish->gapMillis(),
                        'gap_share' => $gapShare === null ? null : round($gapShare, 4),
                        'runner_up' => $finish->runnerUp->driver_name,
                    ],
                );
            })
            ->filter()
            ->values();
    }
}
