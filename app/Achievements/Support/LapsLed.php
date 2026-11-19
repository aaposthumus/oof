<?php

namespace App\Achievements\Support;

use App\Achievements\SessionData;
use App\Models\DriverEntry;
use App\Models\LapTime;
use App\Models\Position;
use App\Models\SessionLap;
use WeakMap;

/**
 * Laps each car led, counted the standard way: a lap counts for the car
 * that was P1 at its end, the last lap included. Jolpica's lap times are the
 * source of truth (from 1996, and for every race since 2023 once they are
 * in). Until they are, OpenF1 stands in: a lap counts when the car was P1 at
 * the start of the next one, and as the last lap has no next one, it counts
 * for the winner (capped at the laps the winner completed, as OpenF1's laps
 * can run past the flag). Counted once per session.
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

        return self::$counted[$data] ??= self::fromJolpica($data) ? self::countJolpica($data) : self::countOpenF1($data);
    }

    /**
     * Whether the count comes from Jolpica's lap times, so it is final.
     */
    public static function fromJolpica(SessionData $data): bool
    {
        return $data->lapTimes()->isNotEmpty();
    }

    /**
     * Whether the car was P1 at the end of every lap it completed (at least one).
     */
    public static function ledEveryLap(SessionData $data, DriverEntry $entry): bool
    {
        $completed = (int) $entry->result?->laps_completed;

        if ($completed === 0) {
            return false;
        }

        if (self::fromJolpica($data)) {
            return (self::of($data)[$entry->id] ?? 0) === $completed;
        }

        // OpenF1: P1 at the start of every lap after the first, so at the end of every lap but the last.
        $positions = $data->positions()->where('driver_entry_id', $entry->id)->values();
        $lapEnds = $data->laps()
            ->where('driver_entry_id', $entry->id)
            ->filter(fn (SessionLap $lap) => $lap->lap_number > 1 && $lap->started_at !== null)
            ->pluck('started_at');

        if ($positions->isEmpty() || $lapEnds->count() < $completed - 1) {
            return false;
        }

        return $lapEnds->every(fn ($lapEnd) => $positions->last(fn (Position $position) => $position->recorded_at->lessThanOrEqualTo($lapEnd))?->position === 1);
    }

    /**
     * @return array<int, int>
     */
    private static function countJolpica(SessionData $data): array
    {
        return $data->lapTimes()
            ->filter(fn (LapTime $lap) => $lap->position === 1)
            ->countBy('driver_entry_id')
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private static function countOpenF1(SessionData $data): array
    {
        $positions = $data->positions()->groupBy('driver_entry_id');

        $counts = $data->laps()
            ->filter(fn (SessionLap $lap) => $lap->driver_entry_id !== null && $lap->lap_number > 1 && $lap->started_at !== null)
            ->groupBy('driver_entry_id')
            ->map(function ($laps, int $entryId) use ($positions) {
                $own = $positions->get($entryId, collect())->values();

                return $laps->filter(fn (SessionLap $lap) => $own->last(fn (Position $position) => $position->recorded_at->lessThanOrEqualTo($lap->started_at))?->position === 1)->count();
            })
            ->all();

        $winner = $data->resultEntries()->first(fn (DriverEntry $entry) => $entry->result->position === 1);

        if ($winner !== null && $data->laps()->isNotEmpty()) {
            $counts[$winner->id] = min(($counts[$winner->id] ?? 0) + 1, (int) $winner->result->laps_completed);
        }

        return array_filter($counts);
    }
}
