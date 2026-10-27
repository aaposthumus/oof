<?php

namespace App\Excavation\Excavators;

use App\Excavation\Candidate;
use App\Excavation\DigParameters;
use App\Excavation\Excavator;
use App\Excavation\Support\CareerTimeline;
use App\Models\ConstructorStanding;
use App\Models\SeasonTeam;
use App\Models\Team;
use Illuminate\Support\Collection;

/**
 * Wins by a team far down the constructors' standings before the race, or a
 * driver's only career win.
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
        return 2;
    }

    public function dig(DigParameters $parameters): Collection
    {
        $minPosition = (int) config('excavators.surprise_winner.min_position');
        $minRelative = (float) config('excavators.surprise_winner.min_relative');
        $onlyWinBonus = (float) config('excavators.surprise_winner.only_win_bonus');

        $wins = $this->timeline->rows()->where('is_win', true);
        $careerWins = $wins->unique(fn (object $row) => $row->round_id.':'.$row->driver_id)->countBy('driver_id');
        $standings = $this->standingsBefore();

        return $wins
            ->filter(fn (object $win) => $parameters->includes($win->year))
            ->unique(fn (object $win) => $win->round_id.':'.$win->driver_id)
            ->map(function (object $win) use ($standings, $careerWins, $minPosition, $minRelative, $onlyWinBonus) {
                $standing = $standings($win);
                $onlyWin = $careerWins[$win->driver_id] === 1;
                $relative = $standing !== null && $standing['teams'] > 1 ? ($standing['position'] - 1) / ($standing['teams'] - 1) : null;
                $teamHit = $relative !== null && $standing['position'] >= $minPosition && $relative >= $minRelative;

                if (! $teamHit && ! $onlyWin) {
                    return null;
                }

                return new Candidate(
                    roundId: $win->round_id,
                    driverId: $win->driver_id,
                    seasonTeamId: $win->season_team_id,
                    score: min(100, round(100 * ($teamHit ? $relative : 0) + ($onlyWin ? $onlyWinBonus : 0), 2)),
                    evidence: [
                        'team_position_before' => $standing['position'] ?? null,
                        'teams_before' => $standing['teams'] ?? null,
                        'standings_from_previous_season' => $standing['previous_season'] ?? false,
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
     * Finds the winner's team position before the race: after the previous
     * round, or in the first early_season_rounds rounds the team's final
     * position the season before. A team without points stands just below the
     * scoring teams; standings list only those, so the field is the teams in
     * the race. The position is unknown (null) for a team excluded from the
     * standings, and for one that is missing although its drivers scored.
     *
     * @return callable(object): (array{position: int, teams: int, previous_season: bool}|null)
     */
    private function standingsBefore(): callable
    {
        $earlyRounds = (int) config('excavators.surprise_winner.early_season_rounds');
        $rows = ConstructorStanding::query()
            ->toBase()
            ->join('rounds', 'rounds.id', '=', 'constructor_standings.round_id')
            ->join('seasons', 'seasons.id', '=', 'rounds.season_id')
            ->join('season_teams', 'season_teams.id', '=', 'constructor_standings.season_team_id')
            ->whereNotNull('constructor_standings.position')
            ->get(['seasons.year', 'rounds.round_number', 'constructor_standings.season_team_id', 'season_teams.team_id', 'constructor_standings.position']);

        $byRound = $rows->groupBy(fn (object $row) => $row->year.':'.$row->round_number);
        $finalRound = $rows->groupBy('year')->map(fn (Collection $season) => $season->max('round_number'));
        $teamOf = SeasonTeam::query()->pluck('team_id', 'id');
        $slugOf = Team::query()->pluck('slug', 'id');
        $teamsInRace = $this->timeline->byRound()->map(fn (Collection $race) => $race->unique('season_team_id')->count());
        $excluded = collect(config('excavators.surprise_winner.excluded_teams'))
            ->map(fn (array $exclusion) => $exclusion['year'].':'.$exclusion['team'])
            ->flip();
        $scoringRounds = $this->timeline->rows()
            ->where('points', '>', 0)
            ->groupBy(fn (object $row) => $row->year.':'.$teamOf->get($row->season_team_id))
            ->map(fn (Collection $scored) => $scored->min('round_number'));

        return function (object $win) use ($earlyRounds, $byRound, $finalRound, $teamOf, $slugOf, $excluded, $scoringRounds, $teamsInRace): ?array {
            $previousSeason = $win->round_number <= $earlyRounds;
            $year = $previousSeason ? $win->year - 1 : $win->year;
            $standingsRound = $previousSeason ? $finalRound->get($year) : $win->round_number - 1;
            $standings = $byRound->get($year.':'.$standingsRound);
            $teamId = $teamOf->get($win->season_team_id);

            if ($standings === null || $excluded->has($year.':'.$slugOf->get($teamId))) {
                return null;
            }

            $standing = $standings->firstWhere('team_id', $teamId);

            // A new team, or one renamed, has no position from the season before.
            if ($standing === null && $previousSeason) {
                return null;
            }

            // Missing although its drivers had scored by then: excluded, not pointless.
            if ($standing === null && ($scoringRounds->get($year.':'.$teamId) ?? PHP_INT_MAX) <= $standingsRound) {
                return null;
            }

            $position = $standing === null ? $standings->count() + 1 : (int) $standing->position;

            return ['position' => $position, 'teams' => max($position, $teamsInRace[$win->round_id]), 'previous_season' => $previousSeason];
        };
    }
}
