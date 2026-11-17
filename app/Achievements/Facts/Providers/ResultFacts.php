<?php

namespace App\Achievements\Facts\Providers;

use App\Achievements\Facts\Concerns\ReadsResults;
use App\Achievements\Facts\Fact;
use App\Achievements\Facts\FactEvidence;
use App\Achievements\Facts\FactProvider;
use App\Achievements\SessionData;
use App\Achievements\Support\Finish;
use App\Achievements\Support\Surprise;
use App\Models\DriverEntry;
use Illuminate\Support\Collection;

/**
 * One car's race or sprint result row. All of a car's result achievements
 * share one event, so the more specific one wins.
 *
 * Attributes: position, position_text, grid, start ("P12", or "the pit
 * lane" for grid 0), status, laps_completed, laps_down (laps behind the
 * winner for a car still running), places_lost (from the grid, for a
 * classified finisher not starting from the pit lane), points,
 * fastest_lap_rank, surprise (detail and note when the team's result is a
 * surprise on form, else null; see Surprise).
 */
class ResultFacts implements FactProvider
{
    use ReadsResults;

    public function notes(): array
    {
        return [
            'From 2025 the retirement reason is only "Retired", so illness and injury cannot be filtered automatically.',
            'Grid 0 meant a pit lane start until 2024.',
        ];
    }

    public function facts(SessionData $data): Collection
    {
        return $data->resultEntries()
            ->map(fn (DriverEntry $entry) => new Fact(
                type: 'result',
                entryId: $entry->id,
                instanceKey: 'result',
                eventKey: "result:{$entry->id}",
                attributes: [
                    'position' => $entry->result->position,
                    'position_text' => $entry->result->position_text,
                    'grid' => $entry->result->grid,
                    'start' => $entry->result->grid === 0 ? 'the pit lane' : "P{$entry->result->grid}",
                    'status' => $entry->result->status,
                    'laps_completed' => (int) $entry->result->laps_completed,
                    'laps_down' => fn () => Finish::lapsDown($entry, $data),
                    'places_lost' => Finish::isClassifiedFinisher($entry->result) && $entry->result->grid > 0 ? $entry->result->position - $entry->result->grid : null,
                    'points' => (float) $entry->result->points,
                    'fastest_lap_rank' => $entry->result->fastest_lap_rank,
                    'surprise' => fn () => Surprise::of($entry, $data),
                ],
                evidence: FactEvidence::results([$entry]),
            ))
            ->values();
    }
}
