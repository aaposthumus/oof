<?php

namespace App\Achievements\Detectors;

use App\Achievements\Detection;
use App\Achievements\Detector;
use App\Achievements\SessionData;
use App\Achievements\Support\ClassifiedMessage;
use App\Achievements\Support\Evidence;
use App\Achievements\Support\IncidentThread;
use App\Enums\AwardConfidence;
use App\RaceControl\MessageType;
use Illuminate\Support\Collection;

/**
 * Involved in an incident race control noted: every car it names. Being
 * named is not being blamed, so this is medium confidence and never implies
 * fault. It waits while the incident is under investigation.
 */
class ItWasLikeThatDetector implements Detector
{
    public function key(): string
    {
        return 'it_was_like_that_when_i_got_there';
    }

    public function detect(SessionData $data): Collection
    {
        return collect($data->threads())
            ->flatMap(fn (IncidentThread $thread) => collect($thread->messages)
                ->filter(fn (ClassifiedMessage $message) => $message->is(MessageType::IncidentNoted))
                ->flatMap(fn (ClassifiedMessage $message) => $message->entryIds)
                ->unique()
                ->map(fn (int $entryId) => new Detection(
                    achievementKey: $this->key(),
                    driverEntryId: $entryId,
                    instanceKey: "incident:{$thread->id()}",
                    eventKey: $thread->eventKey(),
                    evidence: Evidence::messages($thread->messagesFor($entryId)),
                    confidence: AwardConfidence::Medium,
                    isOpen: $thread->isOpen(),
                )))
            ->values();
    }
}
