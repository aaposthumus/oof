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
 * without seasons is removed; a team takes its fixed name from the rules,
 * else the name of its latest season.
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
            $teams = Team::query()
                ->whereKey(array_keys($touched))
                ->orWhereIn('slug', $resolver->lineageTeams())
                ->get();

            foreach ($teams as $team) {
                $latest = $team->seasonTeams()
                    ->join('seasons', 'seasons.id', '=', 'season_teams.season_id')
                    ->orderByDesc('seasons.year')
                    ->value('season_teams.name');

                $name = $resolver->nameFor($team->slug) ?? $latest;

                if ($latest === null) {
                    if (isset($touched[$team->id])) {
                        $team->delete();
                        $removed++;
                    }
                } elseif ($team->name !== $name) {
                    $team->update(['name' => $name]);
                }
            }

            return ['moved' => $moved, 'removed_teams' => $removed];
        });
    }
}
