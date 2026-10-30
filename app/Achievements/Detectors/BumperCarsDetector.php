<?php

namespace App\Achievements\Detectors;

use App\RaceControl\Offence;

/**
 * Penalised for: causing a collision.
 */
class BumperCarsDetector extends OffencePenaltyDetector
{
    public function key(): string
    {
        return 'bumper_cars';
    }

    protected function offences(): array
    {
        return [Offence::CausingACollision];
    }
}
