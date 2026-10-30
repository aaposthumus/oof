<?php

namespace App\Achievements\Detectors;

use App\Achievements\SessionData;
use App\Achievements\Support\Finish;
use App\Models\DriverEntry;

/**
 * Lost the most places from the grid to the flag, among classified
 * finishers. Pit lane starts (grid 0) are left out.
 */
class ReverseGearDetector extends ResultDetector
{
    public function key(): string
    {
        return 'reverse_gear';
    }

    protected function judge(DriverEntry $entry, SessionData $data): string|false|null
    {
        $lost = fn (DriverEntry $other) => Finish::isClassifiedFinisher($other->result) && $other->result->grid > 0
            ? $other->result->position - $other->result->grid
            : null;
        $most = $data->resultEntries()->map($lost)->filter()->max();

        return $most > 0 && $lost($entry) === $most ? "P{$entry->result->grid} → P{$entry->result->position}" : false;
    }
}
