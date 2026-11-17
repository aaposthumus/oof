<?php

namespace App\Achievements\Facts\Providers;

use App\Achievements\Facts\Concerns\ReadsRaceControl;
use App\Achievements\Facts\Fact;
use App\Achievements\Facts\FactEvidence;
use App\Achievements\Facts\FactProvider;
use App\Achievements\SessionData;
use App\Achievements\Support\ClassifiedMessage;
use App\Achievements\Support\IncidentThread;
use App\RaceControl\MessageType;
use Illuminate\Support\Collection;

/**
 * An offence the stewards penalised one car for: one fact per penalty
 * naming a single car, in an incident with a known offence. Being noted or
 * investigated is not enough. It belongs to the incident, so it absorbs
 * the penalty it led to.
 *
 * Attributes: incident, offence, reason (as worded), other_penalty (a
 * penalty without an achievement of its own: "3 places", "disqualified").
 */
class PenalisedOffenceFacts implements FactProvider
{
    use ReadsRaceControl;

    public function facts(SessionData $data): Collection
    {
        return collect($data->threads())
            ->filter(fn (IncidentThread $thread) => $thread->offence !== null)
            ->flatMap(fn (IncidentThread $thread) => collect($thread->penalties())
                ->filter(fn (ClassifiedMessage $penalty) => $penalty->soleEntryId() !== null)
                ->map(fn (ClassifiedMessage $penalty) => new Fact(
                    type: 'penalised_offence',
                    entryId: $penalty->soleEntryId(),
                    instanceKey: "incident:{$thread->id()}",
                    eventKey: $thread->eventKey(),
                    attributes: [
                        'incident' => $thread->id(),
                        'offence' => $thread->offence->value,
                        'reason' => $thread->reason,
                        'other_penalty' => self::otherPenalty($penalty),
                    ],
                    evidence: FactEvidence::messages($thread->messagesFor($penalty->soleEntryId())),
                    carsNamed: $penalty->carsNamed(),
                )))
            ->values();
    }

    private static function otherPenalty(ClassifiedMessage $penalty): ?string
    {
        return match ($penalty->type()) {
            MessageType::GridPenalty => $penalty->classification->detail,
            MessageType::PitLaneStart => 'pit lane start',
            MessageType::Disqualification => 'disqualified',
            default => null,
        };
    }
}
