<?php

namespace Tests\Feature\Normalizers;

use App\Models\DriverStanding;
use App\Models\Result;
use App\Models\SeasonDriver;
use App\Normalizers\NormalizationRunner;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\NormalizesJolpicaFixtures;
use Tests\TestCase;

class NormalizationRunnerTest extends TestCase
{
    use LazilyRefreshDatabase, NormalizesJolpicaFixtures;

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
}
