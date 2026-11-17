<?php

namespace App\Achievements\Facts\Providers;

use App\Achievements\Facts\Fact;
use App\Achievements\Facts\FactEvidence;
use App\Achievements\Facts\FactProvider;
use App\Achievements\SessionData;
use App\Achievements\Support\ClassifiedMessage;
use App\Achievements\Support\IncidentThread;
use App\RaceControl\Offence;
use Illuminate\Support\Collection;

/**
 * A penalty naming one car, one fact per penalty message. A track-limits
 * penalty belongs to the car's track-limits event, so it replaces the
 * deletions and the black-and-white flag that led to it; any other belongs
 * to its incident.
 *
 * Attributes: incident, kind (the message type, e.g. time_penalty),
 * detail (as classified, e.g. "5 s").
 */
class PenaltyFacts implements FactProvider
{
    use ReadsRaceControl;

    public function facts(SessionData $data): Collection
    {
        return collect($data->threads())
            ->flatMap(fn (IncidentThread $thread) => collect($thread->penalties())
                ->filter(fn (ClassifiedMessage $penalty) => $penalty->soleEntryId() !== null)
                ->map(fn (ClassifiedMessage $penalty) => new Fact(
                    type: 'penalty',
                    entryId: $penalty->soleEntryId(),
                    instanceKey: "rcm:{$penalty->message->id}",
                    eventKey: $thread->offence === Offence::TrackLimits ? "track_limits:{$penalty->soleEntryId()}" : $thread->eventKey(),
                    attributes: [
                        'incident' => $thread->id(),
                        'kind' => $penalty->type()->value,
                        'detail' => $penalty->classification->detail,
                    ],
                    evidence: FactEvidence::messages($thread->messagesFor($penalty->soleEntryId())),
                    carsNamed: $penalty->carsNamed(),
                )))
            ->values();
    }
}
