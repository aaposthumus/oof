<?php

namespace App\Excavation\Support;

/**
 * What a result's status says, for every excavator alike.
 */
class RaceStatuses
{
    public static function isDisqualified(string $status): bool
    {
        return in_array($status, config('excavators.disqualified_statuses'), true);
    }

    /**
     * Statuses about injury, illness or a fatal accident, never used.
     */
    public static function isSkipped(string $status): bool
    {
        return in_array($status, config('excavators.skipped_statuses'), true);
    }

    /**
     * Running at the flag: finished, or classified laps down.
     */
    public static function isRunning(string $status): bool
    {
        return in_array($status, ['Finished', 'Lapped'], true) || str_starts_with($status, '+');
    }

    /**
     * The curious statuses from the config, never a skipped one.
     *
     * @return list<string>
     */
    public static function curious(): array
    {
        return array_values(array_filter(
            config('excavators.curious_retirement.statuses'),
            fn (string $status) => ! self::isSkipped($status),
        ));
    }
}
