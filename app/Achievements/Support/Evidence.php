<?php

namespace App\Achievements\Support;

use App\Models\DriverEntry;
use App\Models\Result;

/**
 * The source an award rests on, stored word for word so a reviewer can check
 * it without knowing the race.
 */
class Evidence
{
    /**
     * @return array{source: string, messages: list<array{id: int, issued_at: string, lap: int|null, message: string}>, note?: string}
     */
    public static function messages(iterable $messages, ?string $note = null): array
    {
        $rows = [];

        foreach ($messages as $message) {
            $rows[$message->message->id] = [
                'id' => $message->message->id,
                'issued_at' => $message->message->issued_at->format('Y-m-d H:i:s'),
                'lap' => $message->message->lap_number,
                'message' => $message->message->message,
            ];
        }

        ksort($rows);

        return array_filter(['source' => 'race_control', 'messages' => array_values($rows), 'note' => $note]);
    }

    /**
     * @return array{source: string, rows: list<array<string, mixed>>, note?: string}
     */
    public static function results(iterable $entries, ?string $note = null): array
    {
        $rows = [];

        foreach ($entries as $entry) {
            $rows[] = self::resultRow($entry);
        }

        return array_filter(['source' => 'jolpica', 'rows' => $rows, 'note' => $note]);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{source: string, rows: list<array<string, mixed>>, note?: string}
     */
    public static function rows(string $source, array $rows, ?string $note = null): array
    {
        return array_filter(['source' => $source, 'rows' => $rows, 'note' => $note]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function resultRow(DriverEntry $entry): array
    {
        $result = $entry->result;
        $qualifying = $entry->qualifyingResult;

        return array_filter([
            'result_id' => $result?->id,
            'qualifying_result_id' => $qualifying?->id,
            'driver' => "{$entry->driver->given_name} {$entry->driver->family_name}",
            'car' => $entry->car_number,
            'team' => $entry->seasonTeam?->name,
            'grid' => $result?->grid,
            'position' => $result?->position_text ?? $qualifying?->position,
            'status' => $result?->status,
            'laps' => $result?->laps_completed,
            'points' => $result instanceof Result ? (float) $result->points : null,
            'fastest_lap_rank' => $result?->fastest_lap_rank,
            'q1' => $qualifying?->q1_millis,
            'q2' => $qualifying?->q2_millis,
            'q3' => $qualifying?->q3_millis,
        ], fn ($value) => $value !== null);
    }
}
