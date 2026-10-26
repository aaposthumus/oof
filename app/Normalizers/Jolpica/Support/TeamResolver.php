<?php

namespace App\Normalizers\Jolpica\Support;

use LogicException;

/**
 * Maps a Jolpica constructor in a given season to the slug of its lasting team.
 */
class TeamResolver
{
    /**
     * @param  list<array{constructor: string, team: string, from?: int, to?: int}>  $lineage
     */
    public function __construct(private array $lineage) {}

    public function slugFor(string $constructorId, int $year): string
    {
        foreach ($this->lineage as $rule) {
            if ($rule['constructor'] === $constructorId
                && $year >= ($rule['from'] ?? PHP_INT_MIN)
                && $year <= ($rule['to'] ?? PHP_INT_MAX)) {
                return $rule['team'];
            }
        }

        if (in_array($constructorId, array_column($this->lineage, 'team'), true)) {
            throw new LogicException("Constructor [{$constructorId}] in {$year} would merge into lineage team [{$constructorId}]; add a team_lineage rule for it.");
        }

        return $constructorId;
    }
}
