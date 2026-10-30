<?php

namespace App\Achievements\Detectors;

use App\Achievements\SessionData;
use App\Achievements\Support\Finish;
use App\Models\DriverEntry;

/**
 * Lapped by the leader: finished one lap down.
 */
class LappedDogDetector extends ResultDetector
{
    public function key(): string
    {
        return 'lapped_dog';
    }

    protected function judge(DriverEntry $entry, SessionData $data): string|false|null
    {
        return Finish::lapsDown($entry, $data) === 1 ? '1 lap' : false;
    }
}
