<?php

namespace App\Achievements\Detectors;

use App\Achievements\SessionData;
use App\Achievements\Support\Finish;
use App\Models\DriverEntry;

/**
 * Disqualified (DSQ), from Jolpica's final results. The reason is often only
 * in the stewards' documents, so it is added as a subtitle in the CMS.
 */
class SentToYourRoomDetector extends ResultDetector
{
    public function key(): string
    {
        return 'sent_to_your_room';
    }

    protected function eventKey(DriverEntry $entry): string
    {
        return "disqualification:{$entry->id}";
    }

    protected function judge(DriverEntry $entry, SessionData $data): string|false|null
    {
        return Finish::isDisqualified($entry->result) ? null : false;
    }
}
