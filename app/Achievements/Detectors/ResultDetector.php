<?php

namespace App\Achievements\Detectors;

use App\Achievements\Detection;
use App\Achievements\Detector;
use App\Achievements\SessionData;
use App\Achievements\Support\Evidence;
use App\Models\DriverEntry;
use Illuminate\Support\Collection;

/**
 * An achievement read from one car's Jolpica result row. All of a car's
 * result achievements share one event, so the more specific one wins.
 */
abstract class ResultDetector implements Detector
{
    /**
     * The award's detail when the result earns it, false when it does not.
     */
    abstract protected function judge(DriverEntry $entry, SessionData $data): string|false|null;

    protected function eventKey(DriverEntry $entry): string
    {
        return "result:{$entry->id}";
    }

    public function detect(SessionData $data): Collection
    {
        return $data->resultEntries()
            ->map(fn (DriverEntry $entry) => [$entry, $this->judge($entry, $data)])
            ->reject(fn (array $judged) => $judged[1] === false)
            ->map(fn (array $judged) => new Detection(
                achievementKey: $this->key(),
                driverEntryId: $judged[0]->id,
                instanceKey: 'result',
                eventKey: $this->eventKey($judged[0]),
                evidence: Evidence::results([$judged[0]]),
                detail: $judged[1],
            ))
            ->values();
    }
}
