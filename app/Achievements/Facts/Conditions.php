<?php

namespace App\Achievements\Facts;

use App\Achievements\SessionData;
use InvalidArgumentException;

/**
 * A trigger's where list: every item must hold. An item is a predicate
 * name, or [attribute, operator, value] with one of the operators below.
 */
class Conditions
{
    public const OPERATORS = ['=', '!=', '<', '<=', '>', '>=', 'in', 'not_in', 'matches'];

    public function __construct(private Predicates $predicates) {}

    /**
     * @param  list<string|array{0: string, 1: string, 2: mixed}>  $where
     */
    public function pass(array $where, Fact $fact, SessionData $data): bool
    {
        foreach ($where as $condition) {
            $holds = is_string($condition)
                ? $this->predicates->test($condition, $fact, $data)
                : self::compare($fact->get($condition[0]), $condition[1], $condition[2]);

            if (! $holds) {
                return false;
            }
        }

        return true;
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
     * The predicates a where list names.
     *
     * @param  list<string|array<int, mixed>>  $where
     * @return list<string>
     */
    public static function predicateNames(array $where): array
    {
        return array_values(array_filter($where, 'is_string'));
    }
}
