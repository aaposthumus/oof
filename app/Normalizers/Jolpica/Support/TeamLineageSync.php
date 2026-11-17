<?php

namespace App\Normalizers\Jolpica\Support;

use App\Models\SeasonTeam;
use App\Models\Team;
use App\Models\TeamLineage;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Re-points every season team to the lasting team the lineage rules give
 * it, so a changed rule reaches the seasons already imported. A team left
 * without seasons is removed; a team takes the name of its latest season.
 * Throws a LogicException (and changes nothing) when the rules would merge
 * an unmapped constructor into a lineage team.
 */
class TeamLineageSync
{
    /**
     * @return array{moved: int, removed_teams: int}
     */
    public function run(): array
    {
        return DB::transaction(function () {
            $resolver = new TeamResolver(TeamLineage::rules());
            $touched = [];
            $moved = 0;

            foreach (SeasonTeam::query()->with(['season', 'team'])->get() as $seasonTeam) {
                $slug = $resolver->slugFor($seasonTeam->jolpica_constructor_id, $seasonTeam->season->year);

                if ($seasonTeam->team->slug === $slug) {
                    continue;
                }

                $team = Team::firstOrCreate(['slug' => $slug], ['name' => $seasonTeam->name]);
                $touched[$seasonTeam->team_id] = true;
                $touched[$team->id] = true;
                $seasonTeam->update(['team_id' => $team->id]);
                $moved++;
            }

            $removed = 0;

            foreach (Team::query()->whereKey(array_keys($touched))->get() as $team) {
                $latest = $team->seasonTeams()
                    ->join('seasons', 'seasons.id', '=', 'season_teams.season_id')
                    ->orderByDesc('seasons.year')
                    ->value('season_teams.name');

                if ($latest === null) {
                    $team->delete();
                    $removed++;
                } elseif ($team->name !== $latest) {
                    $team->update(['name' => $latest]);
                }
            }

            return ['moved' => $moved, 'removed_teams' => $removed];
        });
    }
}
