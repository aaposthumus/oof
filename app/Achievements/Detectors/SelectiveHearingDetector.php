<?php

namespace App\Achievements\Detectors;

use App\RaceControl\Offence;

/**
 * Penalised for: failing to follow the race director's instructions.
 */
class SelectiveHearingDetector extends OffencePenaltyDetector
{
    public function key(): string
    {
        return 'selective_hearing';
    }

    protected function offences(): array
    {
        return [Offence::RaceDirectorsInstructions];
    }
}
