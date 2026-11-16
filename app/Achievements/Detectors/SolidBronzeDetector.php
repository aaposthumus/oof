<?php

namespace App\Achievements\Detectors;

use App\Achievements\SessionData;
use App\Achievements\Support\Finish;
use App\Models\DriverEntry;

/**
 * Third place.
 */
class SolidBronzeDetector extends ResultDetector
{
    public function key(): string
    {
        return 'solid_bronze';
    }

    protected function judge(DriverEntry $entry, SessionData $data): string|false|null
    {
        return $entry->result->position === 3 && ! Finish::isDisqualified($entry->result) ? null : false;
    }
}
