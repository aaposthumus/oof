<?php

namespace App\Achievements\Detectors;

use App\RaceControl\Offence;

/**
 * Penalised for: speeding in the pit lane.
 */
class SmileForTheCameraDetector extends OffencePenaltyDetector
{
    public function key(): string
    {
        return 'smile_for_the_camera';
    }

    protected function offences(): array
    {
        return [Offence::SpeedingInThePitLane];
    }
}
