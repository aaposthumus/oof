<?php

namespace App\OpenF1;

use App\Support\PacedHttpClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;

/**
 * HTTP client for the OpenF1 API that stays under its rate limits. OpenF1
 * answers an empty result with 404 "No results found.", which is returned
 * as a response like any other.
 */
class OpenF1Client
{
    private const NO_RESULTS = 'No results found.';

    private PacedHttpClient $http;

    /**
     * @param  array{base_url: string, user_agent: string, min_request_interval_ms: int, connect_timeout: int, timeout: int, retry_backoff_seconds: list<int>}  $config
     */
    public function __construct(array $config)
    {
        $this->http = new PacedHttpClient($config);
    }

    /**
     * @throws RequestException|ConnectionException when retries are exhausted
     */
    public function get(OpenF1Request $request): Response
    {
        $response = $this->http->get($request->endpoint, $request->query(), acceptedStatuses: [404]);

        if ($response->status() === 404 && $response->json('detail') !== self::NO_RESULTS) {
            $response->throw();
        }

        return $response;
    }

    /**
     * The rows of a response; an empty result is an empty list.
     *
     * @return list<array<string, mixed>>
     */
    public static function rows(Response $response): array
    {
        return $response->status() === 404 ? [] : $response->json();
    }
}
