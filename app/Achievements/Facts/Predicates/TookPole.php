<?php

namespace App\Achievements\Facts\Predicates;

use App\Achievements\Facts\DataSource;
use App\Achievements\Facts\Fact;
use App\Achievements\Facts\Predicate;
use App\Achievements\SessionData;
use App\Achievements\Support\Poles;

/**
 * The driver took the round's pole (see Poles, the one definition of pole):
 * P1 in qualifying, or without qualifying data the official grid pole. It
 * names the driver, so it holds on every car they drove that day.
 */
class TookPole implements Predicate
{
    public function sources(): array
    {
        return [DataSource::JolpicaResults, DataSource::JolpicaQualifying];
    }

    public function availableFrom(): int
    {
        return 1950;
    }

    public function notes(): array
    {
        return ['Pole from qualifying where present, otherwise the official grid pole (config/official_poles.php settles shared cars).'];
    }

    public function test(Fact $fact, SessionData $data): bool
    {
        $driverId = $data->entry($fact->entryId)?->driver_id;

        return $driverId !== null && Poles::driverOf($data) === $driverId;
    }
}
