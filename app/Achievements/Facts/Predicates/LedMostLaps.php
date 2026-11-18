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
        return [DataSource::OpenF1Positions, DataSource::OpenF1Laps];
    }

    public function availableFrom(): int
    {
        return 2023;
    }

    public function notes(): array
    {
        return ['Laps led are read from OpenF1 positions; before 2023 it needs the Jolpica lap-time import (from 1996).'];
    }

    public function test(Fact $fact, SessionData $data): bool
    {
        $most = max([0, ...array_values(LapsLed::of($data))]);

        return $most > 0 && $fact->get('laps_led') === $most;
    }
}
