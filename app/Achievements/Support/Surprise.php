<?php

namespace App\Achievements\Support;

use App\Achievements\SessionData;
use App\Enums\SessionType;
use App\Models\ConstructorStanding;
use App\Models\DriverEntry;
use App\Models\Result;
use App\Models\Round;

/**
 * Whether a win or pole is a surprise: the team stands outside the top of
 * the constructors' championship before the round, or (after the first
 * rounds) the driver has not started in the top of the grid earlier that
 * season. In the first rounds the previous season's final standings count,
 * as in SurpriseWinnerExcavator.
 */
class Surprise
{
    /**
     * The reason it is a surprise, or null when it is not.
     */
    public static function reason(DriverEntry $entry, SessionData $data): ?string
    {
        $topTeams = (int) config('achievements.surprise.top_teams');
        $topGrid = (int) config('achievements.surprise.top_grid');
        $earlyRounds = (int) config('achievements.surprise.early_season_rounds');
        $round = $data->session->round;
        $position = self::teamPositionBefore($entry, $round, $earlyRounds);

        if ($position !== null && $position > $topTeams) {
            return "Team P{$position} in the constructors' standings before the round";
        }

        if ($round->round_number > $earlyRounds && ! self::startedNearTheFront($entry, $round, $topGrid)) {
            return "No start in the top {$topGrid} earlier this season";
        }

        return null;
    }

    private static function teamPositionBefore(DriverEntry $entry, Round $round, int $earlyRounds): ?int
    {
        $teamId = $entry->seasonTeam->team_id;
        $previousSeason = $round->round_number <= $earlyRounds;
        $standingsRound = Round::query()
            ->whereRelation('season', 'year', $previousSeason ? $round->season->year - 1 : $round->season->year)
            ->when(! $previousSeason, fn ($query) => $query->where('round_number', '<', $round->round_number))
            ->whereHas('constructorStandings')
            ->orderByDesc('round_number')
            ->first();

        if ($standingsRound === null) {
            return null;
        }

        $standings = ConstructorStanding::query()
            ->where('round_id', $standingsRound->id)
            ->whereNotNull('position')
            ->with('seasonTeam')
            ->get();
        $standing = $standings->first(fn (ConstructorStanding $standing) => $standing->seasonTeam->team_id === $teamId);

        // A new or renamed team has no position from the season before; a pointless one stands below the rest.
        return match (true) {
            $standing !== null => (int) $standing->position,
            $previousSeason => null,
            default => $standings->count() + 1,
        };
    }

    private static function startedNearTheFront(DriverEntry $entry, Round $round, int $topGrid): bool
    {
        return Result::query()
            ->whereBetween('grid', [1, $topGrid])
            ->whereHas('driverEntry', fn ($query) => $query
                ->where('driver_id', $entry->driver_id)
                ->whereHas('raceSession', fn ($query) => $query
                    ->where('type', SessionType::Race)
                    ->whereHas('round', fn ($query) => $query->where('season_id', $round->season_id)->where('round_number', '<', $round->round_number))))
            ->exists();
    }
}
