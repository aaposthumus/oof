<?php

namespace Tests\Feature\Console\Commands;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class OofImportTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_dry_run_reports_the_requests_needed(): void
    {
        Http::preventStrayRequests();

        $this->artisan('oof:import', ['--season' => [2024], '--dry-run' => true])
            ->expectsOutputToContain('2 requests to fetch')
            ->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_fails_with_resume_hint_when_the_api_keeps_failing(): void
    {
        config(['jolpica.retry_backoff_seconds' => [], 'jolpica.min_request_interval_ms' => 0]);
        Http::preventStrayRequests();
        Sleep::fake();
        Http::fake(['api.jolpi.ca/ergast/f1/*' => Http::response('Unavailable', 503)]);

        $this->artisan('oof:import', ['--season' => [2024]])
            ->expectsOutputToContain('Run the command again to resume')
            ->assertFailed();
    }

    public function test_dry_run_of_openf1_counts_its_season_listings(): void
    {
        Http::preventStrayRequests();

        $this->artisan('oof:import', ['--source' => 'openf1', '--season' => [2026], '--dry-run' => true])
            ->expectsOutputToContain('OpenF1: 2 requests to fetch')
            ->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_rejects_an_unknown_source_and_options_of_the_other_source(): void
    {
        $this->artisan('oof:import', ['--source' => 'ergast'])->assertFailed();
        $this->artisan('oof:import', ['--source' => 'openf1', '--laps' => true])->assertFailed();
        $this->artisan('oof:import', ['--meeting' => 1297])->assertFailed();
    }
}
