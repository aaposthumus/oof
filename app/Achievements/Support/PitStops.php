<?php

namespace App\Achievements\Support;

use App\Achievements\SessionData;
use App\Models\PitLaneVisit;
use App\RaceControl\MessageType;
use Illuminate\Support\Collection;

/**
 * Pit stops with OpenF1's stationary time. A stop that served a penalty, or
 * a car waiting in the pit lane under a red flag, is not a slow stop.
 */
class PitStops
{
    /**
     * @return Collection<int, PitLaneVisit>
     */
    public static function countable(SessionData $data): Collection
    {
        $served = $data->messagesOfType(MessageType::PenaltyServed);
        $redFlags = $data->messagesOfType(MessageType::RedFlag);

        return $data->pitVisits()
            ->filter(fn (PitLaneVisit $visit) => $visit->stop_duration_millis !== null && $visit->driver_entry_id !== null)
            ->reject(function (PitLaneVisit $visit) use ($served, $redFlags) {
                $from = $visit->entered_at->copy()->subSeconds(10);
                $to = $visit->entered_at->copy()->addMilliseconds(($visit->lane_duration_millis ?? $visit->stop_duration_millis) + 120_000);

                return $served->contains(fn (ClassifiedMessage $message) => in_array($visit->driver_entry_id, $message->entryIds, true) && $message->message->issued_at->between($from, $to))
                    || $redFlags->contains(fn (ClassifiedMessage $message) => $message->message->issued_at->between($visit->entered_at->copy()->subMinutes(5), $to));
            })
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    public static function row(PitLaneVisit $visit): array
    {
        return [
            'pit_lane_visit_id' => $visit->id,
            'car' => $visit->driver_number,
            'lap' => $visit->lap_number,
            'entered_at' => $visit->entered_at->format('Y-m-d H:i:s'),
            'stationary' => self::seconds($visit->stop_duration_millis),
            'pit_lane' => $visit->lane_duration_millis === null ? null : self::seconds($visit->lane_duration_millis),
        ];
    }

    public static function seconds(int $millis): string
    {
        return number_format($millis / 1000, 1).' s';
    }
}
