<?php

namespace App\Achievements\Facts\Providers;

use App\Achievements\Facts\DataSource;
use App\Achievements\Facts\Fact;
use App\Achievements\Facts\FactEvidence;
use App\Achievements\Facts\FactProvider;
use App\Achievements\SessionData;
use App\Achievements\Support\QualifyingPhases;
use App\Achievements\Support\Surprise;
use App\Models\DriverEntry;
use Illuminate\Support\Collection;

/**
 * One car's qualifying or sprint qualifying classification.
 *
 * Attributes: position, q1_millis, q1_time, surprise (detail and note when
 * the team's result is a surprise on form, else null; see Surprise).
 */
class QualifyingResultFacts implements FactProvider
{
    public function sources(): array
    {
        return [DataSource::JolpicaQualifying];
    }

    public function availableFrom(): int
    {
        return 1994;
    }

    public function notes(): array
    {
        return ['Sprint qualifying comes from OpenF1 session results.'];
    }

    public function facts(SessionData $data): Collection
    {
        return $data->entries
            ->filter(fn (DriverEntry $entry) => $entry->qualifyingResult !== null)
            ->map(fn (DriverEntry $entry) => new Fact(
                type: 'qualifying_result',
                entryId: $entry->id,
                instanceKey: 'qualifying',
                eventKey: "qualifying:{$entry->id}",
                attributes: [
                    'position' => $entry->qualifyingResult->position,
                    'q1_millis' => $entry->qualifyingResult->q1_millis,
                    'q1_time' => $entry->qualifyingResult->q1_millis === null ? null : QualifyingPhases::seconds($entry->qualifyingResult->q1_millis),
                    'surprise' => fn () => Surprise::of($entry, $data),
                ],
                evidence: FactEvidence::results([$entry]),
            ))
            ->values();
    }
}
