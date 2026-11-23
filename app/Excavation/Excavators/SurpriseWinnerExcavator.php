<?php

namespace App\Excavation\Excavators;

use App\Achievements\Support\Surprise;
use App\Excavation\Candidate;
use App\Excavation\DigParameters;
use App\Excavation\Excavator;
use App\Excavation\Support\CareerTimeline;
use App\Models\Round;
use App\Models\SeasonTeam;
use Illuminate\Support\Collection;

/**
 * Wins that are a surprise by the same rule as Giant Slayer (the lasting
 * team outside the top on rolling team form, see Surprise; a team without
 * form is no surprise), or a driver's only career win.
 */
class SurpriseWinnerExcavator implements Excavator
{
    public function __construct(private CareerTimeline $timeline) {}

    public function key(): string
    {
        return 'surprise_winner';
    }

    public function version(): int
    {
        return 4;
    }

    public function dig(DigParameters $parameters): Collection
    {
        $onlyWinBonus = (float) config('excavators.surprise_winner.only_win_bonus');

        $wins = $this->timeline->rows()->where('is_win', true);
        $careerWins = $wins->unique(fn (object $row) => $row->round_id.':'.$row->driver_id)->countBy('driver_id');
        $teamOf = SeasonTeam::query()->pluck('team_id', 'id');
        $winsToJudge = $wins
            ->filter(fn (object $win) => $parameters->includes($win->year))
            ->unique(fn (object $win) => $win->round_id.':'.$win->driver_id);
        $rounds = Round::query()->with('season')->findMany($winsToJudge->pluck('round_id')->unique())->keyBy('id');

        return $winsToJudge
            ->map(function (object $win) use ($careerWins, $onlyWinBonus, $teamOf, $rounds) {
                $surprise = Surprise::forTeam((int) $teamOf->get($win->season_team_id), $rounds[$win->round_id]);
                $onlyWin = $careerWins[$win->driver_id] === 1;

                if ($surprise === null && ! $onlyWin) {
                    return null;
                }

                $relative = $surprise === null ? null : self::relative($surprise['rank'], $surprise['form']->teams->count());
                $team = $surprise === null ? null : $surprise['form']->teams->firstWhere('team_id', $teamOf->get($win->season_team_id));

                return new Candidate(
                    roundId: $win->round_id,
                    driverId: $win->driver_id,
                    seasonTeamId: $win->season_team_id,
                    score: min(100, round(100 * ($relative ?? 0) + ($onlyWin ? $onlyWinBonus : 0), 2)),
                    evidence: [
                        'team_form_rank' => $surprise['rank'] ?? null,
                        'teams_in_window' => $surprise === null ? null : $surprise['form']->teams->count(),
                        'window_races' => $surprise === null ? null : $surprise['form']->races->count(),
                        'team_points_per_race' => $team['per_race'] ?? null,
                        'team_relative' => $relative === null ? null : round($relative, 3),
                        'career_wins' => $careerWins[$win->driver_id],
                        'only_career_win' => $onlyWin,
                    ],
                );
            })
            ->filter()
            ->values();
    }

    /**
     * How far down the form the team stood: 0 = best, 1 = last.
     */
    private static function relative(int $rank, int $teams): float
    {
        return $teams <= 1 ? 1.0 : ($rank - 1) / ($teams - 1);
    }
}
