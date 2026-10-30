<?php

namespace App\Achievements\Detectors;

use App\Achievements\Support\ClassifiedMessage;
use App\RaceControl\MessageType;

/**
 * A 10-second time penalty.
 */
class PerfectTenDetector extends PenaltyDetector
{
    public function key(): string
    {
        return 'perfect_ten';
    }

    protected function matches(ClassifiedMessage $penalty): bool
    {
        return $penalty->is(MessageType::TimePenalty) && $penalty->classification->detail === '10 s';
    }

    protected function detail(ClassifiedMessage $penalty): string
    {
        return '10 s';
    }
}
