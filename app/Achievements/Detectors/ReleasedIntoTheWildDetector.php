<?php

namespace App\Achievements\Detectors;

use App\RaceControl\Offence;

/**
 * Penalised for: an unsafe release.
 */
class ReleasedIntoTheWildDetector extends OffencePenaltyDetector
{
    public function key(): string
    {
        return 'released_into_the_wild';
    }

    protected function offences(): array
    {
        return [Offence::UnsafeRelease];
    }
}
