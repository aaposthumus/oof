<?php

namespace App\Achievements\Support;

use App\Achievements\SessionData;
use App\RaceControl\MessageType;
use Illuminate\Support\Collection;

/**
 * A session's safety car periods with the laps they ran: from the lap it was
 * deployed to the lap it came in, or a red flag or the chequered flag.
 */
class SafetyCarPeriods
{
    /**
     * @return Collection<int, array{deployed: ClassifiedMessage, ended: ClassifiedMessage|null, laps: int}>
     */
    public static function of(SessionData $data): Collection
    {
        $periods = collect();
        $open = null;
        $ends = [MessageType::SafetyCarInThisLap, MessageType::RedFlag, MessageType::ChequeredFlag];

        foreach ($data->messagesOfType(MessageType::SafetyCarDeployed, ...$ends) as $message) {
            if ($message->is(MessageType::SafetyCarDeployed)) {
                $open ??= $message;
            } elseif ($open !== null) {
                $periods->push(self::period($open, $message));
                $open = null;
            }
        }

        if ($open !== null) {
            $periods->push(self::period($open, null));
        }

        return $periods;
    }

    /**
     * @return array{deployed: ClassifiedMessage, ended: ClassifiedMessage|null, laps: int}
     */
    private static function period(ClassifiedMessage $deployed, ?ClassifiedMessage $ended): array
    {
        $from = $deployed->message->lap_number;
        $to = $ended?->message->lap_number;

        return ['deployed' => $deployed, 'ended' => $ended, 'laps' => $from === null || $to === null ? 0 : max(1, $to - $from + 1)];
    }
}
