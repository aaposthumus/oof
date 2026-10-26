<?php

namespace App\Jolpica;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/**
 * HTTP client for the Jolpica API that stays under its rate limits: it waits a
 * minimum interval between requests and backs off on 429 and server errors.
 */
class JolpicaClient
{
    private ?int $lastRequestAt = null;

    /**
     * @param  array{base_url: string, user_agent: string, min_request_interval_ms: int, connect_timeout: int, timeout: int, retry_backoff_seconds: list<int>}  $config
     */
    public function __construct(private array $config) {}

    /**
     * @throws RequestException|ConnectionException when retries are exhausted
     */
    public function get(JolpicaRequest $request): Response
    {
        $backoff = $this->config['retry_backoff_seconds'];

        for ($attempt = 0; ; $attempt++) {
            $this->throttle();
            $canRetry = $attempt < count($backoff);

            try {
                $response = $this->http()->get($request->path(), $request->query());
            } catch (ConnectionException $exception) {
                if (! $canRetry) {
                    throw $exception;
                }

                Sleep::for($backoff[$attempt])->seconds();

                continue;
            }

            if ($response->successful()) {
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
