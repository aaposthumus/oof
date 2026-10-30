<?php

namespace App\Normalizers\OpenF1;

use App\Models\OpenF1SessionDriver;
use App\Models\RaceControlMessage;
use App\Normalizers\OpenF1\Support\OpenF1Resolver;
use App\Normalizers\OpenF1\Support\OpenF1Time;
use App\Normalizers\OpenF1\Support\SessionContext;
use App\Normalizers\OpenF1\Support\SessionEntryLinker;

/**
 * Keeps race control messages with their original text. Many messages name
 * their car only in the text ("CAR 18 (STR) TIME 1:39.171 DELETED"), so the
 * cars named are parsed, and a message naming one car is linked to it. A
 * few name the car by acronym alone ("CAR LEC TIME 1:36.518 DELETED"); those
 * are found among the session's cars.
 *
 * OpenF1 sometimes runs a practice session's feed on into the F2 or F3
 * session after it, whose cars share F1's numbers. Once a message after the
 * chequered flag names another series, it and everything after are dropped.
 *
 * Every car a message names is linked through race_control_message_driver_entry.
 */
class RaceControlNormalizer extends SessionDataNormalizer
{
    public function __construct(OpenF1Resolver $openF1, private SessionEntryLinker $linker)
    {
        parent::__construct($openF1);
    }

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

    protected function stored(SessionContext $context): void
    {
        $this->linker->linkMessageEntries($context->session);
    }

    protected function rows(array $payload, SessionContext $context): array
    {
        $rows = [];
        $occurrences = [];
        $numbersByAcronym = OpenF1SessionDriver::query()
            ->where('race_session_id', $context->session->id)
            ->whereNotNull('name_acronym')
            ->pluck('driver_number', 'name_acronym')
            ->all();

        foreach (self::ownMessages($payload) as $message) {
            $cars = self::carNumbers($message['message'], $numbersByAcronym);
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
     * The session's messages, without those of a support series session that follows it.
     *
     * @param  list<array<string, mixed>>  $payload
     * @return list<array<string, mixed>>
     */
    public static function ownMessages(array $payload): array
    {
        $chequered = false;

        foreach ($payload as $index => $message) {
            if ($chequered && preg_match('/\b(?:F2|F3|F1 ACADEMY|PORSCHE)\b/', $message['message']) === 1) {
                return array_slice($payload, 0, $index);
            }

            $chequered = $chequered || str_starts_with($message['message'], 'CHEQUERED FLAG');
        }

        return $payload;
    }

    /**
     * Car numbers named in a message, written as "CAR 18 (STR)" or "CARS 6 (HAD) AND 23 (ALB)",
     * or else as "CAR LEC" when the acronym is one of the session's cars. A time
     * followed by "(PIT)" ("LAP 34 18:58:59 (PIT)") is not a car.
     *
     * @param  array<string, int>  $numbersByAcronym  The session's car numbers keyed by acronym
     * @return list<int>
     */
    public static function carNumbers(string $message, array $numbersByAcronym = []): array
    {
        preg_match_all('/(?<![^\s,])(\d{1,2}) \([A-Z]{3}\)/', $message, $matches);

        if ($matches[1] === [] && preg_match('/^CAR ([A-Z]{3}) /', $message, $acronym) === 1 && isset($numbersByAcronym[$acronym[1]])) {
            return [(int) $numbersByAcronym[$acronym[1]]];
        }

        return array_values(array_unique(array_map('intval', $matches[1])));
    }
}
