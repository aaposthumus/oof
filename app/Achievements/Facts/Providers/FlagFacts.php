<?php

namespace App\Achievements\Facts\Providers;

use App\Achievements\Facts\Concerns\ReadsRaceControl;
use App\Achievements\Facts\Fact;
use App\Achievements\Facts\FactEvidence;
use App\Achievements\Facts\FactProvider;
use App\Achievements\SessionData;
use App\Achievements\Support\ClassifiedMessage;
use App\RaceControl\MessageType;
use App\RaceControl\Offence;
use Illuminate\Support\Collection;

/**
 * A flag shown to one car, once per message. A black flag is the car's
 * disqualification event, so it replaces Sent to Your Room. A
 * black-and-white flag for track limits belongs to the car's track-limits
 * event, so it replaces the deletions before it and a track-limits penalty
 * replaces it; any other flag stands on its own.
 *
 * Attributes: flag (the message type, e.g. black_and_white_flag), offence.
 */
class FlagFacts implements FactProvider
{
    use ReadsRaceControl;

    private const FLAGS = [MessageType::BlueFlag, MessageType::BlackFlag, MessageType::BlackAndWhiteFlag, MessageType::BlackAndOrangeFlag];

    public function facts(SessionData $data): Collection
    {
        return $data->messagesOfType(...self::FLAGS)
            ->filter(fn (ClassifiedMessage $message) => $message->soleEntryId() !== null)
            ->map(fn (ClassifiedMessage $message) => new Fact(
                type: 'flag',
                entryId: $message->soleEntryId(),
                instanceKey: "rcm:{$message->message->id}",
                eventKey: self::eventKey($message),
                attributes: [
                    'flag' => $message->type()->value,
                    'offence' => $message->offence()?->value,
                ],
                evidence: FactEvidence::messages([$message]),
                carsNamed: 1,
            ))
            ->values();
    }

    private static function eventKey(ClassifiedMessage $message): string
    {
        return match (true) {
            $message->is(MessageType::BlackFlag) => "disqualification:{$message->soleEntryId()}",
            $message->is(MessageType::BlackAndWhiteFlag) && $message->offence() === Offence::TrackLimits => "track_limits:{$message->soleEntryId()}",
            default => "rcm:{$message->message->id}",
        };
    }
}
