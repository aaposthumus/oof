<?php

namespace Tests\Feature\Console\Commands;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\NormalizesJolpicaFixtures;
use Tests\TestCase;

class OofStatusTest extends TestCase
{
    use LazilyRefreshDatabase, NormalizesJolpicaFixtures;

    public function test_reports_empty_database(): void
    {
        $this->artisan('oof:status')
            ->expectsOutputToContain('never')
            ->expectsOutputToContain('none')
            ->assertSuccessful();
    }

    public function test_reports_pending_and_normalized_responses_and_latest_round(): void
    {
        $this->storeRawFixture('results_2024_page_0', 'results', 2024);
        $this->artisan('oof:normalize')->assertSuccessful();

        $this->storeRawFixture('results_2024_page_0', 'results', 2024, offset: 30);

        $this->artisan('oof:status')
            ->expectsOutputToContain('Pending')
            ->expectsOutputToContain('2024 round')
            ->assertSuccessful();

        $this->assertDatabaseCount('raw_responses', 2);
    }
}
