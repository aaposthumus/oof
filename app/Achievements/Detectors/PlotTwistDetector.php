<?php

namespace App\Achievements\Detectors;

use App\Achievements\Detection;
use App\Achievements\Detector;
use App\Achievements\SessionData;
use App\Achievements\Support\Evidence;
use App\Achievements\Support\Surprise;
use App\Models\DriverEntry;
use Illuminate\Support\Collection;

/**
 * A surprise pole: P1 in qualifying (see Surprise for the rule).
 */
class PlotTwistDetector implements Detector
{
    public function key(): string
    {
        return 'plot_twist';
    }

    public function detect(SessionData $data): Collection
    {
        return $data->entries
            ->filter(fn (DriverEntry $entry) => $entry->qualifyingResult?->position === 1)
            ->map(fn (DriverEntry $entry) => [$entry, Surprise::reason($entry, $data)])
            ->filter(fn (array $pole) => $pole[1] !== null)
            ->map(fn (array $pole) => new Detection(
                achievementKey: $this->key(),
                driverEntryId: $pole[0]->id,
                instanceKey: 'pole',
                eventKey: "pole:{$pole[0]->id}",
                evidence: Evidence::results([$pole[0]], $pole[1]),
                detail: $pole[1],
            ))
            ->values();
    }
}
