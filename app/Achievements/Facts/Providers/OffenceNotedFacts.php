<?php

namespace App\Achievements\Facts\Providers;

use App\Achievements\Facts\Fact;
use App\Achievements\Facts\FactEvidence;
use App\Achievements\Facts\FactProvider;
use App\Achievements\SessionData;
use App\Achievements\Support\ClassifiedMessage;
use App\Achievements\Support\IncidentThread;
use App\RaceControl\MessageType;
use Illuminate\Support\Collection;

/**
 * An offence race control noted or penalised for one car: one fact per
 * such message, whether or not a penalty followed.
 *
 * Attributes: incident, offence (null when race control names none),
 * penalised (whether the car got a penalty in the incident).
 */
class OffenceNotedFacts implements FactProvider
{
    use ReadsRaceControl;

    public function facts(SessionData $data): Collection
    {
        return collect($data->threads())
            ->flatMap(function (IncidentThread $thread) {
                $penalised = collect($thread->penalties())->map(fn (ClassifiedMessage $penalty) => $penalty->soleEntryId())->filter()->all();

                return collect($thread->messages)
                    ->filter(fn (ClassifiedMessage $message) => $message->soleEntryId() !== null && $message->is(MessageType::IncidentNoted, ...IncidentThread::PENALTIES))
                    ->map(fn (ClassifiedMessage $message) => new Fact(
                        type: 'offence_noted',
                        entryId: $message->soleEntryId(),
                        instanceKey: "incident:{$thread->id()}",
                        eventKey: $thread->eventKey(),
                        attributes: [
                            'incident' => $thread->id(),
                            'offence' => $thread->offence?->value,
                            'penalised' => in_array($message->soleEntryId(), $penalised, true),
                        ],
                        evidence: FactEvidence::messages($thread->messagesFor($message->soleEntryId())),
                        carsNamed: 1,
                    ));
            })
            ->values();
    }
}
