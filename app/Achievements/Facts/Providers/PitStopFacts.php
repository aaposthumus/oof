<?php

namespace App\Achievements\Facts\Providers;

use App\Achievements\Facts\DataSource;
use App\Achievements\Facts\Fact;
use App\Achievements\Facts\FactEvidence;
use App\Achievements\Facts\FactProvider;
use App\Achievements\SessionData;
use App\Achievements\Support\PitStops;
use App\Models\PitLaneVisit;
use Illuminate\Support\Collection;

/**
 * A pit stop with OpenF1's measured stationary time; there is no estimate.
 * Stops serving a penalty and stops around a red flag are left out (see
 * PitStops).
 *
 * Attributes: stop_millis, stop_time ("5.3 s").
 */
class PitStopFacts implements FactProvider
{
    public function sources(): array
    {
        return [DataSource::OpenF1Pit, DataSource::OpenF1RaceControl];
    }

    public function availableFrom(): int
    {
        return 2023;
    }

    public function notes(): array
    {
        return [
            'Stationary time is filled for only a small share of visits.',
            '"Penalty served" exists in race control only from 2025.',
        ];
    }

    public function facts(SessionData $data): Collection
    {
        return PitStops::countable($data)
            ->map(fn (PitLaneVisit $visit) => new Fact(
                type: 'pit_stop',
                entryId: $visit->driver_entry_id,
                instanceKey: "pit:{$visit->id}",
                eventKey: "pit:{$visit->id}",
                attributes: [
                    'stop_millis' => $visit->stop_duration_millis,
                    'stop_time' => PitStops::seconds($visit->stop_duration_millis),
                ],
                evidence: FactEvidence::rows('openf1', [PitStops::row($visit)]),
            ))
            ->values();
    }
}
