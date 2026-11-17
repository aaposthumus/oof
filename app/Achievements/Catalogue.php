<?php

namespace App\Achievements;

use InvalidArgumentException;

/**
 * The achievement catalogue in config/achievement_catalogue.php: what each
 * achievement seeds in the CMS (editorial defaults) and how the engine
 * finds it (mechanics and trigger).
 *
 * @phpstan-type Entry array{name: string, section: string, shelf: string, tier: string|null, score_category: string, description: string, supersedes: list<string>, sessions: list<string>, stacks: bool, recipient: string, scope: string, withheld_on_sensitive_weekends: bool, trigger: array<string, mixed>}
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
     * @return array{session_types: list<string>, stacks: bool, withheld_on_sensitive_weekends: bool}
     */
    public function mechanics(string $key): array
    {
        $entry = $this->entry($key);

        return [
            'session_types' => $entry['sessions'],
            'stacks' => $entry['stacks'],
            'withheld_on_sensitive_weekends' => $entry['withheld_on_sensitive_weekends'],
        ];
    }
}
