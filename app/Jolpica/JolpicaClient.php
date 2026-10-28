<?php

namespace App\Jolpica;

use App\Support\PacedHttpClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;

/**
 * HTTP client for the Jolpica API that stays under its rate limits: it waits a
 * minimum interval between requests and backs off on 429 and server errors.
 */
class JolpicaClient
{
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
    public function get(JolpicaRequest $request): Response
    {
        return $this->http->get($request->path(), $request->query());
    }
}
