<?php

namespace App\Achievements\Support;

use App\Achievements\SessionData;
use App\Models\DriverEntry;

/**
 * Whether a win or pole is a surprise: the lasting team stands outside the
 * top of the rolling team form before the round (see TeamForm). A team with
 * no race in the window counts as outside it. Before the very first race
 * the window is empty and nothing is a surprise. Only the team decides; the
 * driver's own history does not.
 */
class Surprise
{
    /**
     * The award's detail and the note for its evidence, or null when it is not a surprise.
     *
     * @return array{detail: string, note: string}|null
     */
    public static function of(DriverEntry $entry, SessionData $data): ?array
    {
        $topTeams = (int) config('achievements.surprise.top_teams');
        $form = TeamForm::before($data->session->round);

        if ($form->isEmpty()) {
            return null;
        }

        $races = $form->races->count();
        $window = $races === 1 ? 'race' : "{$races} races";
        $count = $races === 1 ? '1 race' : "{$races} races";
        $teamId = $entry->seasonTeam->team_id;
        $rank = $form->rankOf($teamId);

        if ($rank !== null && $rank <= $topTeams) {
            return null;
        }

        $team = $form->teams->firstWhere('team_id', $teamId);
        $standing = $team === null
            ? 'no race in the window'
            : "P{$rank} with ".TeamForm::figure($team['per_race']).' points per race';

        return [
            'detail' => $rank === null ? "No race for the team in the last {$window}" : "Team P{$rank} on form over the last {$window}",
            'note' => "Team form over {$form->windowLabel()} ({$count}). Top {$topTeams} on points per race: {$form->summary($topTeams)}. {$entry->seasonTeam->name}: {$standing}.",
        ];
    }
}
