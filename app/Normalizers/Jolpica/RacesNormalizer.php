<?php

namespace App\Normalizers\Jolpica;

use App\Enums\SessionType;
use App\Normalizers\Jolpica\Support\CoreResolver;
use App\Normalizers\Normalizer;

/**
 * Creates rounds and their scheduled sessions from a season schedule.
 * Practice sessions are not created yet.
 */
class RacesNormalizer implements Normalizer
{
    /**
     * Schedule keys per session type. The 2023 sprint shootout is sprint qualifying.
     *
     * @var array<string, SessionType>
     */
    private const SCHEDULE_KEYS = [
        'Qualifying' => SessionType::Qualifying,
        'Sprint' => SessionType::Sprint,
        'SprintQualifying' => SessionType::SprintQualifying,
        'SprintShootout' => SessionType::SprintQualifying,
    ];

    public function __construct(private CoreResolver $core) {}

    public function endpoint(): string
    {
        return 'races';
    }

    public function normalize(array $payload): void
    {
        foreach ($payload['MRData']['RaceTable']['Races'] as $race) {
            $round = $this->core->round($race);

            $this->core->raceSession($round, SessionType::Race, $race['date'], $race['time'] ?? null);

            foreach (self::SCHEDULE_KEYS as $key => $type) {
                if (isset($race[$key])) {
                    $this->core->raceSession($round, $type, $race[$key]['date'], $race[$key]['time'] ?? null);
                }
            }
        }
    }
}
