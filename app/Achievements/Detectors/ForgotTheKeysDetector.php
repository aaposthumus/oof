<?php

namespace App\Achievements\Detectors;

use App\Achievements\SessionData;
use App\Achievements\Support\Finish;
use App\Excavation\Support\RaceStatuses;
use App\Models\DriverEntry;

/**
 * Did not start. Injury and illness statuses are skipped; other unfunny
 * reasons are suppressed in the CMS.
 */
class ForgotTheKeysDetector extends ResultDetector
{
    public function key(): string
    {
        return 'forgot_the_keys';
    }

    protected function judge(DriverEntry $entry, SessionData $data): string|false|null
    {
        return Finish::didNotStart($entry->result) && ! RaceStatuses::isSkipped($entry->result->status) ? $entry->result->status : false;
    }
}
