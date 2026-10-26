<?php

namespace Tests\Feature\Jolpica;

use App\Jolpica\JolpicaClient;
use App\Jolpica\JolpicaRequest;
use Carbon\CarbonInterval;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class JolpicaClientTest extends TestCase
{
    private const SEASONS_URL = 'api.jolpi.ca/ergast/f1/seasons.json*';

    private function client(int $intervalMs = 0, array $backoff = [15, 60]): JolpicaClient
    {
        return new JolpicaClient(['min_request_interval_ms' => $intervalMs, 'retry_backoff_seconds' => $backoff] + config('jolpica'));
    }

    public function test_sends_custom_user_agent_and_pagination(): void
    {
        Http::preventStrayRequests();
        Http::fake([self::SEASONS_URL => Http::response(['MRData' => []])]);

        $this->client()->get(new JolpicaRequest('seasons', offset: 100));

        Http::assertSent(fn (Request $request) => $request->header('User-Agent') === ['OOF/0.1']
            && $request->url() === 'https://api.jolpi.ca/ergast/f1/seasons.json?limit=100&offset=100');
    }

    public function test_retries_throttled_request_after_the_retry_after_delay(): void
    {
        Http::preventStrayRequests();
        Sleep::fake();
        Http::fake([self::SEASONS_URL => Http::sequence()
            ->push('Request was throttled', 429, ['Retry-After' => '30'])
            ->push(['MRData' => []])]);

        $response = $this->client()->get(new JolpicaRequest('seasons'));

        $this->assertTrue($response->successful());
        Sleep::assertSequence([Sleep::for(30)->seconds()]);
    }

    public function test_backs_off_on_server_errors_and_throws_when_retries_run_out(): void
    {
        Http::preventStrayRequests();
        Sleep::fake();
        Http::fake([self::SEASONS_URL => Http::response('Bad gateway', 502)]);

        try {
            $this->client(backoff: [15, 60])->get(new JolpicaRequest('seasons'));
            $this->fail('Expected the request to fail.');
        } catch (RequestException $exception) {
            $this->assertSame(502, $exception->response->status());
        }

        Http::assertSentCount(3);
        Sleep::assertSequence([Sleep::for(15)->seconds(), Sleep::for(60)->seconds()]);
    }

    public function test_waits_the_minimum_interval_between_requests(): void
    {
        Http::preventStrayRequests();
        Sleep::fake();
        Http::fake([self::SEASONS_URL => Http::response(['MRData' => []])]);
        $client = $this->client(intervalMs: 7500);

        $client->get(new JolpicaRequest('seasons'));
        $client->get(new JolpicaRequest('seasons'));

        Sleep::assertSlept(fn (CarbonInterval $wait) => $wait->totalMilliseconds > 7000 && $wait->totalMilliseconds <= 7500, 1);
    }
}
