<?php

namespace App\Achievements\Detectors;

use App\Achievements\SessionData;
use App\Achievements\Support\Finish;
use App\Models\DriverEntry;

/**
 * Won.
 */
class DidTheThingDetector extends ResultDetector
{
    public function key(): string
    {
        return 'did_the_thing';
    }

    protected function judge(DriverEntry $entry, SessionData $data): string|false|null
    {
        return $entry->result->position === 1 && ! Finish::isDisqualified($entry->result) ? null : false;
    }
}
