<?php

namespace App\Achievements\Detectors;

use App\RaceControl\Offence;

/**
 * Penalised for: leaving the track and gaining an advantage.
 */
class ShortcutTaxDetector extends OffencePenaltyDetector
{
    public function key(): string
    {
        return 'shortcut_tax';
    }

    protected function offences(): array
    {
        return [Offence::LeavingTheTrack];
    }
}
