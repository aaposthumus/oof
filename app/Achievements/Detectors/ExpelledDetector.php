<?php

namespace App\Achievements\Detectors;

use App\Achievements\Support\ClassifiedMessage;
use App\RaceControl\MessageType;

/**
 * Black flag during the race. It is the car's disqualification event, so it
 * replaces Sent to Your Room.
 */
class ExpelledDetector extends FlagDetector
{
    public function key(): string
    {
        return 'expelled';
    }

    protected function type(): MessageType
    {
        return MessageType::BlackFlag;
    }

    protected function eventKey(ClassifiedMessage $message): string
    {
        return "disqualification:{$message->soleEntryId()}";
    }
}
