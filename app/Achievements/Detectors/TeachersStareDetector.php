<?php

namespace App\Achievements\Detectors;

use App\Achievements\Support\ClassifiedMessage;
use App\RaceControl\MessageType;
use App\RaceControl\Offence;

/**
 * Black-and-white flag, for any reason. One for track limits belongs to the
 * car's track-limits event, so it replaces the deletions that led to it and
 * a track-limits penalty replaces it; any other stands on its own.
 */
class TeachersStareDetector extends FlagDetector
{
    public function key(): string
    {
        return 'teachers_stare';
    }

    protected function type(): MessageType
    {
        return MessageType::BlackAndWhiteFlag;
    }

    protected function eventKey(ClassifiedMessage $message): string
    {
        return $message->offence() === Offence::TrackLimits ? "track_limits:{$message->soleEntryId()}" : parent::eventKey($message);
    }
}
