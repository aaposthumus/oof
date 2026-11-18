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
 * A car named in an incident race control noted, once per car and
 * incident. Being named is not being blamed. It is open while the incident
 * is under investigation.
 *
 * Attributes: incident, teammate (the family name of a teammate named in
 * the same incident, or null).
 */
class IncidentInvolvementFacts implements FactProvider
{
    use ReadsRaceControl;

    public function facts(SessionData $data): Collection
    {
        return collect($data->threads())
            ->flatMap(fn (IncidentThread $thread) => collect($thread->messages)
                ->filter(fn (ClassifiedMessage $message) => $message->is(MessageType::IncidentNoted))
                ->flatMap(fn (ClassifiedMessage $message) => $message->entryIds)
                ->unique()
                ->pipe(fn (Collection $entryIds) => $entryIds->map(fn (int $entryId) => new Fact(
                    type: 'incident_involvement',
                    entryId: $entryId,
                    instanceKey: "incident:{$thread->id()}",
                    eventKey: $thread->eventKey(),
                    attributes: ['incident' => $thread->id(), 'teammate' => self::teammate($entryId, $entryIds, $data)],
                    evidence: FactEvidence::messages($thread->messagesFor($entryId)),
                    isOpen: $thread->isOpen(),
                ))))
            ->values();
    }

    /**
     * The family name of a teammate among the other cars named, or null.
     *
     * @param  Collection<int, int>  $entryIds
     */
    private static function teammate(int $entryId, Collection $entryIds, SessionData $data): ?string
    {
        $team = $data->entry($entryId)?->season_team_id;
        $teammate = $entryIds
            ->reject(fn (int $other) => $other === $entryId)
            ->map(fn (int $other) => $data->entry($other))
            ->first(fn ($other) => $team !== null && $other?->season_team_id === $team);

        return $teammate?->driver->family_name;
    }
}
