<?php

namespace App\Normalizers\OpenF1;

use App\Models\Stint;
use App\Normalizers\OpenF1\Support\SessionContext;

class StintsNormalizer extends SessionDataNormalizer
{
    public function endpoint(): string
    {
        return 'stints';
    }

    protected function model(): string
    {
        return Stint::class;
    }

    protected function uniqueBy(): array
    {
        return ['driver_number', 'stint_number'];
    }

    protected function rows(array $payload, SessionContext $context): array
    {
        return array_map(fn (array $stint) => [
            'driver_number' => $stint['driver_number'],
            'driver_entry_id' => $context->entryIdFor($stint['driver_number']),
            'stint_number' => $stint['stint_number'],
            'compound' => $stint['compound'] ?? null,
            'lap_start' => $stint['lap_start'] ?? null,
            'lap_end' => $stint['lap_end'] ?? null,
            'tyre_age_at_start' => $stint['tyre_age_at_start'] ?? null,
        ], $payload);
    }
}
