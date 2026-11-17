<?php

namespace App\Achievements\Facts\Predicates;

use App\Achievements\Facts\Concerns\ReadsResults;
use App\Achievements\Facts\Fact;
use App\Achievements\Facts\Predicate;
use App\Achievements\SessionData;
use App\Achievements\Support\Finish;

/**
 * Not disqualified.
 */
class NotDisqualified implements Predicate
{
    use ReadsResults;

    public function test(Fact $fact, SessionData $data): bool
    {
        return ! Finish::isDisqualified($data->entry($fact->entryId)->result);
    }
}
