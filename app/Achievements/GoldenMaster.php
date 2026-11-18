<?php

namespace App\Achievements;

use App\Enums\AwardScope;
use App\Models\AchievementAward;
use App\Models\AchievementRun;
use App\Models\RaceSession;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * A snapshot of the awards the engine gives, kept in a file to compare later
 * runs against: the safety net under every change to the engine.
 *
 * It belongs to the database it was exported from, because instance keys
 * carry that database's IDs. Each award is one line, keyed by its session,
 * achievement and instance key, with its evidence as a hash. A weekend award
 * is keyed by the session it was decided after, a season award by its
 * season ("2025-season"), covered once the season has been decided.
 */
class GoldenMaster
{
    public const PATH = 'achievements/golden-master.jsonl';

    /**
     * The fields of an award that are compared.
     */
    public const FIELDS = ['driver', 'car', 'detail', 'variant', 'status', 'confidence', 'review_reason', 'event_key', 'evidence', 'sensitive'];

    /**
     * The awards as stored right now, with the sessions (and seasons) the engine has run.
     *
     * @param  list<int>  $seasons  Only these seasons; all of them when empty
     * @return array{sessions: list<string>, awards: array<string, array<string, string|int|null>>}
     */
    public function snapshot(array $seasons = []): array
    {
        $sessions = RaceSession::query()
            ->join('rounds', 'rounds.id', '=', 'race_sessions.round_id')
            ->join('seasons', 'seasons.id', '=', 'rounds.season_id')
            ->whereNotNull('race_sessions.achievements_checked_at')
            ->when($seasons !== [], fn ($query) => $query->whereIn('seasons.year', $seasons))
            ->toBase()
            ->get(['seasons.year', 'rounds.round_number', 'race_sessions.type'])
            ->map(fn (object $session) => self::sessionKey($session->year, $session->round_number, $session->type))
            ->concat(AchievementRun::query()
                ->join('seasons', 'seasons.id', '=', 'achievement_runs.season_id')
                ->where('achievement_runs.scope', AwardScope::Season)
                ->where('achievement_runs.is_dry_run', false)
                ->when($seasons !== [], fn ($query) => $query->whereIn('seasons.year', $seasons))
                ->distinct()
                ->pluck('seasons.year')
                ->map(fn (int $year) => self::seasonKey($year)))
            ->unique()
            ->sort()
            ->values()
            ->all();

        $awards = AchievementAward::query()
            ->join('achievements', 'achievements.id', '=', 'achievement_awards.achievement_id')
            ->leftJoin('race_sessions', 'race_sessions.id', '=', 'achievement_awards.race_session_id')
            ->leftJoin('rounds', 'rounds.id', '=', 'race_sessions.round_id')
            ->join('seasons', 'seasons.id', '=', 'achievement_awards.season_id')
            ->leftJoin('drivers', 'drivers.id', '=', 'achievement_awards.driver_id')
            ->leftJoin('driver_entries', 'driver_entries.id', '=', 'achievement_awards.driver_entry_id')
            ->when($seasons !== [], fn ($query) => $query->whereIn('seasons.year', $seasons))
            ->toBase()
            ->get([
                'seasons.year', 'rounds.round_number', 'race_sessions.type', 'achievements.key', 'achievement_awards.instance_key',
                'drivers.jolpica_driver_id', 'driver_entries.car_number', 'achievement_awards.detail', 'achievement_awards.variant_name',
                'achievement_awards.status', 'achievement_awards.confidence', 'achievement_awards.review_reason',
                'achievement_awards.event_key', 'achievement_awards.evidence', 'achievement_awards.is_sensitive',
            ])
            ->mapWithKeys(fn (object $award) => [
                ($award->type === null ? self::seasonKey($award->year) : self::sessionKey($award->year, $award->round_number, $award->type))."|{$award->key}|{$award->instance_key}" => [
                    'driver' => $award->jolpica_driver_id,
                    'car' => $award->car_number,
                    'detail' => $award->detail,
                    'variant' => $award->variant_name,
                    'status' => $award->status,
                    'confidence' => $award->confidence,
                    'review_reason' => $award->review_reason,
                    'event_key' => $award->event_key,
                    'evidence' => self::hash($award->evidence),
                    // Only when set, so a master exported before the flag existed still compares.
                    'sensitive' => $award->is_sensitive ? 1 : null,
                ],
            ])
            ->sortKeys()
            ->all();

        return ['sessions' => $sessions, 'awards' => $awards];
    }

    public function exists(): bool
    {
        return Storage::disk('local')->exists(self::PATH);
    }

