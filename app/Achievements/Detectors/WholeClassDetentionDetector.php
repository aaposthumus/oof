<?php

namespace App\Achievements\Detectors;

use App\Achievements\SessionData;
use App\Achievements\Support\ClassifiedMessage;
use App\RaceControl\MessageType;

/**
 * Caused a red flag, in any session. A red flag shown just after the
 * chequered flag only closes the session, so it does not count.
 */
class WholeClassDetentionDetector extends CauseDetector
{
    public function key(): string
    {
        return 'whole_class_detention';
    }

    protected function type(): MessageType
    {
        return MessageType::RedFlag;
    }

    protected function counts(ClassifiedMessage $deployment, SessionData $data): bool
    {
        $issuedAt = $deployment->message->issued_at;

        return ! $data->messagesOfType(MessageType::ChequeredFlag)->contains(
            fn (ClassifiedMessage $flag) => $flag->message->issued_at->betweenIncluded($issuedAt->copy()->subMinute(), $issuedAt),
        );
    }
}
