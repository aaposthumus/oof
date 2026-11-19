<?php

namespace App\Achievements;

use App\Models\Achievement;
use InvalidArgumentException;

/**
 * The achievement catalogue in config/achievement_catalogue.php: what each
 * achievement seeds in the CMS (editorial defaults) and how the engine
 * finds it (mechanics and trigger).
 *
 * Recipient: driver, team or safety_car (see App\Achievements\Recipient).
 *
 * Scope: session (decided per session), weekend (once per round, after the
 * session named in decided_after: practice for the last practice session,
 * or a session type) or season (once the final round is final). A weekend
 * or season entry lists the sessions it reads.
 *
 * @phpstan-type Entry array{name: string, section: string, shelf: string, tier: string|null, score_category: string, description: string, supersedes: list<string>, sessions: list<string>, stacks: bool, recipient: string, scope: string, decided_after?: string, withheld_on_sensitive_weekends: bool, trigger: array<string, mixed>}
 */
class Catalogue
{
    /**
     * Fields the CMS owns once the row exists.
     */
    public const EDITORIAL = ['name', 'section', 'shelf', 'tier', 'score_category', 'description', 'supersedes'];

    /**
     * @return array<string, Entry> Keyed by achievement key, in run order
     */
    public function entries(): array
    {
        return config('achievement_catalogue.achievements');
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->entries());
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->entries());
    }

    /**
     * @return Entry
     */
    public function entry(string $key): array
    {
        return $this->entries()[$key] ?? throw new InvalidArgumentException("No achievement [{$key}] in the catalogue.");
    }

    /**
     * @return array<string, mixed>
     */
    public function trigger(string $key): array
    {
        return $this->entry($key)['trigger'];
    }

    /**
     * The row's editorial defaults, for a new achievement only.
     *
     * @return array<string, mixed>
     */
    public function editorial(string $key): array
    {
        return [...array_intersect_key($this->entry($key), array_flip(self::EDITORIAL)), 'is_enabled' => true];
    }

    /**
     * The row's mechanics, which this file owns: sync writes them over the CMS.
     *
     * @return array{session_types: list<string>, recipient_type: string, scope: string, decided_after: string|null, stacks: bool, withheld_on_sensitive_weekends: bool, is_manual: bool}
     */
    public function mechanics(string $key): array
    {
        $entry = $this->entry($key);

        return [
            'session_types' => $entry['sessions'],
            'recipient_type' => $entry['recipient'],
            'scope' => $entry['scope'],
            'decided_after' => $entry['decided_after'] ?? null,
            'stacks' => $entry['stacks'],
            'withheld_on_sensitive_weekends' => $entry['withheld_on_sensitive_weekends'],
            'is_manual' => $entry['trigger']['type'] === 'manual',
        ];
    }

    /**
     * A hash of everything that decides what the engine finds: this file,
     * the engine settings in config/achievements.php (not the points, which
     * are snapshotted apart, nor the events), and what the engine reads from
     * the stored achievements (enabled, supersedes, the mechanics). The run
     * log keeps it per context, so oof:achievements --stale can re-run what
     * was decided on an older version. Bump achievements.engine_version when
     * a code change alters what the engine finds.
     */
    public function version(): string
    {
        $rows = Achievement::query()->orderBy('key')->get()
            ->map(fn (Achievement $achievement) => [
                $achievement->key, $achievement->is_enabled, $achievement->session_types, $achievement->recipient_type?->value, $achievement->scope?->value,
                $achievement->decided_after, $achievement->stacks, $achievement->supersedes, $achievement->withheld_on_sensitive_weekends, $achievement->is_manual,
            ])
            ->all();

        return sha1(json_encode([
            $this->entries(),
            array_diff_key(config('achievements'), array_flip(['points', 'events_from', 'first_season', 'shelf_from', 'live_from'])),
            $rows,
        ], JSON_THROW_ON_ERROR));
    }
}
