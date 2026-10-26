<?php

namespace App\Normalizers\Jolpica;

use App\Enums\SessionType;
use App\Normalizers\Jolpica\Support\CoreResolver;
use App\Normalizers\MissingDependencyException;
use Illuminate\Support\Facades\Log;

/**
 * Shared by pit stops and laps, which only name a driverId: finds that
 * driver's entry in the race the payload is about.
 */
trait RaceEntryLookup
{
    abstract protected function core(): CoreResolver;

    /**
     * @param  array<string, mixed>  $race  A Jolpica Race object
     * @return callable(string): ?int Resolves a Jolpica driver ID to a driver entry ID
     */
    protected function raceEntryResolver(array $race): callable
    {
        $round = $this->core()->round($race);
        $entryIds = $this->core()->entryIdsByJolpicaDriver($this->core()->raceSession($round, SessionType::Race));

        if ($entryIds === []) {
            throw new MissingDependencyException("Race results for {$race['season']}-{$race['round']} are not normalized yet.");
        }

        return function (string $driverId) use ($entryIds, $race): ?int {
            $entryId = $entryIds[$driverId] ?? null;

            if ($entryId === null) {
                Log::warning('Skipping row without a single race entry for its driver.', [
                    'season' => $race['season'], 'round' => $race['round'], 'driverId' => $driverId,
                ]);
            }

            return $entryId;
        };
    }
}
