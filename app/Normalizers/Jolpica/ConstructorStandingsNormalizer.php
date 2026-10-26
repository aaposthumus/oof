<?php

namespace App\Normalizers\Jolpica;

use App\Models\ConstructorStanding;
use App\Normalizers\Jolpica\Support\CoreResolver;
use App\Normalizers\Normalizer;

class ConstructorStandingsNormalizer implements Normalizer
{
    public function __construct(private CoreResolver $core) {}

    public function endpoint(): string
    {
        return 'constructorstandings';
    }

    public function normalize(array $payload): void
    {
        foreach ($payload['MRData']['StandingsTable']['StandingsLists'] as $list) {
            $round = $this->core->existingRound((int) $list['season'], (int) $list['round']);
            $season = $this->core->season((int) $list['season']);

            foreach ($list['ConstructorStandings'] as $row) {
                ConstructorStanding::updateOrCreate(
                    ['round_id' => $round->id, 'season_team_id' => $this->core->seasonTeam($season, $row['Constructor'])->id],
                    [
                        'position' => isset($row['position']) ? (int) $row['position'] : null,
                        'position_text' => $row['positionText'],
                        'points' => (float) $row['points'],
                        'wins' => (int) $row['wins'],
                    ],
                );
            }
        }
    }
}
