<?php

namespace Tests\Feature\Console\Commands;

use App\Achievements\Catalogue;
use App\Enums\AwardScope;
use App\Enums\SessionType;
use App\Models\AchievementAward;
use App\Models\AchievementRun;
use App\Models\RaceSession;
use App\Models\Result;
use App\Models\User;
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
        $earlier = $this->raceSession(SessionType::Race, $this->round(2024, 4));
        $quiet = $this->car($race, 44);
        $penalised = $this->car($race, 16);
        $this->resultFor($this->car($earlier, 44, null, $quiet->driver), ['position' => 5, 'position_text' => '5']);
        $this->resultFor($this->car($earlier, 16, null, $penalised->driver), ['position' => 6, 'position_text' => '6']);
        $this->resultFor($quiet, ['position' => 8, 'position_text' => '8']);
        $this->resultFor($penalised, ['position' => 9, 'position_text' => '9', 'grid' => 2]);
        $this->resultFor($this->car($race, 99), ['position' => 20, 'position_text' => '20']);
        $this->car($qualifying, 16, null, $penalised->driver);
        $this->message($qualifying, 'FIA STEWARDS: 3 PLACE GRID PENALTY FOR CAR 16 (LEC) - CAUSING A COLLISION');

        $this->artisan('oof:achievements', ['--season' => [2024]])
            ->expectsOutputToContain('3 sessions')
            ->expectsOutputToContain('bumper_cars')
            ->assertSuccessful();

        // Bumper Cars in qualifying counts for the weekend, so only the car in P8 stayed quiet.
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

    /**
     * A race with a classified finisher, in round 5 by default.
     */
    private function raceWithAResult(int $roundNumber = 5): RaceSession
    {
        $race = $this->raceSession(SessionType::Race, $this->round(2024, $roundNumber, ['date' => sprintf('2024-05-%02d', $roundNumber)]));
        $this->resultFor($this->car($race, 1), ['position' => 1, 'position_text' => '1', 'points' => 25]);

        return $race;
    }

    public function test_logs_every_session_weekend_and_season_it_decides_with_the_catalogue_version(): void
    {
        $race = $this->raceWithAResult();
        $qualifying = $this->raceSession(SessionType::Qualifying, $race->round);

        $this->artisan('oof:achievements')
            ->expectsOutputToContain('2 sessions, 1 weekends, 1 seasons')
            ->assertSuccessful();

        $runs = AchievementRun::query()->orderBy('id')->get();
        $this->assertSame(["session:{$qualifying->id}", "session:{$race->id}", "round:{$race->round_id}", "season:{$race->round->season_id}"], $runs->pluck('context_key')->all());
        $this->assertSame([AwardScope::Session, AwardScope::Session, AwardScope::Weekend, AwardScope::Season], $runs->pluck('scope')->all());
        $this->assertSame([app(Catalogue::class)->version()], $runs->pluck('catalogue_version')->unique()->all());
        $this->assertFalse($runs->contains('is_dry_run', true));
        $this->assertSame(1, $runs[1]->stats['did_the_thing']['created']);
    }

    public function test_a_dry_run_is_logged_as_one_and_never_counts_as_decided(): void
    {
        $this->raceWithAResult();

        $this->artisan('oof:achievements', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame(3, AchievementRun::query()->where('is_dry_run', true)->count());
        $this->assertSame(['dry-run' => true], AchievementRun::query()->first()->options);
        $this->assertDatabaseCount('achievement_awards', 0);
    }

    public function test_stale_re_runs_only_what_was_decided_on_another_catalogue_version(): void
    {
        $this->raceWithAResult(4);
        $this->raceWithAResult(5);
        $this->artisan('oof:achievements')->assertSuccessful();

        $this->artisan('oof:achievements', ['--stale' => true])
            ->expectsOutputToContain('0 sessions, 0 weekends, 0 seasons')
            ->assertSuccessful();

        config(['achievement_catalogue.achievements.did_the_thing.trigger.detail' => 'Won']);

        $this->artisan('oof:achievements', ['--stale' => true])
            ->expectsOutputToContain('2 sessions, 2 weekends, 1 seasons')
            ->assertSuccessful();
        $this->assertSame(['Won'], AchievementAward::whereRelation('achievement', 'key', 'did_the_thing')->pluck('detail')->unique()->all());

        $this->artisan('oof:achievements', ['--stale' => true])
            ->expectsOutputToContain('0 sessions, 0 weekends, 0 seasons')
            ->assertSuccessful();
    }

    public function test_stale_re_runs_a_session_whose_surprise_window_changed_since(): void
    {
        $earlier = $this->raceWithAResult(3);
        $windowRace = $this->raceWithAResult(4);
        $later = $this->raceWithAResult(5);
        $this->artisan('oof:achievements')->assertSuccessful();

        // A late change to round 4's result changes the team form that rounds 5 reads, not what rounds 3 and 4 read.
        Date::setTestNow('2024-06-02 12:00:00');
        Result::query()->whereHas('driverEntry', fn ($query) => $query->where('race_session_id', $windowRace->id))->update(['points' => 0, 'updated_at' => now()]);

        $this->artisan('oof:achievements', ['--stale' => true])
            ->expectsOutputToContain('1 sessions, 1 weekends, 1 seasons')
            ->assertSuccessful();

        $this->assertTrue($later->fresh()->achievements_checked_at->isSameDay('2024-06-02'));
        $this->assertTrue($windowRace->fresh()->achievements_checked_at->isSameDay('2024-06-01'));
        $this->assertTrue($earlier->fresh()->achievements_checked_at->isSameDay('2024-06-01'));
    }

    public function test_runs_only_the_scopes_asked_for(): void
    {
        $this->raceWithAResult();

        $this->artisan('oof:achievements', ['--scope' => ['season']])
            ->expectsOutputToContain('0 sessions, 0 weekends, 0 seasons')
            ->assertSuccessful();

        $this->artisan('oof:achievements', ['--scope' => ['session', 'weekend']])
            ->expectsOutputToContain('1 sessions, 1 weekends, 0 seasons')
            ->assertSuccessful();

        // Now that its race has been run, the season can be decided on its own.
        $this->artisan('oof:achievements', ['--scope' => ['season']])
            ->expectsOutputToContain('0 sessions, 0 weekends, 1 seasons')
            ->assertSuccessful();

        $this->artisan('oof:achievements', ['--scope' => ['round']])
            ->expectsOutputToContain('Unknown scope')
            ->assertFailed();
    }

    public function test_changed_decides_a_season_again_once_its_awards_changed(): void
    {
        $this->raceWithAResult();
        $this->artisan('oof:achievements')->assertSuccessful();

        $this->artisan('oof:achievements', ['--changed' => true])
            ->expectsOutputToContain('0 sessions, 0 weekends, 0 seasons')
            ->assertSuccessful();

        Date::setTestNow('2024-06-02 12:00:00');
        AchievementAward::whereRelation('achievement', 'key', 'did_the_thing')->sole()->suppress(User::factory()->create(), 'Not a win after all.');

        $this->artisan('oof:achievements', ['--changed' => true])
            ->expectsOutputToContain('0 sessions, 0 weekends, 1 seasons')
            ->assertSuccessful();
    }
}
