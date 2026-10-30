<?php

namespace App\Achievements\Detectors;

use App\Achievements\Support\ClassifiedMessage;
use App\RaceControl\MessageType;

/**
 * A stop-go penalty.
 */
class RedLightGreenLightDetector extends PenaltyDetector
{
    public function key(): string
    {
        return 'red_light_green_light';
    }

    protected function matches(ClassifiedMessage $penalty): bool
    {
        return $penalty->is(MessageType::StopGoPenalty);
    }

    protected function detail(ClassifiedMessage $penalty): string
    {
        return trim('stop-go '.($penalty->classification->detail ?? ''));
    }
}
