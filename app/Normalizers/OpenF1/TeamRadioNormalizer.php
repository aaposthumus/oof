<?php

namespace App\Normalizers\OpenF1;

use App\Models\TeamRadioMessage;
use App\Normalizers\OpenF1\Support\OpenF1Time;
use App\Normalizers\OpenF1\Support\SessionContext;

/**
 * The listing of published team radio messages. The audio is never downloaded.
 */
class TeamRadioNormalizer extends SessionDataNormalizer
{
    public function endpoint(): string
    {
        return 'team_radio';
    }

    protected function model(): string
    {
        return TeamRadioMessage::class;
    }

    protected function uniqueBy(): array
    {
        return ['driver_number', 'recorded_at'];
    }

    protected function rows(array $payload, SessionContext $context): array
    {
        return array_map(fn (array $message) => [
            'driver_number' => $message['driver_number'],
            'driver_entry_id' => $context->entryIdFor($message['driver_number']),
            'recorded_at' => OpenF1Time::toDateTime($message['date']),
            'recording_url' => $message['recording_url'],
        ], $payload);
    }
}
