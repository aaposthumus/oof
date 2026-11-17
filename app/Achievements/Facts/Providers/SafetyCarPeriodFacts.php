<?php

namespace App\Achievements\Facts\Providers;

use App\Achievements\Facts\Concerns\ReadsRaceControl;
use App\Achievements\Facts\Fact;
use App\Achievements\Facts\FactEvidence;
use App\Achievements\Facts\FactProvider;
use App\Achievements\SessionData;
use App\Achievements\Support\SafetyCarPeriods;
use Illuminate\Support\Collection;

/**
 * A safety car period, from its deployment to the lap it comes in (or a
 * red flag or the chequered flag). Concerns no car: the Safety Car itself.
 *
 * Attributes: laps (0 when a lap number is missing).
 */
class SafetyCarPeriodFacts implements FactProvider
{
    use ReadsRaceControl;

    public function facts(SessionData $data): Collection
    {
        return SafetyCarPeriods::of($data)
            ->map(fn (array $period) => new Fact(
                type: 'safety_car_period',
                entryId: null,
                instanceKey: "rcm:{$period['deployed']->message->id}",
                eventKey: 'safety_car',
                attributes: ['laps' => $period['laps']],
                evidence: FactEvidence::messages(array_filter([$period['deployed'], $period['ended']])),
            ))
            ->values();
    }
}
