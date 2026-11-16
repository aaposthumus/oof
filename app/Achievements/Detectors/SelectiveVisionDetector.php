<?php

namespace App\Achievements\Detectors;

use App\RaceControl\Offence;

/**
 * Penalised for: ignoring a yellow, red or blue flag.
 */
class SelectiveVisionDetector extends OffencePenaltyDetector
{
    public function key(): string
    {
        return 'selective_vision';
    }

    protected function offences(): array
    {
        return [Offence::YellowFlagInfringement, Offence::RedFlagInfringement, Offence::IgnoringBlueFlags];
    }
}
