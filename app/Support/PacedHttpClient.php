<?php

namespace App\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/**
 * HTTP client that stays under an API's rate limits: it waits a minimum
 * interval between requests and backs off on 429 and server errors.
 */
class PacedHttpClient
{
    private ?int $lastRequestAt = null;

    /**
     * @param  array{base_url: string, user_agent: string, min_request_interval_ms: int, connect_timeout: int, timeout: int, retry_backoff_seconds: list<int>}  $config
     */
    public function __construct(private array $config) {}

    /**
     * @param  array<string, mixed>  $query
     * @param  list<int>  $acceptedStatuses  Error statuses returned as they are instead of thrown
     *
     * @throws RequestException|ConnectionException when retries are exhausted
     */
    public function get(string $path, array $query = [], array $acceptedStatuses = []): Response
    {
        $backoff = $this->config['retry_backoff_seconds'];

        for ($attempt = 0; ; $attempt++) {
            $this->throttle();
            $canRetry = $attempt < count($backoff);

            try {
                $response = $this->http()->get($path, $query);
            } catch (ConnectionException $exception) {
                if (! $canRetry) {
                    throw $exception;
                }

                Sleep::for($backoff[$attempt])->seconds();

                continue;
            }

            if ($response->successful() || in_array($response->status(), $acceptedStatuses, true)) {
                return $response;
            }

            if ($canRetry && ($response->status() === 429 || $response->serverError())) {
                Sleep::for($this->retryAfter($response) ?? $backoff[$attempt])->seconds();

                continue;
            }

            $response->throw();
        }
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl($this->config['base_url'])
            ->withUserAgent($this->config['user_agent'])
            ->acceptJson()
            ->connectTimeout($this->config['connect_timeout'])
            ->timeout($this->config['timeout']);
    }

    private function throttle(): void
    {
        $intervalMs = $this->config['min_request_interval_ms'];

        if ($this->lastRequestAt !== null) {
            $elapsedMs = intdiv(hrtime(true) - $this->lastRequestAt, 1_000_000);

            if ($elapsedMs < $intervalMs) {
                Sleep::for($intervalMs - $elapsedMs)->milliseconds();
            }
        }

        $this->lastRequestAt = hrtime(true);
    }

    private function retryAfter(Response $response): ?int
    {
        $header = $response->header('Retry-After');

        return ctype_digit($header) ? (int) $header : null;
    }
}
