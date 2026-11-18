<?php

namespace App\Achievements\Support;

use App\Achievements\SessionData;
use App\Enums\SessionType;
use App\Models\DriverEntry;

/**
 * The practice sessions of the round a driver topped (P1 in OpenF1's
 * practice classification), in weekend order.
 */
class PracticeToppers
{
    private const PRACTICE = [SessionType::Practice1, SessionType::Practice2, SessionType::Practice3];

    /**
     * Labels of the sessions the driver topped ("FP1", "FP3"), empty when none.
     *
     * @return list<string>
     */
    public static function sessionsToppedBy(int $driverId, SessionData $data): array
    {
        return $data->weekendSessions(self::PRACTICE)
            ->filter(fn (SessionData $session) => $session->entries->contains(fn (DriverEntry $entry) => $entry->driver_id === $driverId && $entry->practiceResult?->position === 1))
            ->map(fn (SessionData $session) => self::label($session->type()))
            ->values()
            ->all();
    }

    private static function label(SessionType $type): string
    {
        return match ($type) {
            SessionType::Practice1 => 'FP1',
            SessionType::Practice2 => 'FP2',
            default => 'FP3',
        };
    }
}
