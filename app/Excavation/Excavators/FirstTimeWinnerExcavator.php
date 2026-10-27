<?php

namespace App\Excavation\Excavators;

use App\Excavation\Candidate;
use App\Excavation\DigParameters;
use App\Excavation\Excavator;
use App\Excavation\Support\CareerTimeline;
use Illuminate\Support\Collection;

/**
 * Every driver's first win; one-hit wonders, whose first win stayed their
 * only one, score higher.
 */
class FirstTimeWinnerExcavator implements Excavator
{
    public function __construct(private CareerTimeline $timeline) {}

    public function key(): string
    {
        return 'first_time_winner';
    }

    public function version(): int
    {
        return 1;
    }

    public function dig(DigParameters $parameters): Collection
    {
        $wins = $this->timeline->rows()
            ->where('is_win', true)
            ->unique(fn (object $row) => $row->round_id.':'.$row->driver_id);
        $careerWins = $wins->countBy('driver_id');

        return $wins
            ->unique('driver_id')
            ->filter(fn (object $win) => $parameters->includes($win->year))
            ->map(fn (object $win) => new Candidate(
                roundId: $win->round_id,
                driverId: $win->driver_id,
                seasonTeamId: $win->season_team_id,
                score: (float) config($careerWins[$win->driver_id] === 1 ? 'excavators.first_time_winner.one_hit_score' : 'excavators.first_time_winner.first_win_score'),
                evidence: [
                    'start_number' => $win->start_number,
                    'career_wins' => $careerWins[$win->driver_id],
                    'one_hit_wonder' => $careerWins[$win->driver_id] === 1,
                ],
            ))
            ->values();
    }
}
