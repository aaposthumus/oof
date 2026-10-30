<?php

namespace App\Achievements\Detectors;

use App\Achievements\Detection;
use App\Achievements\Detector;
use App\Achievements\SessionData;
use App\Achievements\Support\Evidence;
use App\Achievements\Support\PitStops;
use App\Models\PitLaneVisit;
use Illuminate\Support\Collection;

/**
 * A pit stop longer than the threshold standing still. Only stops with
 * OpenF1's measured stationary time count; there is no estimate.
 */
class TableForOneDetector implements Detector
{
    public function key(): string
    {
        return 'table_for_one';
    }

    public function detect(SessionData $data): Collection
    {
        return PitStops::countable($data)
            ->filter(fn (PitLaneVisit $visit) => $visit->stop_duration_millis > config('achievements.pit_stop_millis'))
            ->map(fn (PitLaneVisit $visit) => new Detection(
                achievementKey: $this->key(),
                driverEntryId: $visit->driver_entry_id,
                instanceKey: "pit:{$visit->id}",
                eventKey: "pit:{$visit->id}",
                evidence: Evidence::rows('openf1', [PitStops::row($visit)]),
                detail: PitStops::seconds($visit->stop_duration_millis),
            ))
            ->values();
    }
}
