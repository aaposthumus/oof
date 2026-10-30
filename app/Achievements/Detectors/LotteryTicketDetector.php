<?php

namespace App\Achievements\Detectors;

use App\Achievements\SessionData;
use App\Achievements\Support\Finish;
use App\Achievements\Support\Surprise;
use App\Models\DriverEntry;

/**
 * A surprise win (see Surprise for the rule).
 */
class LotteryTicketDetector extends ResultDetector
{
    public function key(): string
    {
        return 'lottery_ticket';
    }

    protected function judge(DriverEntry $entry, SessionData $data): string|false|null
    {
        if ($entry->result->position !== 1 || Finish::isDisqualified($entry->result)) {
            return false;
        }

        return Surprise::reason($entry, $data) ?? false;
    }
}
