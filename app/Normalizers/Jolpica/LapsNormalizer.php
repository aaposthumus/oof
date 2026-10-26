<?php

namespace App\Normalizers\Jolpica;

use App\Models\LapTime;
use App\Normalizers\Jolpica\Support\CoreResolver;
use App\Normalizers\Jolpica\Support\TimeParser;
use App\Normalizers\Normalizer;

class LapsNormalizer implements Normalizer
{
    use RaceEntryLookup;

    public function __construct(private CoreResolver $core) {}

    public function endpoint(): string
    {
        return 'laps';
    }

    protected function core(): CoreResolver
    {
        return $this->core;
    }

    public function normalize(array $payload): void
    {
        foreach ($payload['MRData']['RaceTable']['Races'] as $race) {
            $entryIdFor = $this->raceEntryResolver($race);
            $rows = [];

            foreach ($race['Laps'] as $lap) {
                foreach ($lap['Timings'] as $timing) {
                    if (($entryId = $entryIdFor($timing['driverId'])) === null) {
                        continue;
                    }

                    $rows[] = [
                        'driver_entry_id' => $entryId,
                        'lap' => (int) $lap['number'],
                        'position' => isset($timing['position']) ? (int) $timing['position'] : null,
                        'time_millis' => TimeParser::toMillis($timing['time'] ?? null),
                    ];
                }
            }

            // Laps are by far the largest table, so they are written in bulk.
            LapTime::upsert($rows, ['driver_entry_id', 'lap'], ['position', 'time_millis']);
        }
    }
}
