<?php

namespace App\Achievements\Support;

use App\Achievements\SessionData;
use App\Excavation\Support\RaceStatuses;
use App\Models\DriverEntry;
use App\Models\Result;

/**
 * What a race or sprint result row says about how the car finished.
 */
class Finish
{
    public static function isDisqualified(Result $result): bool
    {
        return RaceStatuses::isDisqualified($result->status);
    }

    public static function didNotStart(Result $result): bool
    {
        return $result->position_text === 'W' || in_array($result->status, ['Did not start', 'Withdrew'], true);
    }

    /**
     * Out of the race before the flag, for any reason but a disqualification,
     * a non-start, or injury and illness.
     */
    public static function retired(Result $result): bool
    {
        return ! RaceStatuses::isRunning($result->status)
            && ! self::isDisqualified($result)
            && ! self::didNotStart($result)
            && ! RaceStatuses::isSkipped($result->status);
    }

    /**
     * Laps behind the winner at the flag, for a car still running.
     */
    public static function lapsDown(DriverEntry $entry, SessionData $data): int
    {
        $result = $entry->result;

        if ($result === null || ! RaceStatuses::isRunning($result->status) || self::isDisqualified($result)) {
            return 0;
        }

        if (preg_match('/^\+(\d+) Laps?$/', $result->status, $matches) === 1) {
            return (int) $matches[1];
        }

        $winnerLaps = $data->resultEntries()->first(fn (DriverEntry $other) => $other->result->position === 1)?->result->laps_completed;

        return $result->status === 'Lapped' && $winnerLaps !== null ? max(0, $winnerLaps - $result->laps_completed) : 0;
    }

    /**
     * Classified at the flag and not disqualified, with a numeric position.
     */
    public static function isClassifiedFinisher(Result $result): bool
    {
        return $result->is_classified && $result->position !== null && ! self::isDisqualified($result);
    }
}
