<?php

namespace App\Achievements\Facts;

use App\Achievements\SessionData;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * The named predicates a trigger's where list can use.
 */
class Predicates
{
    /**
     * @var array<string, class-string<Predicate>>
     */
    public const PREDICATES = [];

    /**
     * @var array<string, Predicate>
     */
    private array $predicates = [];

    public function __construct(private Container $container) {}

    public function has(string $name): bool
    {
        return array_key_exists($name, static::PREDICATES);
    }

    public function get(string $name): Predicate
    {
        $class = static::PREDICATES[$name] ?? throw new InvalidArgumentException("No predicate [{$name}].");

        return $this->predicates[$name] ??= $this->container->make($class);
    }

    public function test(string $name, Fact $fact, SessionData $data): bool
    {
        return $this->get($name)->test($fact, $data);
    }
}
