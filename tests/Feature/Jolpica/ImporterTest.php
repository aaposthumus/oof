<?php

namespace Tests\Feature\Jolpica;

use App\Jolpica\Importer;
use App\Models\RawResponse;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ImporterTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['jolpica.min_request_interval_ms' => 0]);
    }

    /**
     * Fake the Jolpica API with minimal payloads for the given schedules.
     *
     * @param  array<int, array<int, string>>  $schedules  Race dates by season and round
     * @param  array<string, int>  $totals  Row totals by path, for pagination
     */
    private function fakeJolpica(array $schedules, array $totals = []): void
    {
        Http::preventStrayRequests();
        Http::fake(['api.jolpi.ca/ergast/f1/*' => function (Request $request) use ($schedules, $totals) {
            $path = Str::after(parse_url($request->url(), PHP_URL_PATH), '/ergast/f1/');
            $meta = ['offset' => (string) ($request->data()['offset'] ?? 0), 'total' => (string) ($totals[$path] ?? 1)];

            if ($path === 'seasons.json') {
                $seasons = array_map(fn (int $year) => ['season' => (string) $year], array_keys($schedules));

                return Http::response(['MRData' => [...$meta, 'SeasonTable' => ['Seasons' => $seasons]]]);
            }

            if (preg_match('#^(\d{4})/races\.json$#', $path, $matches) === 1) {
                $races = [];

                foreach ($schedules[(int) $matches[1]] as $round => $date) {
                    $races[] = ['season' => $matches[1], 'round' => (string) $round, 'date' => $date];
                }

                return Http::response(['MRData' => [...$meta, 'RaceTable' => ['Races' => $races]]]);
            }

            return Http::response(['MRData' => [...$meta, 'RaceTable' => ['Races' => []]]]);
        }]);
    }

    private function requestsTo(string $path): int
    {
        return Http::recorded(fn (Request $request) => str_contains($request->url(), "/ergast/f1/{$path}?"))->count();
    }

    public function test_stores_every_page_of_every_endpoint_under_its_request_key(): void
    {
        $this->travelTo('2026-10-26 12:00:00');
        $this->fakeJolpica([2024 => [1 => '2024-03-02']], ['2024/results.json' => 150]);

        app(Importer::class)->import([2024]);

        $this->assertEqualsCanonicalizing([
            'jolpica:seasons:-:-:0',
            'jolpica:races:2024:-:0',
            'jolpica:results:2024:-:0',
            'jolpica:results:2024:-:100',
            'jolpica:qualifying:2024:-:0',
            'jolpica:driverstandings:2024:1:0',
            'jolpica:constructorstandings:2024:1:0',
            'jolpica:pitstops:2024:1:0',
        ], RawResponse::pluck('request_key')->all());
        $this->assertSame(150, RawResponse::firstWhere('request_key', 'jolpica:results:2024:-:100')->total_rows);
    }

    public function test_rerun_skips_responses_that_are_final(): void
    {
        $this->travelTo('2026-10-26 12:00:00');
        $this->fakeJolpica([2024 => [1 => '2024-03-02']]);

        app(Importer::class)->import([2024]);
        $report = app(Importer::class)->import([2024]);

        $this->assertSame(['fetched' => 0, 'skipped' => 7, 'planned' => 0], $report);
        Http::assertSentCount(7);
    }

    public function test_fetches_a_recent_round_again_until_it_is_final(): void
    {
        $this->travelTo('2026-10-26 12:00:00');
        $this->fakeJolpica([2026 => [1 => '2026-10-25', 2 => '2026-11-01']]);

        app(Importer::class)->import([2026]);
        $this->travelTo('2026-10-28 12:00:00'); // three days after the race: this fetch is final
        app(Importer::class)->import([2026]);
        $this->travelTo('2026-10-29 12:00:00');
        app(Importer::class)->import([2026]);

        $this->assertSame(2, $this->requestsTo('2026/1/driverstandings.json'));
    }

    public function test_does_not_request_rounds_that_have_not_taken_place(): void
    {
        $this->travelTo('2026-10-26 12:00:00');
        $this->fakeJolpica([2026 => [1 => '2026-10-25', 2 => '2026-11-01']]);

        app(Importer::class)->import([2026]);

        $this->assertSame(1, $this->requestsTo('2026/1/driverstandings.json'));
        $this->assertSame(0, $this->requestsTo('2026/2/driverstandings.json'));
    }

    public function test_imports_lap_times_only_when_asked(): void
    {
        $this->travelTo('2026-10-26 12:00:00');
        $this->fakeJolpica([2024 => [1 => '2024-03-02']]);

        app(Importer::class)->import([2024]);
        $this->assertSame(0, $this->requestsTo('2024/1/laps.json'));

        app(Importer::class)->import([2024], onlyLaps: true);
        $this->assertSame(1, $this->requestsTo('2024/1/laps.json'));
        $this->assertSame(1, $this->requestsTo('2024/results.json'));
    }

    public function test_dry_run_counts_requests_without_fetching(): void
    {
        Http::preventStrayRequests();

        $report = app(Importer::class)->import([2024], dryRun: true);

        $this->assertSame(['fetched' => 0, 'skipped' => 0, 'planned' => 2], $report);
        Http::assertNothingSent();
    }
}
