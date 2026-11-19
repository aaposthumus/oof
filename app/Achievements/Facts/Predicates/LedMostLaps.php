<?php

namespace App\Achievements\Facts\Predicates;

use App\Achievements\Facts\DataSource;
use App\Achievements\Facts\Fact;
use App\Achievements\Facts\Predicate;
use App\Achievements\SessionData;
use App\Achievements\Support\LapsLed;

/**
 * The car led the most laps of the session (see LapsLed), at least one. Cars
 * level on the most share it. Reads the fact's laps_led attribute.
 */
class LedMostLaps implements Predicate
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
        return ['Laps led from Jolpica lap times (from 1996); OpenF1 stands in until a race\'s Jolpica laps are in, and the award waits for them.'];
    }

    public function test(Fact $fact, SessionData $data): bool
    {
        $most = max([0, ...array_values(LapsLed::of($data))]);

        return $most > 0 && $fact->get('laps_led') === $most;
    }
}
