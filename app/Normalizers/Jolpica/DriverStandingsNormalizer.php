<?php

namespace App\Normalizers\Jolpica;

use App\Models\DriverStanding;
use App\Normalizers\Jolpica\Support\CoreResolver;
use App\Normalizers\Normalizer;

class DriverStandingsNormalizer implements Normalizer
{
    public function __construct(private CoreResolver $core) {}

    public function endpoint(): string
    {
        return 'driverstandings';
    }

    public function normalize(array $payload): void
    {
        foreach ($payload['MRData']['StandingsTable']['StandingsLists'] as $list) {
            $round = $this->core->existingRound((int) $list['season'], (int) $list['round']);

            foreach ($list['DriverStandings'] as $row) {
                DriverStanding::updateOrCreate(
                    ['round_id' => $round->id, 'driver_id' => $this->core->driver($row['Driver'])->id],
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
