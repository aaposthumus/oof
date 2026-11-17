<?php

namespace App\Achievements\Support;

use App\Enums\SessionType;
use App\Models\RaceSession;
use App\Models\Result;
use App\Models\Round;
use Illuminate\Support\Collection;

/**
 * The form of every lasting team over the last Grands Prix before a round,
 * in calendar order across season boundaries: the points its cars scored in
 * the window divided by the window's races it entered. Ranking on that figure
 * within the window makes the points system of each era irrelevant. Sprints
 * and the Indianapolis 500 rounds of 1950 to 1960 are left out.
 */
class TeamForm
{
    private const INDIANAPOLIS_500 = 'Indianapolis 500';

    /**
     * @param  Collection<int, RaceSession>  $races  The window, latest first
     * @param  Collection<int, array{team_id: int, name: string, points: float, races: int, per_race: float}>  $teams  Best form first
     */
    private function __construct(
        public readonly Collection $races,
        public readonly Collection $teams,
    ) {}

    public static function before(Round $round, ?int $windowRaces = null): self
    {
        $windowRaces ??= (int) config('achievements.surprise.window_races');
        $round->loadMissing('season');

        $races = RaceSession::query()
            ->select('race_sessions.*')
            ->join('rounds', 'rounds.id', '=', 'race_sessions.round_id')
            ->join('seasons', 'seasons.id', '=', 'rounds.season_id')
            ->where('race_sessions.type', SessionType::Race)
            ->where(fn ($query) => $query->whereNull('rounds.jolpica_race_name')->orWhere('rounds.jolpica_race_name', '!=', self::INDIANAPOLIS_500))
            ->where('rounds.name', '!=', self::INDIANAPOLIS_500)
            ->where(fn ($query) => $query
                ->where('seasons.year', '<', $round->season->year)
                ->orWhere(fn ($query) => $query->where('seasons.year', $round->season->year)->where('rounds.round_number', '<', $round->round_number)))
            ->whereHas('driverEntries.result')
            ->orderByDesc('seasons.year')
            ->orderByDesc('rounds.round_number')
            ->limit($windowRaces)
            ->with('round.season')
            ->get();

        $teams = Result::query()
            ->join('driver_entries', 'driver_entries.id', '=', 'results.driver_entry_id')
            ->join('season_teams', 'season_teams.id', '=', 'driver_entries.season_team_id')
            ->join('teams', 'teams.id', '=', 'season_teams.team_id')
            ->whereIn('driver_entries.race_session_id', $races->pluck('id'))
            ->groupBy('season_teams.team_id', 'teams.name')
            ->selectRaw('season_teams.team_id, teams.name, coalesce(sum(results.points), 0) as points, count(distinct driver_entries.race_session_id) as races')
            ->toBase()
            ->get()
            ->map(fn (object $row) => [
                'team_id' => (int) $row->team_id,
                'name' => (string) $row->name,
                'points' => (float) $row->points,
                'races' => (int) $row->races,
                'per_race' => round((float) $row->points / (int) $row->races, 6),
            ])
            ->sortBy([['per_race', 'desc'], ['team_id', 'asc']])
            ->values();

        return new self($races, $teams);
    }

    /**
     * The team's rank on points per race; equal figures share the better
     * rank. Null when the team has no race in the window.
     */
    public function rankOf(int $teamId): ?int
    {
        $team = $this->teams->firstWhere('team_id', $teamId);

        if ($team === null) {
            return null;
        }

        return 1 + $this->teams->filter(fn (array $other) => $other['per_race'] > $team['per_race'])->count();
    }

    public function isEmpty(): bool
    {
        return $this->races->isEmpty();
    }

    /**
     * "2025 Mexico City Grand Prix to 2026 Bahrain Grand Prix", oldest first.
     */
    public function windowLabel(): string
    {
        $label = fn (RaceSession $race): string => "{$race->round->season->year} {$race->round->name}";

        return $this->races->count() === 1
            ? $label($this->races->first())
            : $label($this->races->last()).' to '.$label($this->races->first());
    }

    /**
     * "McLaren 30.4" for each team down to the given rank.
     */
    public function summary(int $throughRank): string
    {
        return $this->teams
            ->filter(fn (array $team) => $this->rankOf($team['team_id']) <= $throughRank)
            ->map(fn (array $team) => $team['name'].' '.self::figure($team['per_race']))
            ->implode(', ');
    }

    public static function figure(float $perRace): string
    {
        return rtrim(rtrim(number_format($perRace, 1, '.', ''), '0'), '.');
    }
}
