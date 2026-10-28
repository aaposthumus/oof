<?php

namespace App\Normalizers\OpenF1;

use App\Models\RaceControlMessage;
use App\Normalizers\OpenF1\Support\OpenF1Time;
use App\Normalizers\OpenF1\Support\SessionContext;

/**
 * Keeps race control messages with their original text. Many messages name
 * their car only in the text ("CAR 18 (STR) TIME 1:39.171 DELETED"), so the
 * cars named are parsed, and a message naming one car is linked to it.
 */
class RaceControlNormalizer extends SessionDataNormalizer
{
    public function endpoint(): string
    {
        return 'race_control';
    }

    protected function model(): string
    {
        return RaceControlMessage::class;
    }

    protected function uniqueBy(): array
    {
        return ['fingerprint'];
    }

    protected function rows(array $payload, SessionContext $context): array
    {
        $rows = [];
        $occurrences = [];

        foreach ($payload as $message) {
            $cars = self::carNumbers($message['message']);
            $carNumber = $message['driver_number'] ?? (count($cars) === 1 ? $cars[0] : null);

            // Identical messages at the same moment are told apart by their order.
            $identity = implode('|', [$message['date'], $message['category'], $message['message'], $message['qualifying_phase'] ?? '', $message['driver_number'] ?? '']);
            $occurrence = $occurrences[$identity] = ($occurrences[$identity] ?? 0) + 1;

            $rows[] = [
                'fingerprint' => sha1("{$identity}|{$occurrence}"),
                'issued_at' => OpenF1Time::toDateTime($message['date']),
                'lap_number' => $message['lap_number'] ?? null,
                'category' => $message['category'],
                'flag' => $message['flag'] ?? null,
                'scope' => $message['scope'] ?? null,
                'sector' => $message['sector'] ?? null,
                'qualifying_phase' => $message['qualifying_phase'] ?? null,
                'driver_number' => $message['driver_number'] ?? null,
                'car_number' => $carNumber,
                'driver_entry_id' => $context->entryIdFor($carNumber),
                'car_numbers' => $cars === [] ? null : json_encode($cars),
                'message' => $message['message'],
            ];
        }

        return $rows;
    }

    /**
     * Car numbers named in a message, written as "CAR 18 (STR)" or "CARS 6 (HAD) AND 23 (ALB)".
     *
     * @return list<int>
     */
    public static function carNumbers(string $message): array
    {
        preg_match_all('/\b(\d{1,2}) \([A-Z]{3}\)/', $message, $matches);

        return array_values(array_unique(array_map('intval', $matches[1])));
    }
}
