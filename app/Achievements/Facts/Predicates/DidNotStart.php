<?php

namespace App\Achievements\Facts\Predicates;

use App\Achievements\Facts\Concerns\ReadsResults;
use App\Achievements\Facts\Fact;
use App\Achievements\Facts\Predicate;
use App\Achievements\SessionData;
use App\Achievements\Support\Finish;
use App\Excavation\Support\RaceStatuses;

/**
 * Did not start, unless through injury or illness.
 */
class DidNotStart implements Predicate
{
    use ReadsResults;

    public function notes(): array
    {
        return ['From 2025 the retirement reason is only "Retired", so illness and injury cannot be filtered automatically.'];
    }

    public function test(Fact $fact, SessionData $data): bool
    {
        $result = $data->entry($fact->entryId)->result;

        return Finish::didNotStart($result) && ! RaceStatuses::isSkipped($result->status);
    }
}
