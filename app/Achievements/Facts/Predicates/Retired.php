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

    public function notes(): array
    {
        return ['From 2025 the retirement reason is only "Retired", so illness and injury cannot be filtered automatically.'];
    }

    public function test(Fact $fact, SessionData $data): bool
    {
        return Finish::retired($data->entry($fact->entryId)->result);
    }
}
