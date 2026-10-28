<?php

namespace App\Normalizers\OpenF1;

use App\Models\WeatherSample;
use App\Normalizers\OpenF1\Support\OpenF1Time;
use App\Normalizers\OpenF1\Support\SessionContext;

class WeatherNormalizer extends SessionDataNormalizer
{
    public function endpoint(): string
    {
        return 'weather';
    }

    protected function model(): string
    {
        return WeatherSample::class;
    }

    protected function uniqueBy(): array
    {
        return ['recorded_at'];
    }

    protected function rows(array $payload, SessionContext $context): array
    {
        $rows = [];

        foreach ($payload as $sample) {
            $recordedAt = OpenF1Time::toDateTime($sample['date']);

            $rows[$recordedAt] = [
                'recorded_at' => $recordedAt,
                'air_temperature' => $sample['air_temperature'] ?? null,
                'track_temperature' => $sample['track_temperature'] ?? null,
                'humidity' => $sample['humidity'] ?? null,
                'pressure' => $sample['pressure'] ?? null,
                'rainfall' => isset($sample['rainfall']) ? $sample['rainfall'] > 0 : null,
                'wind_speed' => $sample['wind_speed'] ?? null,
                'wind_direction' => $sample['wind_direction'] ?? null,
            ];
        }

        return array_values($rows);
    }
}
