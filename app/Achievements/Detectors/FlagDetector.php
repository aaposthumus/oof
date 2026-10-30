<?php

namespace App\Achievements\Detectors;

use App\Achievements\Detection;
use App\Achievements\Detector;
use App\Achievements\SessionData;
use App\Achievements\Support\ClassifiedMessage;
use App\Achievements\Support\Evidence;
use App\RaceControl\MessageType;
use Illuminate\Support\Collection;

/**
 * A flag shown to one car, once per message.
 */
abstract class FlagDetector implements Detector
{
    abstract protected function type(): MessageType;

    protected function counts(ClassifiedMessage $message): bool
    {
        return true;
    }

    protected function eventKey(ClassifiedMessage $message): string
    {
        return "rcm:{$message->message->id}";
    }

    public function detect(SessionData $data): Collection
    {
        return $data->messagesOfType($this->type())
            ->filter(fn (ClassifiedMessage $message) => $message->soleEntryId() !== null && $this->counts($message))
            ->map(fn (ClassifiedMessage $message) => new Detection(
                achievementKey: $this->key(),
                driverEntryId: $message->soleEntryId(),
                instanceKey: "rcm:{$message->message->id}",
                eventKey: $this->eventKey($message),
                evidence: Evidence::messages([$message]),
                carsNamed: 1,
            ))
            ->values();
    }
}
