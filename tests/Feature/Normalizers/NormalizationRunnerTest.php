<?php

namespace Tests\Feature\Normalizers;

use App\Enums\OpenF1MatchStatus;
use App\Models\DriverStanding;
use App\Models\OpenF1SessionDriver;
use App\Models\RawResponse;
use App\Models\Result;
use App\Models\SeasonDriver;
use App\Normalizers\NormalizationRunner;
use App\Normalizers\OpenF1\DriversNormalizer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\NormalizesJolpicaFixtures;
use Tests\Concerns\NormalizesOpenF1Fixtures;
use Tests\TestCase;

class NormalizationRunnerTest extends TestCase
{
    use LazilyRefreshDatabase, NormalizesJolpicaFixtures, NormalizesOpenF1Fixtures;

    public function test_normalizes_pending_responses_and_skips_them_on_rerun(): void
    {
        $this->storeRawFixture('results_2024_page_0', 'results', 2024);
        $this->storeRawFixture('driverstandings_2024_5', 'driverstandings', 2024, 5);

        $first = app(NormalizationRunner::class)->run();
        $second = app(NormalizationRunner::class)->run();

        $this->assertSame(['normalized' => 1, 'deferred' => 0], $first['results']);
        $this->assertSame(['normalized' => 1, 'deferred' => 0], $first['driverstandings']);
        $this->assertSame(0, $second['results']['normalized'] + $second['driverstandings']['normalized']);
        $this->assertSame(2, DriverStanding::count());
        $this->assertSame(2, SeasonDriver::count());
    }

    public function test_defers_a_response_until_its_dependencies_are_normalized(): void
    {
        $standings = $this->storeRawFixture('driverstandings_2024_5', 'driverstandings', 2024, 5);

        $first = app(NormalizationRunner::class)->run();
        $this->storeRawFixture('results_2024_page_0', 'results', 2024);
        $second = app(NormalizationRunner::class)->run();

        $this->assertSame(1, $first['driverstandings']['deferred']);
        $this->assertSame(1, $second['driverstandings']['normalized']);
        $this->assertNotNull($standings->fresh()->normalized_at);
    }

    public function test_normalizes_again_a_response_that_was_fetched_again(): void
    {
        $results = $this->storeRawFixture('results_2024_page_0', 'results', 2024);
        app(NormalizationRunner::class)->run();
        Result::query()->update(['points' => 0]);

        $this->travel(1)->hour();
        $results->update(['fetched_at' => now()]);
        $stats = app(NormalizationRunner::class)->run();

        $this->assertSame(1, $stats['results']['normalized']);
        $this->assertSame('25.00', Result::where('position', 1)->sole()->points);
    }

    public function test_leaves_lap_times_alone_unless_asked(): void
    {
        $this->storeRawFixture('results_2024_page_0', 'results', 2024);
        $laps = $this->storeRawFixture('laps_2024_5', 'laps', 2024, 5);

        $stats = app(NormalizationRunner::class)->run();

        $this->assertArrayNotHasKey('laps', $stats);
        $this->assertNull($laps->fresh()->normalized_at);
    }

    public function test_matches_unmatched_openf1_cars_again_on_every_run(): void
    {
        $this->matchSeason2026();
        RawResponse::create([
            'source' => 'openf1', 'endpoint' => 'drivers', 'request_key' => 'openf1:drivers:11389', 'url' => 'https://api.openf1.org/v1/drivers',
            'parameters' => ['session_key' => 11389], 'season' => 2026, 'http_status' => 200, 'payload' => $this->openF1Fixture('drivers_11389'),
            'fetched_at' => now()->subMinute(), 'normalized_at' => now(),
        ]);
        OpenF1SessionDriver::query()->delete();

        $this->assertSame(['normalized' => 0, 'deferred' => 0], app(NormalizationRunner::class)->run(source: 'openf1')['openf1:drivers']);

        $this->normalizeOpenF1(DriversNormalizer::class, 'drivers_11389');
        OpenF1SessionDriver::query()->update(['driver_id' => null, 'driver_entry_id' => null, 'match_status' => OpenF1MatchStatus::Unmatched]);

        $stats = app(NormalizationRunner::class)->run(source: 'openf1');

        $this->assertSame(1, $stats['openf1:drivers']['normalized']);
        $this->assertSame(0, OpenF1SessionDriver::where('match_status', OpenF1MatchStatus::Unmatched)->count());
    }
}
