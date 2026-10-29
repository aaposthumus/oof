<?php

namespace Tests\Feature\Console\Commands;

use App\Normalizers\OpenF1\DriversNormalizer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\NormalizesJolpicaFixtures;
use Tests\Concerns\NormalizesOpenF1Fixtures;
use Tests\TestCase;

class OofStatusTest extends TestCase
{
    use LazilyRefreshDatabase, NormalizesJolpicaFixtures, NormalizesOpenF1Fixtures;

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

    public function test_reports_openf1_match_counts_and_what_needs_a_look(): void
    {
        $this->austinRace();
        $this->normalizeOpenF1(DriversNormalizer::class, 'drivers_11389');

        $this->artisan('oof:status')
            ->expectsOutputToContain('OpenF1 matching')
            ->expectsOutputToContain('1 cancelled, 3 matched, 1 not_a_round')
            ->expectsOutputToContain('43 matched, 1 practice_only')
            ->doesntExpectOutputToContain('Unmatched or conflicting')
            ->assertSuccessful();
    }
}
