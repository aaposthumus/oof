<?php

namespace App\Normalizers\OpenF1;

use App\Enums\SessionType;
use App\Models\QualifyingResult;
use App\Models\RaceSession;
use App\Normalizers\Normalizer;
use App\Normalizers\OpenF1\Support\OpenF1Resolver;
use App\Normalizers\OpenF1\Support\OpenF1Time;

/**
 * Sprint qualifying classifications, which Jolpica does not have, into
 * qualifying_results. OpenF1 gives each car's best time per phase (SQ1-SQ3).
 * Results of the other sessions come from Jolpica, so they are skipped.
 */
class SessionResultNormalizer implements Normalizer
{
    public function __construct(private OpenF1Resolver $openF1) {}

    public function endpoint(): string
    {
        return 'session_result';
    }

    public function normalize(array $payload): void
    {
        if ($payload === []) {
            return;
        }

        $session = RaceSession::firstWhere('openf1_session_key', (int) $payload[0]['session_key']);

        if ($session?->type !== SessionType::SprintQualifying) {
            return;
        }

        $context = $this->openF1->sessionContext($payload);
        $entryIds = [];

        foreach ($payload as $row) {
            $entryId = $context->entryIdFor($row['driver_number']);

            if ($entryId === null) {
                continue;
            }

            $durations = is_array($row['duration'] ?? null) ? $row['duration'] : [];
            $entryIds[] = $entryId;

            QualifyingResult::updateOrCreate(['driver_entry_id' => $entryId], [
                'position' => $row['position'] ?? null,
                'q1_millis' => OpenF1Time::toMillis($durations[0] ?? null),
                'q2_millis' => OpenF1Time::toMillis($durations[1] ?? null),
                'q3_millis' => OpenF1Time::toMillis($durations[2] ?? null),
            ]);
        }

        QualifyingResult::query()
            ->whereIn('driver_entry_id', $session->driverEntries()->select('id'))
            ->whereNotIn('driver_entry_id', $entryIds)
            ->delete();
    }
}
