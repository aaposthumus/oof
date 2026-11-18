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
 * A car's highest speed-trap reading of the session (OpenF1 laps), one fact
 * per car that has one. The earliest lap with that reading is the one shown.
 *
 * Attributes: top_speed (km/h), lap.
 */
class SpeedTrapFacts implements FactProvider
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
        return ['Readings are whole km/h, so the top speed is often shared.'];
    }

    public function facts(SessionData $data): Collection
    {
        return $data->laps()
            ->filter(fn (SessionLap $lap) => $lap->driver_entry_id !== null && $lap->st_speed !== null)
            ->groupBy('driver_entry_id')
            ->map(function (Collection $laps, int $entryId) use ($data) {
                $fastest = $laps->sortBy([['st_speed', 'desc'], ['lap_number', 'asc']])->first();

                return new Fact(
                    type: 'speed_trap',
                    entryId: $entryId,
                    instanceKey: 'session',
                    eventKey: "speed_trap:{$entryId}",
                    attributes: ['top_speed' => $fastest->st_speed, 'lap' => $fastest->lap_number],
                    evidence: FactEvidence::rows('openf1', [['driver' => $data->entry($entryId)?->driver->family_name, 'lap' => $fastest->lap_number, 'speed' => $fastest->st_speed]]),
                );
            })
            ->values();
    }
}
