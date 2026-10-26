<?php

namespace App\Normalizers\Jolpica;

use App\Enums\SessionType;
use App\Models\Result;
use App\Normalizers\Jolpica\Support\CoreResolver;
use App\Normalizers\Jolpica\Support\TimeParser;
use App\Normalizers\Normalizer;

/**
 * Race classifications. SprintResultsNormalizer reuses this for sprints.
 */
class ResultsNormalizer implements Normalizer
{
    public function __construct(protected CoreResolver $core) {}

    public function endpoint(): string
    {
        return 'results';
    }

    protected function sessionType(): SessionType
    {
        return SessionType::Race;
    }

    protected function resultsKey(): string
    {
        return 'Results';
    }

    public function normalize(array $payload): void
    {
        // A season listing is paginated across races, so the row's position in
        // the whole listing is what keeps the source order within a session.
        $sourceIndex = (int) $payload['MRData']['offset'];

        foreach ($payload['MRData']['RaceTable']['Races'] as $race) {
            $round = $this->core->round($race);
            $season = $this->core->season((int) $race['season']);
            $session = $this->core->raceSession($round, $this->sessionType());

            foreach ($race[$this->resultsKey()] as $row) {
                $entry = $this->core->driverEntry(
                    $session,
                    $this->core->driver($row['Driver']),
                    $this->core->seasonTeam($season, $row['Constructor']),
                    isset($row['number']) ? (int) $row['number'] : null,
                );

                Result::updateOrCreate(['driver_entry_id' => $entry->id], [
                    'grid' => isset($row['grid']) ? (int) $row['grid'] : null,
                    'position' => isset($row['position']) ? (int) $row['position'] : null,
                    'position_text' => $row['positionText'],
                    'classification_order' => ++$sourceIndex,
                    'is_classified' => ctype_digit($row['positionText']),
                    'status' => $row['status'],
                    'points' => (float) $row['points'],
                    'laps_completed' => isset($row['laps']) ? (int) $row['laps'] : null,
                    'time_text' => $row['Time']['time'] ?? null,
                    'time_millis' => isset($row['Time']['millis']) ? (int) $row['Time']['millis'] : null,
                    'fastest_lap_number' => isset($row['FastestLap']['lap']) ? (int) $row['FastestLap']['lap'] : null,
                    'fastest_lap_rank' => isset($row['FastestLap']['rank']) ? (int) $row['FastestLap']['rank'] : null,
                    'fastest_lap_time_millis' => TimeParser::toMillis($row['FastestLap']['Time']['time'] ?? null),
                ]);
            }
        }
    }
}
