<?php

namespace App\Achievements\Detectors;

use App\Achievements\SessionData;
use App\Achievements\Support\Finish;
use App\Models\DriverEntry;

/**
 * Retired from the race (DNF).
 */
class HomeBeforeDinnerDetector extends ResultDetector
{
    public function key(): string
    {
        return 'home_before_dinner';
    }

    protected function judge(DriverEntry $entry, SessionData $data): string|false|null
    {
        return Finish::retired($entry->result) ? $entry->result->status : false;
    }
}
