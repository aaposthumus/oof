<?php

namespace App\Achievements\Detectors;

use App\Achievements\Support\ClassifiedMessage;
use App\RaceControl\MessageType;

/**
 * A 5-second time penalty.
 */
class HighFiveDetector extends PenaltyDetector
{
    public function key(): string
    {
        return 'high_five';
    }

    protected function matches(ClassifiedMessage $penalty): bool
    {
        return $penalty->is(MessageType::TimePenalty) && $penalty->classification->detail === '5 s';
    }

    protected function detail(ClassifiedMessage $penalty): string
    {
        return '5 s';
    }
}
