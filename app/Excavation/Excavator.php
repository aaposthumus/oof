<?php

namespace App\Excavation;

use Illuminate\Support\Collection;

/**
 * Digs through the core tables for one kind of historic moment. Implementations
 * only read; the ExcavationRunner records runs, applies moment sensitivities
 * and stores the candidates.
 */
interface Excavator
{
    /**
     * Stable key, stored as the candidate type.
     */
    public function key(): string;

    /**
     * Raise when the logic or scoring changes, so runs show which version found what.
     */
    public function version(): int;

    /**
     * @return Collection<int, Candidate>
     */
    public function dig(DigParameters $parameters): Collection;
}
