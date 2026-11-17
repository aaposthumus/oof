<?php

namespace App\Achievements\Facts\Providers;

use App\Achievements\Facts\Fact;
use App\Achievements\Facts\FactEvidence;
use App\Achievements\Facts\FactProvider;
use App\Achievements\SessionData;
use App\Achievements\Support\QualifyingPhases;
use App\Achievements\Support\TrackLimitDeletions;
use Illuminate\Support\Collection;

/**
 * A lap time deleted for track limits, once per deleted time (re-issued
 * deletions are left out). It belongs to the car's track-limits event.
 *
 * Attributes: phase (qualifying phase), time (as worded, or null), millis,
 * q3_millis and q3_time (the car's classified Q3 time).
 */
class TrackLimitDeletionFacts implements FactProvider
{
    use ReadsRaceControl;

    public function notes(): array
    {
        return [
            'Re-issued deletions are deduplicated on car and time.',
            'From 2024 most deletions carry no lap time.',
            'Practice deletions are published only from 2026.',
        ];
    }

    public function facts(SessionData $data): Collection
    {
        return TrackLimitDeletions::of($data)
            ->map(fn (array $deletion) => new Fact(
                type: 'track_limit_deletion',
                entryId: $deletion['entry_id'],
                instanceKey: "deletion:{$deletion['message']->message->id}",
                eventKey: "track_limits:{$deletion['entry_id']}",
                attributes: [
                    'phase' => $deletion['message']->phase,
                    'time' => $deletion['time'],
                    'millis' => $deletion['time'] === null ? null : TrackLimitDeletions::toMillis($deletion['time']),
                    'q3_millis' => fn () => $data->entry($deletion['entry_id'])?->qualifyingResult?->q3_millis,
                    'q3_time' => fn () => ($q3 = $data->entry($deletion['entry_id'])?->qualifyingResult?->q3_millis) === null ? null : QualifyingPhases::seconds($q3),
                ],
                evidence: FactEvidence::messages([$deletion['message']]),
            ))
            ->values();
    }
}
