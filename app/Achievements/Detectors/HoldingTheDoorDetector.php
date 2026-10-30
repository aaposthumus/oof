<?php

namespace App\Achievements\Detectors;

use App\Achievements\Detection;
use App\Achievements\Detector;
use App\Achievements\SessionData;
use App\Achievements\Support\Evidence;
use App\Achievements\Support\QualifyingPhases;
use App\Models\DriverEntry;
use Illuminate\Support\Collection;

/**
 * The slowest time in Q1. Unlocks every weekend, unless that time is shared.
 */
class HoldingTheDoorDetector implements Detector
{
    public function key(): string
    {
        return 'holding_the_door';
    }

    public function detect(SessionData $data): Collection
    {
        $timed = $data->entries->filter(fn (DriverEntry $entry) => $entry->qualifyingResult?->q1_millis !== null);
        $slowest = $timed->max(fn (DriverEntry $entry) => $entry->qualifyingResult->q1_millis);

        $holders = $timed->filter(fn (DriverEntry $entry) => $entry->qualifyingResult->q1_millis === $slowest);

        return $holders->count() !== 1 ? collect() : $holders
            ->map(fn (DriverEntry $entry) => new Detection(
                achievementKey: $this->key(),
                driverEntryId: $entry->id,
                instanceKey: 'q1',
                eventKey: "knocked_out:{$entry->id}",
                evidence: Evidence::results([$entry]),
                detail: QualifyingPhases::seconds($slowest),
            ))
            ->values();
    }
}
