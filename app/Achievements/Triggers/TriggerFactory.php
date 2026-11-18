<?php

namespace App\Achievements\Triggers;

use App\Achievements\Catalogue;
use App\Achievements\Facts\Conditions;
use App\Achievements\Facts\Facts;
use App\Enums\RecipientType;
use App\Enums\SessionType;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * Builds each achievement's trigger from its catalogue entry. A weekend
 * trigger reads the sessions its entry lists; an aggregate trigger is a
 * season trigger. A declarative trigger gives its detections the entry's
 * recipient; a team achievement needs a match, count or extreme trigger.
 */
class TriggerFactory
{
    /**
     * @var array<string, Trigger|SeasonTrigger>
     */
    private array $built = [];

    public function __construct(
        private Container $container,
        private Catalogue $catalogue,
        private Facts $facts,
        private Conditions $conditions,
    ) {}

    /**
     * @return array<string, Trigger|SeasonTrigger> Keyed by achievement key, in run order
     */
    public function all(): array
    {
        return collect($this->catalogue->keys())->mapWithKeys(fn (string $key) => [$key => $this->for($key)])->all();
    }

    public function for(string $key): Trigger|SeasonTrigger
    {
        return $this->built[$key] ??= $this->make($key, $this->catalogue->entry($key));
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function make(string $key, array $entry): Trigger|SeasonTrigger
    {
        $definition = $entry['trigger'];
        $sessions = array_map(SessionType::from(...), $entry['sessions']);
        $weekendSessions = $entry['scope'] === 'weekend' ? $sessions : null;
        $recipient = RecipientType::from($entry['recipient']);

        if ($entry['scope'] === 'weekend' && ! isset($entry['decided_after'])) {
            throw new InvalidArgumentException("[{$key}]: a weekend achievement names the session it is decided after (decided_after).");
        }

        if (($definition['type'] === 'aggregate') !== ($entry['scope'] === 'season')) {
            throw new InvalidArgumentException("[{$key}]: the season scope takes an aggregate trigger, and an aggregate trigger the season scope.");
        }

        if ($recipient === RecipientType::Team && in_array($definition['type'], ['custom', 'aggregate'], true)) {
            throw new InvalidArgumentException("[{$key}]: a team achievement takes a match, count or extreme trigger.");
        }

        return match ($definition['type']) {
            'custom' => new CustomTrigger($this->container->make($definition['class'])),
            'match' => new MatchTrigger($key, $definition, $this->facts, $this->conditions, $weekendSessions, $recipient),
            'count' => new CountTrigger($key, $definition, $this->facts, $this->conditions, $weekendSessions, $recipient),
            'extreme' => new ExtremeTrigger($key, $definition, $this->facts, $this->conditions, $weekendSessions, $recipient),
            'aggregate' => new AggregateTrigger($key, $definition, $this->facts, $this->conditions, $sessions, $recipient),
            default => throw new InvalidArgumentException("Unknown trigger type [{$definition['type']}] for [{$key}]."),
        };
    }
}
