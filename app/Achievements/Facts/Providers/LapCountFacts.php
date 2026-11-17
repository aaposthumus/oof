<?php

namespace App\Achievements\Facts\Providers;

use App\Achievements\Facts\DataSource;
use App\Achievements\Facts\Fact;
use App\Achievements\Facts\FactEvidence;
use App\Achievements\Facts\FactProvider;
use App\Achievements\SessionData;
use App\Models\SessionLap;
use Illuminate\Support\Collection;

/**
 * The laps a car drove in the session, one fact per car.
 *
 * Attributes: laps.
 */
class LapCountFacts implements FactProvider
{
    public function sources(): array
    {
        return [DataSource::OpenF1Laps];
    }

    public function availableFrom(): int
    {
        return 2023;
    }

    public function notes(): array
    {
        return [];
    }

    public function facts(SessionData $data): Collection
    {
        return $data->laps()
            ->filter(fn (SessionLap $lap) => $lap->driver_entry_id !== null)
            ->countBy('driver_entry_id')
            ->map(fn (int $laps, int $entryId) => new Fact(
                type: 'lap_count',
                entryId: $entryId,
                instanceKey: 'session',
                eventKey: "session:{$entryId}",
                attributes: ['laps' => $laps],
                evidence: FactEvidence::rows('openf1', [['driver' => $data->entry($entryId)?->driver->family_name, 'count' => $laps]]),
            ))
            ->values();
    }
}
