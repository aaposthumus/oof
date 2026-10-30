<?php

namespace App\Achievements\Detectors;

use App\RaceControl\Offence;

/**
 * Penalised for: leaving the track and gaining an advantage.
 */
class RecalculatingDetector extends OffencePenaltyDetector
{
    public function key(): string
    {
        return 'recalculating';
    }

    protected function offences(): array
    {
        return [Offence::LeavingTheTrack];
    }
}
