<?php

namespace App\Achievements\Triggers;

use App\Achievements\Catalogue;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * Builds each achievement's trigger from its catalogue entry.
 */
class TriggerFactory
{
    /**
     * @var array<string, Trigger>
     */
    private array $built = [];

    public function __construct(private Container $container, private Catalogue $catalogue) {}

    /**
     * @return array<string, Trigger> Keyed by achievement key, in run order
     */
    public function all(): array
    {
        return collect($this->catalogue->keys())->mapWithKeys(fn (string $key) => [$key => $this->for($key)])->all();
    }

    public function for(string $key): Trigger
    {
        return $this->built[$key] ??= $this->make($key, $this->catalogue->trigger($key));
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function make(string $key, array $definition): Trigger
    {
        return match ($definition['type']) {
            'custom' => new CustomTrigger($this->container->make($definition['class'])),
            default => throw new InvalidArgumentException("Unknown trigger type [{$definition['type']}] for [{$key}]."),
        };
    }
}
