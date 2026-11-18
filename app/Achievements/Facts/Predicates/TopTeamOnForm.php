<?php

namespace App\Achievements\Facts\Predicates;

use App\Achievements\Facts\DataSource;
use App\Achievements\Facts\Fact;
use App\Achievements\Facts\Predicate;
use App\Achievements\Facts\ReadsEarlierRaces;
use App\Achievements\SessionData;

/**
 * The car's lasting team stood in the top teams on form before the round
 * (achievements.surprise.top_teams, the same rolling look-back as the
 * surprise rule; see TeamForm): the opposite of a surprise. A team with no
 * race in the window is not a top team. Reads the fact's team_form_rank.
 */
class TopTeamOnForm implements Predicate, ReadsEarlierRaces
{
    public function sources(): array
    {
        return [DataSource::JolpicaResults];
    }

    public function availableFrom(): int
    {
        return 1950;
    }

    public function notes(): array
    {
        return ['Team form: points per race over the last 10 Grands Prix before the round, per lasting team (team lineage).'];
    }

    public function test(Fact $fact, SessionData $data): bool
    {
        $rank = $fact->get('team_form_rank');

        return $rank !== null && $rank <= (int) config('achievements.surprise.top_teams');
    }
}
