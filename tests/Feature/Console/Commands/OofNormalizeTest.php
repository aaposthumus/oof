<?php

namespace Tests\Feature\Console\Commands;

use App\Models\Result;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\NormalizesJolpicaFixtures;
use Tests\TestCase;

class OofNormalizeTest extends TestCase
{
    use LazilyRefreshDatabase, NormalizesJolpicaFixtures;

    public function test_normalizes_stored_responses_and_reports_counts(): void
    {
        $this->storeRawFixture('results_2024_page_0', 'results', 2024);

        $this->artisan('oof:normalize')
            ->expectsOutputToContain('1 normalized')
            ->assertSuccessful();

        $this->assertSame(2, Result::count());
    }
}
