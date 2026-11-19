<?php

namespace App\Achievements\Facts\Predicates;

use App\Achievements\Facts\DataSource;
use App\Achievements\Facts\Fact;
use App\Achievements\Facts\Predicate;
use App\Achievements\SessionData;
use App\Achievements\Support\LapsLed;

/**
 * The car was P1 at the end of every lap it completed: from Jolpica's lap
 * times (from 1996), or from OpenF1's positions and laps until those are in
 * (see LapsLed). Without either the race cannot be judged, so it does not
 * hold.
 */
class LedEveryLap implements Predicate
{
    public function sources(): array
    {
        return [DataSource::JolpicaLaps, DataSource::OpenF1Positions, DataSource::OpenF1Laps];
    }

    public function availableFrom(): int
    {
        return 1996;
    }

    public function notes(): array
    {
        return ['Jolpica lap times from 1996; OpenF1 stands in until a race\'s Jolpica laps are in, and the award waits for them.'];
    }

    public function test(Fact $fact, SessionData $data): bool
    {
        $entry = $data->entry($fact->entryId);

        return $entry !== null && LapsLed::ledEveryLap($data, $entry);
    }
}
