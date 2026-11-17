<?php

namespace App\Achievements\Facts\Predicates;

use App\Achievements\Facts\DataSource;
use App\Achievements\Facts\Fact;
use App\Achievements\Facts\Predicate;
use App\Achievements\SessionData;
use App\Models\Position;
use App\Models\SessionLap;

/**
 * The car led at the end of every lap, read from OpenF1's positions at the
 * start of each next lap. Without positions and laps the race cannot be
 * judged, so it does not hold.
 */
class LedEveryLap implements Predicate
{
    public function sources(): array
    {
        return [DataSource::OpenF1Positions, DataSource::OpenF1Laps];
    }

    public function availableFrom(): int
    {
        return 2023;
    }

    public function notes(): array
    {
        return ['Before 2023 it needs the Jolpica lap-time import (from 1996).'];
    }

    public function test(Fact $fact, SessionData $data): bool
    {
        $entry = $data->entry($fact->entryId);
        $positions = $data->positions()->where('driver_entry_id', $entry->id)->values();
        $lapEnds = $data->laps()
            ->where('driver_entry_id', $entry->id)
            ->filter(fn (SessionLap $lap) => $lap->lap_number > 1 && $lap->started_at !== null)
            ->pluck('started_at');

        if ($positions->isEmpty() || $lapEnds->count() < $entry->result->laps_completed - 1) {
            return false;
        }

        return $lapEnds->every(fn ($lapEnd) => $positions->last(fn (Position $position) => $position->recorded_at->lessThanOrEqualTo($lapEnd))?->position === 1);
    }
}
