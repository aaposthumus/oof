<?php

namespace App\Excavation;

use Illuminate\Support\Collection;

/**
 * What one excavator run found and what the runner did with it.
 */
final readonly class ExcavationResult
{
    /**
     * @param  Collection<int, Candidate>  $candidates  Kept candidates, highest score first
     * @param  array{found: int, created: int, updated: int, sensitive: int, blocked: int}  $stats
     */
    public function __construct(
        public Excavator $excavator,
        public Collection $candidates,
        public array $stats,
    ) {}
}
