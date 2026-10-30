<?php

namespace App\Achievements\Detectors;

use App\Achievements\SessionData;
use App\Achievements\Support\Finish;
use App\Models\DriverEntry;

/**
 * On the podium.
 */
class StandingRoomOnlyDetector extends ResultDetector
{
    public function key(): string
    {
        return 'standing_room_only';
    }

    protected function judge(DriverEntry $entry, SessionData $data): string|false|null
    {
        return in_array($entry->result->position, [1, 2, 3], true) && ! Finish::isDisqualified($entry->result) ? "P{$entry->result->position}" : false;
    }
}
