<?php

namespace App\Achievements\Detectors;

use App\RaceControl\MessageType;

/**
 * Black-and-orange flag: the car has to come in for repairs.
 */
class MeatballSubDetector extends FlagDetector
{
    public function key(): string
    {
        return 'meatball_sub';
    }

    protected function type(): MessageType
    {
        return MessageType::BlackAndOrangeFlag;
    }
}
