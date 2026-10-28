<?php

namespace App\OpenF1;

/**
 * One OpenF1 endpoint, either for a season (meetings, sessions) or for a session.
 */
final readonly class OpenF1Request
{
    public const SOURCE = 'openf1';

    public function __construct(
        public string $endpoint,
        public int $year,
        public ?int $sessionKey = null,
        public ?int $meetingKey = null,
    ) {}

    public static function forSeason(string $endpoint, int $year): self
    {
        return new self($endpoint, $year);
    }

    /**
     * @param  array{session_key: int, meeting_key: int, year: int}  $session  An OpenF1 session
     */
    public static function forSession(string $endpoint, array $session): self
    {
        return new self($endpoint, $session['year'], $session['session_key'], $session['meeting_key']);
    }

    /**
     * @return array{year?: int, session_key?: int}
     */
    public function query(): array
    {
        return $this->sessionKey === null ? ['year' => $this->year] : ['session_key' => $this->sessionKey];
    }

    public function url(): string
    {
        return $this->endpoint.'?'.http_build_query($this->query());
    }

    /**
     * The unique key a stored response is found by, e.g. "openf1:laps:11396" or "openf1:sessions:2026".
     */
    public function requestKey(): string
    {
        return implode(':', [self::SOURCE, $this->endpoint, $this->sessionKey ?? $this->year]);
    }

    /**
     * @return array{year: int, meeting_key: ?int, session_key: ?int}
     */
    public function parameters(): array
    {
        return ['year' => $this->year, 'meeting_key' => $this->meetingKey, 'session_key' => $this->sessionKey];
    }
}
