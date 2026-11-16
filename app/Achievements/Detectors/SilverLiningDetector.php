<?php

namespace App\Achievements\Detectors;

use App\Achievements\SessionData;
use App\Achievements\Support\Finish;
use App\Models\DriverEntry;

/**
 * Second place.
 */
class SilverLiningDetector extends ResultDetector
{
    public function key(): string
    {
        return 'silver_lining';
    }

    protected function judge(DriverEntry $entry, SessionData $data): string|false|null
    {
        return $entry->result->position === 2 && ! Finish::isDisqualified($entry->result) ? null : false;
    }
}
