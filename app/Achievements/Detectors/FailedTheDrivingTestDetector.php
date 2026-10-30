<?php

namespace App\Achievements\Detectors;

use App\RaceControl\Offence;

/**
 * Penalised for: erratic or dangerous driving.
 */
class FailedTheDrivingTestDetector extends OffencePenaltyDetector
{
    public function key(): string
    {
        return 'failed_the_driving_test';
    }

    protected function offences(): array
    {
        return [Offence::ErraticDriving];
    }
}
