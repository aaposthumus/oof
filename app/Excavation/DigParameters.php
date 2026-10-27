<?php

namespace App\Excavation;

use Illuminate\Contracts\Database\Query\Builder;

/**
 * Which seasons to dig in. Empty means all of them.
 */
final readonly class DigParameters
{
    /**
     * @param  list<int>  $seasons
     */
    public function __construct(
        public array $seasons = [],
        public ?int $from = null,
        public ?int $to = null,
    ) {}

    /**
     * Limit a query to the selected seasons.
     */
    public function applyTo(Builder $query, string $yearColumn = 'seasons.year'): Builder
    {
        return $query
            ->when($this->seasons !== [], fn (Builder $query) => $query->whereIn($yearColumn, $this->seasons))
            ->when($this->from !== null, fn (Builder $query) => $query->where($yearColumn, '>=', $this->from))
            ->when($this->to !== null, fn (Builder $query) => $query->where($yearColumn, '<=', $this->to));
    }

    /**
     * Whether a season is selected, for excavators that look at the full history.
     */
    public function includes(int $year): bool
    {
        return ($this->seasons === [] || in_array($year, $this->seasons, true))
            && ($this->from === null || $year >= $this->from)
            && ($this->to === null || $year <= $this->to);
    }

    /**
     * @return array{seasons: list<int>, from: int|null, to: int|null}
     */
    public function toArray(): array
    {
        return ['seasons' => $this->seasons, 'from' => $this->from, 'to' => $this->to];
    }
}
