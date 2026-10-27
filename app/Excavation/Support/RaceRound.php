<?php

namespace App\Excavation\Support;

/**
 * Field and finisher counts of one round's race.
 */
final readonly class RaceRound
{
    public function __construct(
        public int $roundId,
        public int $year,
        public int $entries,
        public int $starters,
        public int $classified,
        public int $running,
        public float $seasonMedianStarters,
    ) {}
}
