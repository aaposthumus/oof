<?php

namespace App\Achievements\Facts\Predicates;

use App\Achievements\Facts\Concerns\ReadsResults;
use App\Achievements\Facts\Fact;
use App\Achievements\Facts\Predicate;
use App\Achievements\SessionData;
use App\Achievements\Support\Finish;
use App\Models\DriverEntry;

/**
 * The last classified finisher.
 */
class LastClassifiedFinisher implements Predicate
{
    use ReadsResults;

    public function test(Fact $fact, SessionData $data): bool
    {
        $last = $data->resultEntries()
            ->filter(fn (DriverEntry $other) => Finish::isClassifiedFinisher($other->result))
            ->max(fn (DriverEntry $other) => $other->result->position);
        $result = $data->entry($fact->entryId)->result;

        return Finish::isClassifiedFinisher($result) && $result->position === $last;
    }
}
