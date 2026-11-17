<?php

namespace App\Achievements\Facts;

/**
 * What a fact provider, predicate or custom trigger reads, and from which
 * year the data supports it. An achievement's data notes are derived from
 * these.
 */
interface DataNeeds
{
    /**
     * The sources it reads. For a fact provider or custom trigger these are
     * read from the session itself: without rows in them the achievement is
     * not looked for in that session.
     *
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
