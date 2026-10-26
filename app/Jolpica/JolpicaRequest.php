<?php

namespace App\Jolpica;

/**
 * One page of one Jolpica (Ergast-compatible) endpoint.
 */
final readonly class JolpicaRequest
{
    public const SOURCE = 'jolpica';

    public function __construct(
        public string $endpoint,
        public ?int $season = null,
        public ?int $round = null,
        public int $offset = 0,
        public int $limit = 100,
    ) {}

    public function withOffset(int $offset): self
    {
        return new self($this->endpoint, $this->season, $this->round, $offset, $this->limit);
    }

    /**
     * Path relative to the API base, e.g. "2024/5/pitstops.json".
     */
    public function path(): string
    {
        $segments = array_filter([$this->season, $this->round], fn (?int $segment) => $segment !== null);

        return implode('/', [...$segments, $this->endpoint]).'.json';
    }

    /**
     * @return array{limit: int, offset: int}
     */
    public function query(): array
    {
        return ['limit' => $this->limit, 'offset' => $this->offset];
    }

    public function url(): string
    {
        return $this->path().'?'.http_build_query($this->query());
    }

    /**
     * The unique key a stored response is found by, e.g. "jolpica:results:2024:-:100".
     */
    public function requestKey(): string
    {
        return implode(':', [self::SOURCE, $this->endpoint, $this->season ?? '-', $this->round ?? '-', $this->offset]);
    }

    /**
     * @return array{season: ?int, round: ?int, limit: int, offset: int}
     */
    public function parameters(): array
    {
        return ['season' => $this->season, 'round' => $this->round, ...$this->query()];
    }
}
