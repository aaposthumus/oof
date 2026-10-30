<?php

namespace App\Achievements\Detectors;

use App\RaceControl\Offence;

/**
 * Penalised for: forcing another driver off the track.
 */
class RoadHogDetector extends OffencePenaltyDetector
{
    public function key(): string
    {
        return 'road_hog';
    }

    protected function offences(): array
    {
        return [Offence::ForcingOffTheTrack];
    }
}
