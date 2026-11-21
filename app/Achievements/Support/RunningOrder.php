<?php

namespace App\Achievements\Support;

use App\Achievements\SessionData;
use App\Models\LapTime;
use App\Models\Position;
use App\Models\SessionLap;
use Illuminate\Support\Collection;
use WeakMap;

/**
 * Where each car ran at the end of a lap, for Bubble Burst (retired while
 * leading) and Dropped the Baton (a team 1-2 lost in the final laps). As
 * with LapsLed, Jolpica's lap times are the source of truth (races, from
 * 1996): the position at the end of each lap. Until they are in, OpenF1
 * stands in: the car's position at the end of the lap, which is the start of
 * its next lap, or for its last lap the start plus the lap's duration.
 */
class RunningOrder
{
    /**
     * @var WeakMap<SessionData, array<int, array<int, int>>>|null
     */
    private static ?WeakMap $orders = null;

    /**
     * The position of every car at the end of each lap.
     *
     * @return array<int, array<int, int>> Position by lap, then by driver entry ID
     */
    public static function of(SessionData $data): array
    {
        self::$orders ??= new WeakMap;

        return self::$orders[$data] ??= LapsLed::fromJolpica($data) ? self::fromJolpica($data) : self::fromOpenF1($data);
    }

    /**
     * The car's position at the end of a lap, or null when it is not known.
     */
    public static function positionAt(SessionData $data, int $entryId, int $lap): ?int
    {
        return self::of($data)[$lap][$entryId] ?? null;
    }

    /**
     * The order at the end of a lap: driver entry IDs by position.
     *
     * @return array<int, int> Driver entry ID by position
     */
    public static function orderAt(SessionData $data, int $lap): array
    {
        $order = array_flip(self::of($data)[$lap] ?? []);
        ksort($order);

        return $order;
    }

    /**
     * The last lap with a known order.
     */
    public static function lastLap(SessionData $data): ?int
    {
        $laps = array_keys(self::of($data));

        return $laps === [] ? null : max($laps);
    }

    /**
     * @return array<int, array<int, int>>
     */
    private static function fromJolpica(SessionData $data): array
    {
        return $data->lapTimes()
            ->filter(fn (LapTime $lap) => $lap->position !== null)
            ->groupBy('lap')
            ->map(fn (Collection $laps) => $laps->mapWithKeys(fn (LapTime $lap) => [$lap->driver_entry_id => $lap->position])->all())
            ->all();
    }

    /**
     * @return array<int, array<int, int>>
     */
    private static function fromOpenF1(SessionData $data): array
    {
        $positions = $data->positions()->groupBy('driver_entry_id');
        $order = [];

        foreach ($data->laps()->filter(fn (SessionLap $lap) => $lap->driver_entry_id !== null && $lap->started_at !== null)->groupBy('driver_entry_id') as $entryId => $laps) {
            $laps = $laps->sortBy('lap_number')->values();
            $own = $positions->get($entryId, collect())->values();

            foreach ($laps as $index => $lap) {
                $next = $laps->get($index + 1);
                $end = $next?->started_at ?? ($lap->duration_millis === null ? null : $lap->started_at->copy()->addMilliseconds($lap->duration_millis));
                $position = $end === null ? null : $own->last(fn (Position $position) => $position->recorded_at->lessThanOrEqualTo($end))?->position;

                if ($position !== null) {
                    $order[$lap->lap_number][$entryId] = $position;
                }
            }
        }

        ksort($order);

        return $order;
    }
}
