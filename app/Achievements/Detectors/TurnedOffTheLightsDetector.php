<?php

namespace App\Achievements\Detectors;

use App\Achievements\SessionData;
use App\Achievements\Support\Finish;
use App\Models\DriverEntry;

/**
 * The last classified finisher.
 */
class TurnedOffTheLightsDetector extends ResultDetector
{
    public function key(): string
    {
        return 'turned_off_the_lights';
    }

    protected function judge(DriverEntry $entry, SessionData $data): string|false|null
    {
        $last = $data->resultEntries()
            ->filter(fn (DriverEntry $other) => Finish::isClassifiedFinisher($other->result))
            ->max(fn (DriverEntry $other) => $other->result->position);

        return Finish::isClassifiedFinisher($entry->result) && $entry->result->position === $last ? "P{$last}" : false;
    }
}
