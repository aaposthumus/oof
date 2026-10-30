<?php

namespace App\Achievements\Detectors;

use App\Achievements\SessionData;
use App\Achievements\Support\Finish;
use App\Models\DriverEntry;

/**
 * Retired on lap 1, without completing a lap.
 */
class EarlyLunchDetector extends ResultDetector
{
    public function key(): string
    {
        return 'early_lunch';
    }

    protected function judge(DriverEntry $entry, SessionData $data): string|false|null
    {
        return Finish::retired($entry->result) && (int) $entry->result->laps_completed === 0 ? $entry->result->status : false;
    }
}
