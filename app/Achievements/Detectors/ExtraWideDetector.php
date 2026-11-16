<?php

namespace App\Achievements\Detectors;

use App\RaceControl\Offence;

/**
 * Penalised for: forcing another driver off the track.
 */
class ExtraWideDetector extends OffencePenaltyDetector
{
    public function key(): string
    {
        return 'extra_wide';
    }

    protected function offences(): array
    {
        return [Offence::ForcingOffTheTrack];
    }
}