    /**
     * @param  array{sessions: list<string>, awards: array<string, array<string, string|int|null>>}  $snapshot
     */
    public function write(array $snapshot): void
    {
        $lines = [json_encode(['exported_at' => now()->toIso8601String(), 'sessions' => $snapshot['sessions']], JSON_THROW_ON_ERROR)];

        foreach ($snapshot['awards'] as $key => $award) {
            $lines[] = json_encode(['key' => $key, ...$award], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        }

        Storage::disk('local')->put(self::PATH, implode("\n", $lines)."\n");
    }

    /**
     * @return array{exported_at: string, sessions: list<string>, awards: array<string, array<string, string|int|null>>}
     */
    public function read(): array
    {
        $lines = preg_split('/\R/', trim((string) Storage::disk('local')->get(self::PATH)));
        $header = json_decode(array_shift($lines), true, flags: JSON_THROW_ON_ERROR);
        $awards = [];

        foreach ($lines as $line) {
            $award = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
            $awards[$award['key']] = array_diff_key($award, ['key' => true]);
        }

        return ['exported_at' => $header['exported_at'], 'sessions' => $header['sessions'], 'awards' => $awards];
    }

    /**
     * What a snapshot gives differently from the golden master, within the
     * sessions both cover. Sessions the master does not know are listed, not
     * compared: they were run for the first time after the export.
     *
     * @param  array{sessions: list<string>, awards: array<string, array<string, string|int|null>>}  $master
     * @param  array{sessions: list<string>, awards: array<string, array<string, string|int|null>>}  $snapshot
     * @param  list<int>  $seasons  Only these seasons of the master; all of them when empty
     * @return array{missing: array<string, array<string, string|int|null>>, added: array<string, array<string, string|int|null>>, changed: array<string, array<string, array{0: string|int|null, 1: string|int|null}>>, compared: int, uncovered: list<string>}
     */
    public function diff(array $master, array $snapshot, array $seasons = []): array
    {
        $inSeasons = fn (string $key) => $seasons === [] || in_array((int) substr($key, 0, 4), $seasons, true);
        $covered = array_flip(array_filter($master['sessions'], $inSeasons));
        $isCovered = fn (string $key) => isset($covered[strstr($key, '|', true)]);
        $expected = array_filter($master['awards'], $isCovered, ARRAY_FILTER_USE_KEY);
        $actual = array_filter($snapshot['awards'], $isCovered, ARRAY_FILTER_USE_KEY);
        $changed = [];

        foreach (array_intersect_key($expected, $actual) as $key => $award) {
            $fields = [];

            foreach (self::FIELDS as $field) {
                if (($award[$field] ?? null) !== ($actual[$key][$field] ?? null)) {
                    $fields[$field] = [$award[$field] ?? null, $actual[$key][$field] ?? null];
                }
            }

            if ($fields !== []) {
                $changed[$key] = $fields;
            }
        }

        return [
            'missing' => array_diff_key($expected, $actual),
            'added' => array_diff_key($actual, $expected),
            'changed' => $changed,
            'compared' => count($expected),
            'uncovered' => array_values(array_filter($snapshot['sessions'], fn (string $session) => ! isset($covered[$session]))),
        ];
    }

    /**
     * Differences counted per achievement key.
     *
     * @param  array{missing: array<string, mixed>, added: array<string, mixed>, changed: array<string, mixed>}  $diff
     * @return Collection<string, array{missing: int, added: int, changed: int}>
     */
    public function countsPerAchievement(array $diff): Collection
    {
        $counts = [];

        foreach (['missing', 'added', 'changed'] as $kind) {
            foreach (array_keys($diff[$kind]) as $key) {
                $achievement = explode('|', $key)[1];
                $counts[$achievement] ??= ['missing' => 0, 'added' => 0, 'changed' => 0];
                $counts[$achievement][$kind]++;
            }
        }

        return collect($counts)->sortKeys();
    }

    /**
     * Sorts as text in the order the sessions were held within a season: year, round, session type.
     */
    private static function sessionKey(int $year, int $roundNumber, string $type): string
    {
        return sprintf('%04d-%02d-%s', $year, $roundNumber, $type);
    }

    /**
     * Sorts after the season's sessions.
     */
    private static function seasonKey(int $year): string
    {
        return sprintf('%04d-season', $year);
    }

    /**
     * A short hash of the evidence, whatever order the database returns its keys in.
     */
    private static function hash(string $evidence): string
    {
        return substr(sha1(json_encode(self::sorted(json_decode($evidence, true, flags: JSON_THROW_ON_ERROR)), JSON_THROW_ON_ERROR)), 0, 12);
    }

    /**
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    private static function sorted(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn ($item) => is_array($item) ? self::sorted($item) : $item, $value);
    }
}
