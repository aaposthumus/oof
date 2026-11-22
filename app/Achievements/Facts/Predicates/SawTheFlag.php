<?php

namespace App\Achievements\Facts\Predicates;

use App\Achievements\Facts\Concerns\ReadsResults;
use App\Achievements\Facts\Fact;
use App\Achievements\Facts\Predicate;
use App\Achievements\SessionData;
use App\Achievements\Support\Finish;
use App\Excavation\Support\RaceStatuses;

/**
 * Running at the flag (finished, or classified laps down) and not
 * disqualified: no retirement, even one classified at 90% distance, no
 * non-start, no disqualification (Like a Metronome).
 */
class SawTheFlag implements Predicate
{
    use ReadsResults;

    public function test(Fact $fact, SessionData $data): bool
    {
        $result = $data->entry($fact->entryId)?->result;

        return $result !== null && RaceStatuses::isRunning($result->status) && ! Finish::isDisqualified($result);
    }
}
