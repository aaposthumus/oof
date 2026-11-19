<?php

namespace App\Jolpica\Dumps;

use App\Enums\SessionType;
use App\Models\LapTime;
use App\Models\RaceSession;
use App\Normalizers\Jolpica\Support\CoreResolver;
use App\Normalizers\Jolpica\Support\TimeParser;
use Closure;
use Generator;
use Illuminate\Support\Facades\DB;

/**
 * Writes the race lap times of a Jolpica dump into lap_times, as the laps
 * normalizer does for an API response: each lap on the driver's race entry,
 * found by season, round and Jolpica driver ID. One race per transaction;
 * safe to re-run. Sprint, qualifying and practice laps are left out, like
 * the API's laps endpoint, which serves only races.
 */
class LapDumpImporter
{
    private const CHUNK = 1000;

    public function __construct(private CoreResolver $core) {}

    /**
     * @param  list<int>  $years  Only these seasons; empty for every season in the dump
     * @param  (Closure(int $year, int $round, int $laps): void)|null  $onRace  Called after each race written
     * @return array{races: int, laps: int, unmatched_laps: int, missing_rounds: list<string>}
     */
    public function import(JolpicaDump $dump, array $years = [], ?Closure $onRace = null): array
    {
        $report = ['races' => 0, 'laps' => 0, 'unmatched_laps' => 0, 'missing_rounds' => []];
        $races = $this->raceSessions($dump, $years);
        $entries = $this->raceSessionEntries($dump, $races);

        foreach ($this->lapsBySession($dump, $races, $entries) as $sessionId => $laps) {
            ['year' => $year, 'round' => $round] = $races[$sessionId];
            $session = RaceSession::query()
                ->where('type', SessionType::Race)
                ->whereHas('round', fn ($query) => $query->where('round_number', $round)->whereRelation('season', 'year', $year))
                ->first();

            if ($session === null) {
                $report['missing_rounds'][] = "{$year}-{$round}";

                continue;
            }

            $entryIds = $this->core->entryIdsByJolpicaDriver($session);
            $rows = [];

            foreach ($laps as [$driver, $lap, $position, $time]) {
                if (($entryId = $entryIds[$driver] ?? null) === null) {
                    $report['unmatched_laps']++;

                    continue;
                }

                $rows[] = ['driver_entry_id' => $entryId, 'lap' => $lap, 'position' => $position, 'time_millis' => $time];
            }

            DB::transaction(function () use ($rows) {
                foreach (array_chunk($rows, self::CHUNK) as $chunk) {
                    LapTime::upsert($chunk, ['driver_entry_id', 'lap'], ['position', 'time_millis']);
                }
            });

            $report['races']++;
            $report['laps'] += count($rows);

            if ($onRace !== null) {
                $onRace($year, $round, count($rows));
            }
        }

        return $report;
    }

    /**
     * The dump's race sessions, keyed by their dump ID, in calendar order.
     *
     * @param  list<int>  $years
     * @return array<string, array{year: int, round: int}>
     */
    private function raceSessions(JolpicaDump $dump, array $years): array
    {
        $seasons = [];

        foreach ($dump->rows('season') as $season) {
            $seasons[$season['id']] = (int) $season['year'];
        }

        $rounds = [];

        foreach ($dump->rows('round') as $round) {
            $year = $seasons[$round['season_id']];

            if ($years === [] || in_array($year, $years, true)) {
                $rounds[$round['id']] = ['year' => $year, 'round' => (int) $round['number']];
            }
        }

        $races = [];

        foreach ($dump->rows('session') as $session) {
            if ($session['type'] === 'R' && isset($rounds[$session['round_id']])) {
                $races[$session['id']] = $rounds[$session['round_id']];
            }
        }

        uasort($races, fn (array $a, array $b) => [$a['year'], $a['round']] <=> [$b['year'], $b['round']]);

        return $races;
    }

    /**
     * Session entries of those races: dump ID => [session ID, Jolpica driver ID].
     *
     * @param  array<string, array{year: int, round: int}>  $races
     * @return array<string, array{0: string, 1: string}>
     */
    private function raceSessionEntries(JolpicaDump $dump, array $races): array
    {
        $drivers = [];

        foreach ($dump->rows('driver') as $driver) {
            $drivers[$driver['id']] = $driver['reference'];
        }

        $teamDrivers = [];

        foreach ($dump->rows('teamdriver') as $teamDriver) {
            $teamDrivers[$teamDriver['id']] = $drivers[$teamDriver['driver_id']];
        }

        $roundEntries = [];

        foreach ($dump->rows('roundentry') as $roundEntry) {
            $roundEntries[$roundEntry['id']] = $teamDrivers[$roundEntry['team_driver_id']];
        }

        $entries = [];

        foreach ($dump->rows('sessionentry') as $entry) {
            if (isset($races[$entry['session_id']])) {
                $entries[$entry['id']] = [$entry['session_id'], $roundEntries[$entry['round_entry_id']]];
            }
        }

        return $entries;
    }

    /**
     * The numbered laps of each race, in calendar order. The lap file is not
     * ordered by race, so it is read once and kept packed per race; each race
     * is unpacked only when it is written.
     *
     * @param  array<string, array{year: int, round: int}>  $races
     * @param  array<string, array{0: string, 1: string}>  $entries
     * @return Generator<string, list<array{0: string, 1: int, 2: ?int, 3: ?int}>> [driver, lap, position, time]
     */
    private function lapsBySession(JolpicaDump $dump, array $races, array $entries): Generator
    {
        $packed = [];

        foreach ($dump->rows('lap') as $lap) {
            if ($lap['number'] === '' || ! isset($entries[$lap['session_entry_id']])) {
                continue;
            }

            [$sessionId, $driver] = $entries[$lap['session_entry_id']];
            $packed[$sessionId] ??= '';
            $packed[$sessionId] .= implode("\t", [$driver, $lap['number'], $lap['position'], $lap['time']])."\n";
        }

        foreach (array_keys($races) as $sessionId) {
            if (! isset($packed[$sessionId])) {
                continue;
            }

            $lines = explode("\n", rtrim($packed[$sessionId], "\n"));
            unset($packed[$sessionId]);

            yield $sessionId => array_map(function (string $line) {
                [$driver, $lap, $position, $time] = explode("\t", $line);

                return [$driver, (int) $lap, $position === '' ? null : (int) $position, TimeParser::toMillis($time)];
            }, $lines);
        }
    }
}
