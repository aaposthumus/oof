<?php

namespace App\Normalizers\OpenF1;

use App\Enums\OpenF1MatchStatus;
use App\Models\OpenF1Meeting;
use App\Normalizers\Normalizer;

/**
 * Records a season's OpenF1 meetings. They are matched to rounds together
 * with their sessions, as the match goes by the race start.
 */
class MeetingsNormalizer implements Normalizer
{
    public function endpoint(): string
    {
        return 'meetings';
    }

    public function normalize(array $payload): void
    {
        foreach ($payload as $meeting) {
            $record = OpenF1Meeting::firstOrNew(['meeting_key' => $meeting['meeting_key']]);

            $record->fill([
                'year' => $meeting['year'],
                'name' => $meeting['meeting_name'],
                'official_name' => $meeting['meeting_official_name'] ?? null,
                'circuit_key' => $meeting['circuit_key'],
                'circuit_short_name' => $meeting['circuit_short_name'],
                'location' => $meeting['location'] ?? null,
                'country_name' => $meeting['country_name'] ?? null,
                'date_start' => $meeting['date_start'],
                'date_end' => $meeting['date_end'] ?? null,
                'is_cancelled' => $meeting['is_cancelled'] ?? false,
            ]);
            $record->match_status ??= OpenF1MatchStatus::Unmatched;
            $record->save();
        }
    }
}
