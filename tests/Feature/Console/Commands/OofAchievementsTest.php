<?php

namespace Tests\Feature\Console\Commands;

use App\Enums\SessionType;
use App\Models\AchievementAward;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Date;
use Tests\Concerns\BuildsSessions;
use Tests\TestCase;

class OofAchievementsTest extends TestCase
{
    use BuildsSessions, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->catalogue();
        Date::setTestNow('2024-06-01 12:00:00');
    }

    public function test_runs_a_weekend_with_the_race_last_and_lists_the_awards(): void
    {
        $race = $this->raceSession();
        $qualifying = $this->raceSession(SessionType::Qualifying, $race->round);
        $quiet = $this->car($race, 44);
        $penalised = $this->car($race, 16);
        $this->resultFor($quiet, ['position' => 1, 'position_text' => '1']);
        $this->resultFor($penalised, ['position' => 2, 'position_text' => '2', 'grid' => 2]);
        $this->car($qualifying, 16, null, $penalised->driver);
        $this->message($qualifying, 'FIA STEWARDS: 3 PLACE GRID PENALTY FOR CAR 16 (LEC) - CAUSING A COLLISION');

        $this->artisan('oof:achievements', ['--season' => [2024]])
            ->expectsOutputToContain('2 sessions')
            ->expectsOutputToContain('bumper_cars')
            ->assertSuccessful();

        // Bumper Cars in qualifying counts for the weekend, so only the winner stayed quiet.
        $this->assertSame([$quiet->id], AchievementAward::whereRelation('achievement', 'key', 'nothing_to_see_here')->pluck('driver_entry_id')->all());
    }

    public function test_a_dry_run_stores_nothing(): void
    {
        $race = $this->raceSession();
        $this->resultFor($this->car($race, 44), ['status' => '+1 Lap', 'position' => 9, 'position_text' => '9']);

        $this->artisan('oof:achievements', ['--dry-run' => true])
            ->expectsOutputToContain('Dry run')
            ->expectsOutputToContain('lapped_dog')
            ->assertSuccessful();

        $this->assertDatabaseCount('achievement_awards', 0);
    }

    public function test_only_runs_rounds_with_new_data_when_asked_for_changes(): void
    {
        $unchanged = $this->raceSession();
        $changed = $this->raceSession(SessionType::Race, $this->round(2024, 6, ['date' => '2024-05-19']));
        $this->resultFor($this->car($unchanged, 1), ['position' => 1, 'position_text' => '1']);
        $entry = $this->car($changed, 1);
        $this->artisan('oof:achievements')->assertSuccessful();

        Date::setTestNow('2024-06-02 12:00:00');
        $this->resultFor($entry, ['position' => 1, 'position_text' => '1']);

        $this->artisan('oof:achievements', ['--changed' => true])
            ->expectsOutputToContain('1 sessions')
            ->assertSuccessful();

        $this->assertTrue($changed->fresh()->achievements_checked_at->isSameDay('2024-06-02'));
        $this->assertTrue($unchanged->fresh()->achievements_checked_at->isSameDay('2024-06-01'));
    }

    public function test_never_runs_seasons_before_the_first_one_or_rounds_still_to_come(): void
    {
        $this->raceSession(SessionType::Race, $this->round(2019, 3, ['date' => '2019-04-14']));
        $this->raceSession(SessionType::Race, $this->round(2024, 20, ['date' => '2024-10-20']));

        $this->artisan('oof:achievements')
            ->expectsOutputToContain('0 sessions')
            ->assertSuccessful();
    }

    public function test_rejects_an_unknown_session_type(): void
    {
        $this->artisan('oof:achievements', ['--session' => 'warmup'])
            ->expectsOutputToContain('Unknown session type [warmup]')
            ->assertFailed();
    }
}
