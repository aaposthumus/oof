<?php

namespace Tests\Feature\Console\Commands;

use App\Achievements\GoldenMaster;
use App\Enums\SessionType;
use App\Models\DriverEntry;
use App\Models\RaceSession;
use App\Models\Result;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsSessions;
use Tests\TestCase;

class OofAchievementsGoldenTest extends TestCase
{
    use BuildsSessions, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->catalogue();
        Date::setTestNow('2024-06-01 12:00:00');
        Storage::fake('local');
    }

    /**
     * A race with a winner, a runner-up and a car one lap down.
     *
     * @return array{0: DriverEntry, 1: DriverEntry, 2: DriverEntry}
     */
    private function race(?RaceSession $race = null): array
    {
        $race ??= $this->raceSession();
        $winner = $this->car($race, 1);
        $runnerUp = $this->car($race, 4);
        $lapped = $this->car($race, 44);
        Result::factory()->for($winner)->classified(1)->create();
        Result::factory()->for($runnerUp)->classified(2)->create(['grid' => 2]);
        Result::factory()->for($lapped)->classified(9, '+1 Lap')->create(['grid' => 9]);

        return [$winner, $runnerUp, $lapped];
    }

    private function export(): void
    {
        $this->artisan('oof:achievements:golden', ['--export' => true])->assertSuccessful();
    }

    public function test_exports_what_the_engine_gives_without_storing_any_award(): void
    {
        [, , $lapped] = $this->race();

        $this->artisan('oof:achievements:golden', ['--export' => true])
            ->expectsOutputToContain('Golden master written')
            ->assertSuccessful();

        $master = app(GoldenMaster::class)->read();
        $award = $master['awards']["2024-05-race|lapped_dog|e{$lapped->id}:result"];

        $this->assertSame(['2024-05-race'], $master['sessions']);
        $this->assertSame($lapped->driver->jolpica_driver_id, $award['driver']);
        $this->assertSame(44, $award['car']);
        $this->assertSame('1 lap', $award['detail']);
        $this->assertSame('final', $award['status']);
        $this->assertDatabaseCount('achievement_awards', 0);
        $this->assertNull($lapped->raceSession->fresh()->achievements_checked_at);
    }

    public function test_finds_no_differences_when_the_engine_gives_the_same_awards(): void
    {
        $this->race();
        $this->export();

        $this->artisan('oof:achievements:golden')
            ->expectsOutputToContain('No differences.')
            ->assertSuccessful();
    }

    public function test_reports_awards_that_went_missing_were_added_or_changed(): void
    {
        [$winner, , $lapped] = $this->race();
        $winner->result->update(['grid' => 12]);
        $this->export();

        $lapped->result->update(['status' => '+2 Laps']);
        $winner->result->update(['grid' => 14]);

        $this->artisan('oof:achievements:golden')
            ->expectsOutputToContain("missing  2024-05-race|lapped_dog|e{$lapped->id}:result")
            ->expectsOutputToContain("added    2024-05-race|good_boy|e{$lapped->id}:result")
            ->expectsOutputToContain("changed  2024-05-race|took_the_scenic_route|e{$winner->id}:result  detail: \"from P12\" -> \"from P14\"")
            ->assertFailed();

        $this->assertDatabaseCount('achievement_awards', 0);
    }

    public function test_reports_an_award_whose_evidence_alone_changed(): void
    {
        [, , $lapped] = $this->race();
        $this->export();

        $lapped->result->update(['fastest_lap_rank' => 3]);

        $this->artisan('oof:achievements:golden')
            ->expectsOutputToContain("changed  2024-05-race|lapped_dog|e{$lapped->id}:result  evidence:")
            ->assertFailed();
    }

    public function test_lists_no_more_differences_than_the_limit(): void
    {
        [, , $lapped] = $this->race();
        $this->export();

        $lapped->result->update(['status' => '+2 Laps']);

        $this->artisan('oof:achievements:golden', ['--limit' => 1])
            ->expectsOutputToContain('missing  2024-05-race|lapped_dog')
            ->doesntExpectOutputToContain('added    2024-05-race|good_boy')
            ->expectsOutputToContain('more (--limit=0 lists them all)')
            ->assertFailed();
    }

    public function test_leaves_sessions_first_run_after_the_export_out_of_the_comparison(): void
    {
        $this->race();
        $this->export();

        // A later season, because an earlier race would change the team form the exported race reads.
        Date::setTestNow('2025-06-01 12:00:00');
        $this->race($this->raceSession(SessionType::Race, $this->round(2025)));

        $this->artisan('oof:achievements:golden')
            ->expectsOutputToContain('1 sessions were first run after the export and are not compared (2025-05-race to 2025-05-race).')
            ->expectsOutputToContain('No differences.')
            ->assertSuccessful();
    }

    public function test_only_compares_the_seasons_asked_for(): void
    {
        $this->race();
        [, , $lapped] = $this->race($this->raceSession(SessionType::Race, $this->round(2023)));
        $this->export();

        $lapped->result->update(['status' => '+2 Laps']);

        $this->artisan('oof:achievements:golden', ['--season' => [2024]])
            ->expectsOutputToContain('No differences.')
            ->assertSuccessful();

        $this->artisan('oof:achievements:golden', ['--season' => [2023]])
            ->expectsOutputToContain('missing  2023-05-race|lapped_dog')
            ->assertFailed();
    }

    public function test_fails_without_a_golden_master(): void
    {
        $this->artisan('oof:achievements:golden')
            ->expectsOutputToContain('There is no golden master yet')
            ->assertFailed();
    }

    public function test_only_replaces_a_golden_master_when_forced(): void
    {
        [, , $lapped] = $this->race();
        $this->export();

        $lapped->result->update(['status' => '+2 Laps']);

        $this->artisan('oof:achievements:golden', ['--export' => true])
            ->expectsOutputToContain('A golden master exists already')
            ->assertFailed();
        $this->assertArrayHasKey("2024-05-race|lapped_dog|e{$lapped->id}:result", app(GoldenMaster::class)->read()['awards']);

        $this->artisan('oof:achievements:golden', ['--export' => true, '--force' => true])->assertSuccessful();
        $this->assertArrayHasKey("2024-05-race|good_boy|e{$lapped->id}:result", app(GoldenMaster::class)->read()['awards']);
    }

    public function test_never_exports_a_single_season(): void
    {
        $this->race();

        $this->artisan('oof:achievements:golden', ['--export' => true, '--season' => [2024]])
            ->expectsOutputToContain('export without --season')
            ->assertFailed();

        Storage::disk('local')->assertMissing(GoldenMaster::PATH);
    }
}
