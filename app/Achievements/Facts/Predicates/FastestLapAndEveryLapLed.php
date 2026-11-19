<?php

namespace App\Achievements\Facts\Predicates;

use App\Achievements\Facts\DataSource;
use App\Achievements\Facts\Fact;
use App\Achievements\Facts\Predicate;
use App\Achievements\SessionData;

/**
 * The lap part of a Clean Sweep: the fastest lap and every lap led. From
 * 1996 from Jolpica's lap times (see SetFastestLap and LedEveryLap); before
 * that from the curated list of Grand Chelems (config/curated_clean_sweeps),
 * only once the owner has approved it.
 */
class FastestLapAndEveryLapLed implements Predicate
{
    public function __construct(private SetFastestLap $fastestLap, private LedEveryLap $everyLap) {}

    public function sources(): array
    {
        return [DataSource::JolpicaLaps, DataSource::JolpicaResults, DataSource::OpenF1Positions, DataSource::OpenF1Laps];
    }

    public function availableFrom(): int
    {
        return config('curated_clean_sweeps.approved') ? 1950 : (int) config('curated_clean_sweeps.before');
    }

    public function notes(): array
    {
        return config('curated_clean_sweeps.approved')
            ? ['From 1996 from Jolpica lap times; before that from the curated list of Grand Chelems.']
            : ['From 1996 from Jolpica lap times; the curated list for earlier seasons waits for the owner\'s review.'];
    }

    public function test(Fact $fact, SessionData $data): bool
    {
        if ($data->year() >= (int) config('curated_clean_sweeps.before')) {
            return $this->fastestLap->test($fact, $data) && $this->everyLap->test($fact, $data);
        }

        $driver = $data->entry($fact->entryId)?->driver?->jolpica_driver_id;

        return config('curated_clean_sweeps.approved') && collect(config('curated_clean_sweeps.races'))->contains(fn (array $race) => $race['season'] === $data->year()
            && $race['round'] === $data->session->round->round_number
            && $race['driver'] === $driver);
    }
}
