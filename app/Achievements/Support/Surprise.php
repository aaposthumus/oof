<?php

namespace App\Achievements\Support;

use App\Achievements\SessionData;
use App\Models\DriverEntry;
use App\Models\Round;

/**
 * Whether a win or pole is a surprise: the lasting team stands outside the
 * top of the rolling team form before the round (see TeamForm). A team with
 * no race in the window (a debut, or a long absence) has no form, so its
 * result is no surprise: unknown is not an outsider. Before the very first
 * race the window is empty and nothing is a surprise, and the Indianapolis 500
 * rounds of 1950 to 1960 are never one. Only the team decides; the driver's
 * own history does not. The surprise-winner excavator reads the same rule.
 */
class Surprise
{
    /**
     * The team form and the team's rank in it when the team's result in this
     * round is a surprise; null when it is not, or when the team has no race
     * in the window.
     *
     * @return array{form: TeamForm, rank: int}|null
     */
    public static function forTeam(int $teamId, Round $round, ?TeamForm $form = null): ?array
    {
        if (TeamForm::isIndianapolis500($round)) {
            return null;
        }

        $form ??= TeamForm::before($round);

        if ($form->isEmpty()) {
            return null;
        }

        $rank = $form->rankOf($teamId);

        if ($rank === null || $rank <= (int) config('achievements.surprise.top_teams')) {
            return null;
        }

        return ['form' => $form, 'rank' => $rank];
    }

    /**
     * The award's detail and the note for its evidence, or null when it is not a surprise.
     *
     * @return array{detail: string, note: string}|null
     */
    public static function of(DriverEntry $entry, SessionData $data): ?array
    {
        $teamId = $entry->seasonTeam->team_id;
        $surprise = self::forTeam($teamId, $data->session->round);

        if ($surprise === null) {
            return null;
        }

        ['form' => $form, 'rank' => $rank] = $surprise;
        $topTeams = (int) config('achievements.surprise.top_teams');
        $races = $form->races->count();
        $window = $races === 1 ? 'race' : "{$races} races";
        $count = $races === 1 ? '1 race' : "{$races} races";
        $team = $form->teams->firstWhere('team_id', $teamId);
        $standing = "P{$rank} with ".TeamForm::figure($team['per_race']).' points per race';

        return [
            'detail' => "Team P{$rank} on form over the last {$window}",
            'note' => "Team form over {$form->windowLabel()} ({$count}). Top {$topTeams} on points per race: {$form->summary($topTeams)}. {$entry->seasonTeam->name}: {$standing}.",
        ];
    }
}
