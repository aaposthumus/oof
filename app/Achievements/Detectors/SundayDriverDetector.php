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
use App\RaceControl\Offence;
use Illuminate\Support\Collection;

/**
 * A maximum delta time offence (too slow, e.g. under the safety car or on a
 * qualifying in-lap). Unlike the other offences it counts once race control
 * notes it for one car: medium confidence, high once a penalty follows.
 */
class SundayDriverDetector implements Detector
{
    public function key(): string
    {
        return 'sunday_driver';
    }

    public function detect(SessionData $data): Collection
    {
        return collect($data->threads())
            ->filter(fn (IncidentThread $thread) => $thread->offence === Offence::MaximumTime)
            ->flatMap(function (IncidentThread $thread) {
                $penalised = collect($thread->penalties())->map(fn (ClassifiedMessage $penalty) => $penalty->soleEntryId())->filter()->all();

                return collect($thread->messages)
                    ->filter(fn (ClassifiedMessage $message) => $message->soleEntryId() !== null && $message->is(MessageType::IncidentNoted, ...IncidentThread::PENALTIES))
                    ->unique(fn (ClassifiedMessage $message) => $message->soleEntryId())
                    ->map(fn (ClassifiedMessage $message) => new Detection(
                        achievementKey: $this->key(),
                        driverEntryId: $message->soleEntryId(),
                        instanceKey: "incident:{$thread->id()}",
                        eventKey: $thread->eventKey(),
                        evidence: Evidence::messages($thread->messagesFor($message->soleEntryId())),
                        confidence: in_array($message->soleEntryId(), $penalised, true) ? AwardConfidence::High : AwardConfidence::Medium,
                        carsNamed: 1,
                    ));
            })
            ->values();
    }
}
