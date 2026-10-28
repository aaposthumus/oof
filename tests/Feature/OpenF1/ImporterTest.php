<?php

namespace Tests\Feature\OpenF1;

use App\Models\RawResponse;
use App\OpenF1\Importer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ImporterTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['openf1.min_request_interval_ms' => 0]);
    }

    /**
     * Fake OpenF1 with these 2026 sessions; every session endpoint returns one
     * row, except the ones listed as empty, which answer 404 "No results found.".
     *
     * @param  list<array<string, mixed>>  $sessions
     * @param  list<string>  $emptyEndpoints
     */
    private function fakeOpenF1(array $sessions, array $emptyEndpoints = []): void
    {
        Http::preventStrayRequests();
        Http::fake(['api.openf1.org/v1/*' => function (Request $request) use ($sessions, $emptyEndpoints) {
            $endpoint = basename(parse_url($request->url(), PHP_URL_PATH));

            return match (true) {
                $endpoint === 'sessions' => Http::response($sessions),
                $endpoint === 'meetings' => Http::response([['meeting_key' => 1297, 'year' => 2026]]),
                in_array($endpoint, $emptyEndpoints, true) => Http::response(['detail' => 'No results found.'], 404),
                default => Http::response([['session_key' => $request->data()['session_key']]]),
            };
        }]);
    }

    /**
     * @return array<string, mixed>
     */
    private function openF1Session(int $key, string $name, string $end, bool $cancelled = false): array
    {
        return ['session_key' => $key, 'meeting_key' => 1297, 'year' => 2026, 'session_name' => $name, 'date_start' => $end, 'date_end' => $end, 'is_cancelled' => $cancelled];
    }

    private function requestsFor(string $endpoint, int $sessionKey): int
    {
        return Http::recorded(fn (Request $request) => str_contains($request->url(), "/v1/{$endpoint}?session_key={$sessionKey}"))->count();
    }

    public function test_stores_every_endpoint_of_each_ended_session_under_its_request_key(): void
    {
        $this->travelTo('2026-10-26 12:00:00');
        $this->fakeOpenF1([$this->openF1Session(11392, 'Qualifying', '2026-10-24T22:00:00+00:00'), $this->openF1Session(11396, 'Race', '2026-10-25T22:00:00+00:00')]);

        app(Importer::class)->import([2026]);

        $keys = RawResponse::pluck('request_key');

        $this->assertContains('openf1:meetings:2026', $keys);
        $this->assertContains('openf1:sessions:2026', $keys);
        $this->assertContains('openf1:laps:11396', $keys);
        $this->assertContains('openf1:starting_grid:11392', $keys);
        $this->assertNotContains('openf1:starting_grid:11396', $keys);
        $this->assertCount(2 + 9 + 10, $keys);
        $this->assertSame(['year' => 2026, 'meeting_key' => 1297, 'session_key' => 11396], RawResponse::firstWhere('request_key', 'openf1:laps:11396')->parameters);
        $this->assertSame('openf1', RawResponse::firstWhere('request_key', 'openf1:laps:11396')->source);
    }

    public function test_never_fetches_car_data_location_or_intervals(): void
    {
        $this->travelTo('2026-10-26 12:00:00');
        $this->fakeOpenF1([$this->openF1Session(11396, 'Race', '2026-10-25T22:00:00+00:00')]);

        app(Importer::class)->import([2026]);

        Http::assertNotSent(fn (Request $request) => preg_match('#/v1/(car_data|location|intervals)\?#', $request->url()) === 1);
    }

    public function test_stores_an_empty_result_as_an_empty_payload(): void
    {
        $this->travelTo('2026-10-26 12:00:00');
        $this->fakeOpenF1([$this->openF1Session(11396, 'Race', '2026-10-25T22:00:00+00:00')], ['team_radio']);

        app(Importer::class)->import([2026]);

        $radio = RawResponse::firstWhere('request_key', 'openf1:team_radio:11396');

        $this->assertSame([], $radio->payload);
        $this->assertSame(404, $radio->http_status);
        $this->assertSame(0, $radio->total_rows);
    }

    public function test_skips_sessions_that_have_not_ended_are_cancelled_or_testing(): void
    {
        $this->travelTo('2026-10-26 12:00:00');
        $this->fakeOpenF1([
            $this->openF1Session(11400, 'Practice 1', '2026-10-30T19:00:00+00:00'),
            $this->openF1Session(11401, 'Race', '2026-10-26T11:45:00+00:00'),
            $this->openF1Session(11254, 'Practice 1', '2026-04-10T12:00:00+00:00', cancelled: true),
            $this->openF1Session(11465, 'Day 1', '2026-02-11T16:00:00+00:00'),
        ]);

        app(Importer::class)->import([2026]);

        $this->assertSame(2, RawResponse::count());
    }

    public function test_fetches_a_session_again_at_each_moment_after_it_ended_and_then_never(): void
    {
        $this->fakeOpenF1([$this->openF1Session(11396, 'Race', '2026-10-25T22:00:00+00:00')]);

        foreach (['2026-10-25 22:40', '2026-10-25 23:40', '2026-10-26 01:10', '2026-10-27 09:00', '2026-10-29 09:00', '2026-11-05 09:00'] as $moment) {
            $this->travelTo($moment);
            app(Importer::class)->import([2026]);
        }

        // At 30 min, 3 h, 24 h and 72 h after the end; not again at 1h40 or a week later.
        $this->assertSame(4, $this->requestsFor('laps', 11396));
    }

    public function test_only_imports_the_sessions_of_one_meeting_when_asked(): void
    {
        $this->travelTo('2026-10-26 12:00:00');
        $other = ['meeting_key' => 1296] + $this->openF1Session(11380, 'Race', '2026-10-11T14:00:00+00:00');
        $this->fakeOpenF1([$other, $this->openF1Session(11396, 'Race', '2026-10-25T22:00:00+00:00')]);

        app(Importer::class)->import([2026], meetingKey: 1297);

        $this->assertSame(1, $this->requestsFor('laps', 11396));
        $this->assertSame(0, $this->requestsFor('laps', 11380));
    }

    public function test_dry_run_counts_requests_without_fetching(): void
    {
        Http::preventStrayRequests();

        $report = app(Importer::class)->import([2026], dryRun: true);

        $this->assertSame(['fetched' => 0, 'skipped' => 0, 'planned' => 2, 'unknown_seasons' => 1], $report);
        Http::assertNothingSent();
    }
}
