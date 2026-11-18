<?php

namespace App\Achievements\Facts\Providers;

use App\Achievements\Facts\Concerns\ReadsResults;
use App\Achievements\Facts\DescribesAttributes;
use App\Achievements\Facts\Fact;
use App\Achievements\Facts\FactEvidence;
use App\Achievements\Facts\FactProvider;
use App\Achievements\SessionData;
use App\Achievements\Support\Finish;
use App\Achievements\Support\LapsLed;
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
 * classified finisher not starting from the pit lane), places_gained
 * (from the grid, for a classified finisher; a pit lane start counts as
 * starting behind every car that started), finish ("P5", else the status),
 * points, fastest_lap_rank, laps_led (OpenF1, from 2023; see LapsLed),
 * surprise (detail and note when the team's result is a surprise on form,
 * else null; see Surprise).
 */
class ResultFacts implements DescribesAttributes, FactProvider
{
    use ReadsResults;

    public function attributeYears(): array
    {
        return ['fastest_lap_rank' => 2004];
    }

    public function attributeNotes(): array
    {
        return ['grid' => 'Grid 0 meant a pit lane start until 2024.'];
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
                    'places_gained' => fn () => self::placesGained($entry, $data),
                    'finish' => Finish::isClassifiedFinisher($entry->result) ? "P{$entry->result->position}" : $entry->result->status,
                    'points' => (float) $entry->result->points,
                    'fastest_lap_rank' => $entry->result->fastest_lap_rank,
                    'laps_led' => fn () => LapsLed::of($data)[$entry->id] ?? 0,
                    'surprise' => fn () => Surprise::of($entry, $data),
                ],
                evidence: FactEvidence::results([$entry]),
            ))
            ->values();
    }

    /**
     * Places gained from the start to the flag, for a classified finisher. A
     * pit lane start (grid 0) counts as the slot behind every car that started.
     */
    private static function placesGained(DriverEntry $entry, SessionData $data): ?int
    {
        if (! Finish::isClassifiedFinisher($entry->result) || $entry->result->grid === null) {
            return null;
        }

        $start = $entry->result->grid > 0
            ? $entry->result->grid
            : $data->resultEntries()->reject(fn (DriverEntry $other) => Finish::didNotStart($other->result))->count();

        return $start - $entry->result->position;
    }
}
