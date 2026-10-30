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
 * Safety Car corner: a VSC deployment, once per deployment.
 */
class VirtuallyThereDetector implements Detector
{
    public function key(): string
    {
        return 'virtually_there';
    }

    public function detect(SessionData $data): Collection
    {
        return $data->messagesOfType(MessageType::VirtualSafetyCarDeployed)
            ->map(fn (ClassifiedMessage $message) => new Detection(
                achievementKey: $this->key(),
                driverEntryId: null,
                instanceKey: "rcm:{$message->message->id}",
                eventKey: "rcm:{$message->message->id}",
                evidence: Evidence::messages([$message]),
                detail: $message->message->lap_number === null ? null : "lap {$message->message->lap_number}",
            ))
            ->values();
    }
}
