<?php

namespace App\Achievements\Detectors;

use App\RaceControl\Offence;

/**
 * Penalised for: overtaking or going too fast under the safety car or VSC.
 */
class QueueJumperDetector extends OffencePenaltyDetector
{
    public function key(): string
    {
        return 'queue_jumper';
    }

    protected function offences(): array
    {
        return [Offence::SafetyCarInfringement];
    }
}
