<?php

namespace App\Achievements\Facts;

use App\Achievements\SessionData;
use InvalidArgumentException;

/**
 * A trigger's where list: every item must hold. An item is a predicate
 * name, [attribute, operator, value] with one of the operators below, or
 * ['any' => [items]], which holds when one of its items does.
 */
class Conditions
{
    public const OPERATORS = ['=', '!=', '<', '<=', '>', '>=', 'in', 'not_in', 'matches'];

    public function __construct(private Predicates $predicates) {}

    /**
     * @param  list<string|array<int|string, mixed>>  $where
     */
    public function pass(array $where, Fact $fact, SessionData $data): bool
    {
        foreach ($where as $condition) {
            if (! $this->holds($condition, $fact, $data)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  string|array<int|string, mixed>  $condition
     */
    private function holds(string|array $condition, Fact $fact, SessionData $data): bool
    {
        return match (true) {
            is_string($condition) => $this->predicates->test($condition, $fact, $data),
            isset($condition['any']) => collect($condition['any'])->contains(fn (string|array $item) => $this->holds($item, $fact, $data)),
            default => self::compare($fact->get($condition[0]), $condition[1], $condition[2]),
        };
    }

    /**
     * Strict comparison; an empty value never passes an ordering or a pattern.
     */
    public static function compare(mixed $actual, string $operator, mixed $expected): bool
    {
        return match ($operator) {
            '=' => $actual === $expected,
            '!=' => $actual !== $expected,
            '<' => $actual !== null && $actual < $expected,
            '<=' => $actual !== null && $actual <= $expected,
            '>' => $actual !== null && $actual > $expected,
            '>=' => $actual !== null && $actual >= $expected,
            'in' => in_array($actual, $expected, true),
            'not_in' => ! in_array($actual, $expected, true),
            'matches' => is_string($actual) && preg_match($expected, $actual) === 1,
            default => throw new InvalidArgumentException("Unknown operator [{$operator}]."),
        };
    }

    /**
     * The fact attributes a where list compares.
     *
     * @param  list<string|array<int|string, mixed>>  $where
     * @return list<string>
     */
    public static function attributeNames(array $where): array
    {
        return collect($where)
            ->flatMap(fn (string|array $condition) => match (true) {
                is_string($condition) => [],
                isset($condition['any']) => self::attributeNames($condition['any']),
                default => [explode('.', $condition[0])[0]],
            })
            ->unique()
            ->values()
            ->all();
    }

    /**
     * The predicates a where list names.
     *
     * @param  list<string|array<int|string, mixed>>  $where
     * @return list<string>
     */
    public static function predicateNames(array $where): array
    {
        return collect($where)
            ->flatMap(fn (string|array $condition) => match (true) {
                is_string($condition) => [$condition],
                isset($condition['any']) => self::predicateNames($condition['any']),
                default => [],
            })
            ->unique()
            ->values()
            ->all();
    }
}
