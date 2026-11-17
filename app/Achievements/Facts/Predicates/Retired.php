<?php

namespace App\Achievements\Facts\Predicates;

use App\Achievements\Facts\Concerns\ReadsResults;
use App\Achievements\Facts\Fact;
use App\Achievements\Facts\Predicate;
use App\Achievements\SessionData;
use App\Achievements\Support\Finish;

/**
 * Out of the race before the flag, for any reason but a disqualification, a non-start, or injury and illness.
 */
class Retired implements Predicate
{
    use ReadsResults;

    public function test(Fact $fact, SessionData $data): bool
    {
        return Finish::retired($data->entry($fact->entryId)->result);
    }
}
