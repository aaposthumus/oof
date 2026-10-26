<?php

namespace App\Normalizers\Jolpica;

use App\Enums\SessionType;
use App\Models\QualifyingResult;
use App\Normalizers\Jolpica\Support\CoreResolver;
use App\Normalizers\Jolpica\Support\TimeParser;
use App\Normalizers\Normalizer;

class QualifyingNormalizer implements Normalizer
{
    public function __construct(private CoreResolver $core) {}

    public function endpoint(): string
    {
        return 'qualifying';
    }

    public function normalize(array $payload): void
    {
        foreach ($payload['MRData']['RaceTable']['Races'] as $race) {
            $round = $this->core->round($race);
            $season = $this->core->season((int) $race['season']);
            $session = $this->core->raceSession($round, SessionType::Qualifying);

            foreach ($race['QualifyingResults'] as $row) {
                $entry = $this->core->driverEntry(
                    $session,
                    $this->core->driver($row['Driver']),
                    $this->core->seasonTeam($season, $row['Constructor']),
                    isset($row['number']) ? (int) $row['number'] : null,
                );

                QualifyingResult::updateOrCreate(['driver_entry_id' => $entry->id], [
                    'position' => isset($row['position']) ? (int) $row['position'] : null,
                    'q1_millis' => TimeParser::toMillis($row['Q1'] ?? null),
                    'q2_millis' => TimeParser::toMillis($row['Q2'] ?? null),
                    'q3_millis' => TimeParser::toMillis($row['Q3'] ?? null),
                ]);
            }
        }
    }
}
