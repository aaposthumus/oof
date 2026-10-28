<?php

namespace App\Normalizers\OpenF1;

use App\Models\SessionLap;
use App\Normalizers\OpenF1\Support\OpenF1Time;
use App\Normalizers\OpenF1\Support\SessionContext;

/**
 * Laps of any session, into session_laps. The mini-sector segments stay in
 * the raw layer only.
 */
class LapsNormalizer extends SessionDataNormalizer
{
    public function endpoint(): string
    {
        return 'laps';
    }

    protected function model(): string
    {
        return SessionLap::class;
    }

    protected function uniqueBy(): array
    {
        return ['driver_number', 'lap_number'];
    }

    protected function rows(array $payload, SessionContext $context): array
    {
        return array_map(fn (array $lap) => [
            'driver_number' => $lap['driver_number'],
            'driver_entry_id' => $context->entryIdFor($lap['driver_number']),
            'lap_number' => $lap['lap_number'],
            'started_at' => OpenF1Time::toDateTime($lap['date_start'] ?? null),
            'duration_millis' => OpenF1Time::toMillis($lap['lap_duration'] ?? null),
            'sector_1_millis' => OpenF1Time::toMillis($lap['duration_sector_1'] ?? null),
            'sector_2_millis' => OpenF1Time::toMillis($lap['duration_sector_2'] ?? null),
            'sector_3_millis' => OpenF1Time::toMillis($lap['duration_sector_3'] ?? null),
            'i1_speed' => $lap['i1_speed'] ?? null,
            'i2_speed' => $lap['i2_speed'] ?? null,
            'st_speed' => $lap['st_speed'] ?? null,
            'is_pit_out_lap' => (bool) ($lap['is_pit_out_lap'] ?? false),
        ], $payload);
    }
}
