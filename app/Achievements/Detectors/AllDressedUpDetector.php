<?php

namespace App\Achievements\Detectors;

use App\Achievements\SessionData;
use App\Achievements\Support\Finish;
use App\Models\DriverEntry;

/**
 * Fastest lap, but finished outside the points.
 */
class AllDressedUpDetector extends ResultDetector
{
    public function key(): string
    {
        return 'all_dressed_up_nowhere_to_go';
    }

    protected function judge(DriverEntry $entry, SessionData $data): string|false|null
    {
        $result = $entry->result;

        return $result->fastest_lap_rank === 1 && (float) $result->points === 0.0 && ! Finish::isDisqualified($result) ? "P{$result->position_text}" : false;
    }
}
