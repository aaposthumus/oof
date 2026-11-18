<?php

namespace App\Achievements\Support;

use App\Achievements\SessionData;
use App\Models\Position;
use App\Models\SessionLap;
use WeakMap;

/**
 * Laps each car led, read from OpenF1 as LedEveryLap reads them: a lap
 * counts when the car was P1 at its end (the start of its next lap). The
 * last lap has no next lap, so it is not counted for anyone. Counted once
 * per session.
 */
class LapsLed
{
    /**
     * @var WeakMap<SessionData, array<int, int>>|null
     */
    private static ?WeakMap $counted = null;

    /**
     * @return array<int, int> Laps led per driver entry ID, only for cars that led one
     */
    public static function of(SessionData $data): array
    {
        self::$counted ??= new WeakMap;

        return self::$counted[$data] ??= self::count($data);
    }

    /**
     * @return array<int, int>
     */
    private static function count(SessionData $data): array
    {
        $positions = $data->positions()->groupBy('driver_entry_id');

        return $data->laps()
            ->filter(fn (SessionLap $lap) => $lap->driver_entry_id !== null && $lap->lap_number > 1 && $lap->started_at !== null)
            ->groupBy('driver_entry_id')
            ->map(function ($laps, int $entryId) use ($positions) {
                $own = $positions->get($entryId, collect())->values();

                return $laps->filter(fn (SessionLap $lap) => $own->last(fn (Position $position) => $position->recorded_at->lessThanOrEqualTo($lap->started_at))?->position === 1)->count();
            })
            ->filter()
            ->all();
    }
}
