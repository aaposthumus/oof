<?php

namespace Tests\Feature\Console\Commands;

use App\Normalizers\OpenF1\RaceControlNormalizer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\NormalizesOpenF1Fixtures;
use Tests\TestCase;

class OofDiscoverTest extends TestCase
{
    use LazilyRefreshDatabase, NormalizesOpenF1Fixtures;

    public function test_writes_a_report_of_types_per_season_and_unmatched_wordings(): void
    {
        Storage::fake('local');
        $this->travelTo('2026-10-28 12:00:00');
        $this->austinRace();
        $messages = $this->openF1Fixture('race_control_11396');
        $messages[] = ['message' => 'LAP TIME OF CAR 87 (BEA) - UNDER REVIEW', 'category' => 'Other', 'flag' => null, 'date' => '2026-10-25T19:30:00+00:00'] + $messages[0];
        app(RaceControlNormalizer::class)->normalize($messages);

        $this->artisan('oof:discover')
            ->expectsOutputToContain('Time penalty: 10 s')
            ->expectsOutputToContain('1 unmatched in 1 wordings')
            ->assertSuccessful();

        $report = Storage::disk('local')->get('reports/race-control-discovery-2026-10-28.md');

        $this->assertStringContainsString('| Time penalty: 5 s | 1 | 1 | 0 | 0 | 0 | 1 |', $report);
        $this->assertStringContainsString('### Blue flag (1)', $report);
        $this->assertStringContainsString('- `WAVED BLUE FLAG FOR CAR 87 (BEA) TIMED AT 15:43:02 (2026-18 race)`', $report);
        $this->assertStringContainsString('| CAUSING A COLLISION | Causing a collision | 4 |', $report);
        $this->assertStringContainsString('| Causing a collision | 4 | ', $report);
        $this->assertStringContainsString('| 1 | 2026 | `LAP TIME OF CAR <CAR> - UNDER REVIEW` |', $report);
    }

    public function test_reports_nothing_without_messages(): void
    {
        Storage::fake('local');

        $this->artisan('oof:discover')->expectsOutputToContain('0 messages')->assertSuccessful();
    }
}
