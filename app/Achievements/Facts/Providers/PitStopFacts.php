<?php

namespace App\Achievements\Facts\Providers;

use App\Achievements\Facts\DataSource;
use App\Achievements\Facts\Fact;
use App\Achievements\Facts\FactEvidence;
use App\Achievements\Facts\FactProvider;
use App\Achievements\Facts\MeasuresCoverage;
use App\Achievements\SessionData;
use App\Achievements\Support\PitStops;
use App\Models\PitLaneVisit;
use Illuminate\Support\Collection;

/**
 * A pit stop with OpenF1's measured stationary time; there is no estimate.
 * Stops serving a penalty and stops around a red flag are left out (see
 * PitStops).
 *
 * Attributes: stop_millis, stop_time ("5.3 s"), rejoined (whether the car
 * completed a lap after the stop's lap, so it did not retire in the pits;
 * null without a result).
 *
 * Coverage: the share of the session's pit visits with a stationary time.
 */
class PitStopFacts implements FactProvider, MeasuresCoverage
{
    public function sources(): array
    {
        return [DataSource::OpenF1Pit];
    }

    public function availableFrom(): int
    {
        return 2024;
    }

    public function notes(): array
    {
        return [
            'Stationary time is measured from 2024, and only for a small share of visits.',
            'Stops serving a penalty are left out through race control, whose "penalty served" exists only from 2025.',
        ];
    }

    public function facts(SessionData $data): Collection
    {
        return PitStops::countable($data)
            ->map(fn (PitLaneVisit $visit) => new Fact(
                type: 'pit_stop',
                entryId: $visit->driver_entry_id,
                instanceKey: "pit:{$visit->id}",
                eventKey: "pit:{$visit->id}",
                attributes: [
                    'stop_millis' => $visit->stop_duration_millis,
                    'stop_time' => PitStops::seconds($visit->stop_duration_millis),
                    'rejoined' => fn () => self::rejoined($visit, $data),
                ],
                evidence: FactEvidence::rows('openf1', [PitStops::row($visit)]),
            ))
            ->values();
    }

    public function coverage(SessionData $data): ?float
    {
        $visits = $data->pitVisits()->filter(fn (PitLaneVisit $visit) => $visit->driver_entry_id !== null);

        return $visits->isEmpty() ? null : $visits->whereNotNull('stop_duration_millis')->count() / $visits->count();
    }

    private static function rejoined(PitLaneVisit $visit, SessionData $data): ?bool
    {
        $completed = $data->entry($visit->driver_entry_id)?->result?->laps_completed;

        return $completed === null || $visit->lap_number === null ? null : $completed > $visit->lap_number;
    }
}
