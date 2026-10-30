<?php

namespace App\Achievements\Detectors;

use App\Achievements\SessionData;
use App\Achievements\Support\Finish;
use App\Models\DriverEntry;

/**
 * Won from the threshold grid slot or lower, or from the pit lane.
 */
class TookTheScenicRouteDetector extends ResultDetector
{
    public function key(): string
    {
        return 'took_the_scenic_route';
    }

    protected function judge(DriverEntry $entry, SessionData $data): string|false|null
    {
        $result = $entry->result;
        $fromBehind = $result->grid === 0 || $result->grid >= config('achievements.scenic_route_grid');

        return $result->position === 1 && ! Finish::isDisqualified($result) && $fromBehind ? ($result->grid === 0 ? 'from the pit lane' : "from P{$result->grid}") : false;
    }
}
