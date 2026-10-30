<?php

namespace App\Achievements\Detectors;

use App\RaceControl\Offence;

/**
 * Penalised for: crossing the pit entry or exit line.
 */
class LaneDisciplineDetector extends OffencePenaltyDetector
{
    public function key(): string
    {
        return 'lane_discipline';
    }

    protected function offences(): array
    {
        return [Offence::PitLaneInfringement];
    }

    protected function counts(?string $reason): bool
    {
        return $reason !== null && preg_match('/PIT (?:ENTRY|EXIT)|LINE/', $reason) === 1;
    }
}
