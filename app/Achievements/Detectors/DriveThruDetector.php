<?php

namespace App\Achievements\Detectors;

use App\Achievements\Support\ClassifiedMessage;
use App\RaceControl\MessageType;

/**
 * A drive-through penalty.
 */
class DriveThruDetector extends PenaltyDetector
{
    public function key(): string
    {
        return 'drive_thru';
    }

    protected function matches(ClassifiedMessage $penalty): bool
    {
        return $penalty->is(MessageType::DriveThroughPenalty);
    }

    protected function detail(ClassifiedMessage $penalty): string
    {
        return 'drive-through';
    }
}
