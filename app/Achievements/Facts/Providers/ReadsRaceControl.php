<?php

namespace App\Achievements\Facts\Providers;

use App\Achievements\Facts\DataSource;

/**
 * Data needs of a provider that reads OpenF1's race control messages,
 * published from 2023.
 */
trait ReadsRaceControl
{
    public function sources(): array
    {
        return [DataSource::OpenF1RaceControl];
    }

    public function availableFrom(): int
    {
        return 2023;
    }

    public function notes(): array
    {
        return [];
    }
}
