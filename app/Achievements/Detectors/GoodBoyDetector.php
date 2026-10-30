<?php

namespace App\Achievements\Detectors;

use App\Achievements\SessionData;
use App\Achievements\Support\Finish;
use App\Models\DriverEntry;

/**
 * Lapped twice or more.
 */
class GoodBoyDetector extends ResultDetector
{
    public function key(): string
    {
        return 'good_boy';
    }

    protected function judge(DriverEntry $entry, SessionData $data): string|false|null
    {
        $lapsDown = Finish::lapsDown($entry, $data);

        return $lapsDown >= 2 ? "{$lapsDown} laps" : false;
    }
}
