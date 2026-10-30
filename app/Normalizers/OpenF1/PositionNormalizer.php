<?php

namespace App\Normalizers\OpenF1;

use App\Models\Position;
use App\Normalizers\OpenF1\Support\OpenF1Time;
use App\Normalizers\OpenF1\Support\SessionContext;

/**
 * Running positions of any session, into positions: one row per change.
 */
class PositionNormalizer extends SessionDataNormalizer
{
    public function endpoint(): string
    {
        return 'position';
    }

    protected function model(): string
    {
        return Position::class;
    }

    protected function uniqueBy(): array
    {
        return ['driver_number', 'recorded_at'];
    }

    protected function rows(array $payload, SessionContext $context): array
    {
        $rows = [];

        // OpenF1 can list the same change twice; the row is kept once.
        foreach ($payload as $position) {
            $recordedAt = OpenF1Time::toDateTime($position['date']);

            $rows["{$position['driver_number']}|{$recordedAt}"] = [
                'driver_number' => $position['driver_number'],
                'driver_entry_id' => $context->entryIdFor($position['driver_number']),
                'recorded_at' => $recordedAt,
                'position' => $position['position'],
            ];
        }

        return array_values($rows);
    }
}
