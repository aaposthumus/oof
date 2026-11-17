<?php

namespace App\Achievements\Facts;

/**
 * What a fact provider or predicate reads, and from which year the data
 * supports it. An achievement's data notes are derived from these.
 */
interface DataNeeds
{
    /**
     * @return list<DataSource>
     */
    public function sources(): array;

    public function availableFrom(): int;

    /**
     * Caveats about the data, as printed by oof:achievements:catalogue.
     *
     * @return list<string>
     */
    public function notes(): array;
}
