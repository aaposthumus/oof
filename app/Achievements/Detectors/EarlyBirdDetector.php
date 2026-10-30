<?php

namespace App\Achievements\Detectors;

use App\RaceControl\Offence;

/**
 * Penalised for: a false start.
 */
class EarlyBirdDetector extends OffencePenaltyDetector
{
    public function key(): string
    {
        return 'early_bird';
    }

    protected function offences(): array
    {
        return [Offence::FalseStart];
    }
}
