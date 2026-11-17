<?php

namespace App\Achievements\Facts\Concerns;

use App\Achievements\Facts\DataSource;

/**
 * Data needs of a provider or predicate that reads Jolpica's race and
 * sprint results, which go back to 1950.
 */
trait ReadsResults
{
    public function sources(): array
    {
        return [DataSource::JolpicaResults];
    }

    public function availableFrom(): int
    {
        return 1950;
    }

    public function notes(): array
    {
        return [];
    }
}
