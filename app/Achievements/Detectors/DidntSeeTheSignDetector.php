<?php

namespace App\Achievements\Detectors;

use App\RaceControl\Offence;

/**
 * Penalised for: ignoring a yellow, red or blue flag.
 */
class DidntSeeTheSignDetector extends OffencePenaltyDetector
{
    public function key(): string
    {
        return 'didnt_see_the_sign';
    }

    protected function offences(): array
    {
        return [Offence::YellowFlagInfringement, Offence::RedFlagInfringement, Offence::IgnoringBlueFlags];
    }
}
