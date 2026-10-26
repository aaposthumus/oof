<?php

namespace App\Normalizers\Jolpica;

use App\Models\PitStop;
use App\Normalizers\Jolpica\Support\CoreResolver;
use App\Normalizers\Jolpica\Support\TimeParser;
use App\Normalizers\Normalizer;

class PitStopsNormalizer implements Normalizer
{
    use RaceEntryLookup;

    public function __construct(private CoreResolver $core) {}

    public function endpoint(): string
    {
        return 'pitstops';
    }

    protected function core(): CoreResolver
    {
        return $this->core;
    }

    public function normalize(array $payload): void
    {
        foreach ($payload['MRData']['RaceTable']['Races'] as $race) {
            $entryIdFor = $this->raceEntryResolver($race);

            foreach ($race['PitStops'] as $row) {
                if (($entryId = $entryIdFor($row['driverId'])) === null) {
                    continue;
                }

                PitStop::updateOrCreate(['driver_entry_id' => $entryId, 'stop' => (int) $row['stop']], [
                    'lap' => (int) $row['lap'],
                    'time_of_day' => $row['time'] ?? null,
                    'duration_millis' => TimeParser::toMillis($row['duration'] ?? null),
                ]);
            }
        }
    }
}
