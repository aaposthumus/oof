<?php

namespace App\Achievements\Detectors;

use App\Achievements\Support\ClassifiedMessage;
use App\RaceControl\MessageType;
use App\RaceControl\Offence;

/**
 * Black-and-white flag for track limits: the car's track-limits event.
 */
class NoteSentHomeDetector extends FlagDetector
{
    public function key(): string
    {
        return 'note_sent_home_to_parents';
    }

    protected function type(): MessageType
    {
        return MessageType::BlackAndWhiteFlag;
    }

    protected function counts(ClassifiedMessage $message): bool
    {
        return $message->offence() === Offence::TrackLimits;
    }

    protected function eventKey(ClassifiedMessage $message): string
    {
        return "track_limits:{$message->soleEntryId()}";
    }
}
