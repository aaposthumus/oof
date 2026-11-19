<?php

namespace Tests\Feature\Achievements;

use App\Achievements\AchievementEngine;
use App\Achievements\Catalogue;
use App\Achievements\Detection;
use App\Achievements\Facts\DataSource;
use App\Achievements\Facts\Fact;
use App\Achievements\Facts\FactEvidence;
use App\Achievements\Facts\Requirements;
use App\Achievements\Facts\Template;
use App\Models\Achievement;
use App\Models\RaceSession;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use InvalidArgumentException;
use Tests\Concerns\BuildsSessions;
use Tests\TestCase;

class CatalogueTest extends TestCase
{
    use BuildsSessions, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->catalogue();
    }

    public function test_a_dry_run_sync_lists_the_drift_and_changes_nothing(): void
    {
        Achievement::where('key', 'lapped_dog')->update(['session_types' => ['race'], 'stacks' => true]);

        $this->artisan('oof:achievements:sync', ['--dry-run' => true])
            ->expectsOutputToContain('lapped_dog')
            ->assertSuccessful();

        $achievement = Achievement::firstWhere('key', 'lapped_dog');
        $this->assertSame(['race'], $achievement->session_types);
        $this->assertTrue($achievement->stacks);
    }

    public function test_sync_writes_the_mechanics_and_data_needs_and_keeps_the_editorial_fields(): void
    {
        Achievement::where('key', 'lapped_dog')->update(['session_types' => ['race'], 'stacks' => true, 'name' => 'Lapped Pup']);

        $this->artisan('oof:achievements:sync')->assertSuccessful();

        $achievement = Achievement::firstWhere('key', 'lapped_dog');
        $this->assertSame(['race', 'sprint'], $achievement->session_types);
        $this->assertFalse($achievement->stacks);
        $this->assertSame('Lapped Pup', $achievement->name);
        $this->assertSame([DataSource::JolpicaResults->value], $achievement->data_sources);
        $this->assertSame(1950, $achievement->available_from);
        $this->assertSame(1996, Achievement::firstWhere('key', 'clean_sweep')->available_from);

        $this->artisan('oof:achievements:sync', ['--dry-run' => true])
            ->expectsOutputToContain('0 created, 0 fields on 0 achievements would change')
            ->assertSuccessful();
    }

    public function test_sync_creates_a_missing_achievement_and_leaves_one_outside_the_catalogue(): void
    {
        Achievement::where('key', 'good_boy')->delete();
        Achievement::factory()->create(['key' => 'not_in_the_catalogue']);

        $this->artisan('oof:achievements:sync')
            ->expectsOutputToContain('not_in_the_catalogue is not in the catalogue')
            ->assertSuccessful();

        $this->assertSame('Good Boy', Achievement::firstWhere('key', 'good_boy')->name);
        $this->assertTrue(Achievement::where('key', 'not_in_the_catalogue')->exists());
    }

    public function test_data_needs_follow_from_the_facts_predicates_and_attributes_a_trigger_reads(): void
    {
        $requirements = app(Requirements::class);

        $this->assertSame(1996, $requirements->for('clean_sweep')['available_from']);
        $this->assertSame(1996, $requirements->for('led_astray')['available_from']);
        $this->assertContains(DataSource::JolpicaLaps, $requirements->for('clean_sweep')['sources']);
        $this->assertContains(DataSource::OpenF1Positions, $requirements->for('clean_sweep')['sources']);
        $this->assertSame(2004, $requirements->for('all_dressed_up_nowhere_to_go')['available_from']);
        $this->assertSame(1950, $requirements->for('did_the_thing')['available_from']);
        $this->assertSame(['Grid 0 meant a pit lane start until 2024.'], $requirements->for('reverse_gear')['notes']);
        $this->assertSame(1991, $requirements->for('class_dismissed')['available_from']);

        $this->artisan('oof:achievements:catalogue')
            ->expectsOutputToContain('All Dressed Up, Nowhere to Go')
            ->expectsOutputToContain('OpenF1 race control')
            ->assertSuccessful();
    }

    public function test_the_engine_looks_for_an_achievement_only_from_the_year_its_data_starts(): void
    {
        $before = $this->fastestLapOutsideThePoints(2003);
        $from = $this->fastestLapOutsideThePoints(2004);

        $this->assertSame([], $this->engineFinds('all_dressed_up_nowhere_to_go', $before));
        $this->assertCount(1, $this->engineFinds('all_dressed_up_nowhere_to_go', $from));
    }

    public function test_the_engine_skips_an_achievement_whose_source_has_no_rows_for_the_session(): void
    {
        $race = $this->fastestLapOutsideThePoints(2024);
        $requirements = app(Requirements::class);

        $this->assertTrue($requirements->allows('lapped_dog', $this->data($race)));
        $this->assertFalse($requirements->allows('blue_carpet', $this->data($race)));
        $this->assertFalse($requirements->allows('table_for_one', $this->data($race)));
    }

    public function test_a_trigger_with_an_unknown_option_is_refused(): void
    {
        config(['achievement_catalogue.achievements.lapped_dog.trigger.wher' => []]);

        $this->expectException(InvalidArgumentException::class);

        app(AchievementEngine::class)->trigger('lapped_dog');
    }

    public function test_the_season_scope_and_the_aggregate_trigger_only_go_together(): void
    {
        config(['achievement_catalogue.achievements.lapped_dog.scope' => 'season']);

        $this->expectException(InvalidArgumentException::class);

        app(AchievementEngine::class)->trigger('lapped_dog');
    }

    public function test_a_weekend_achievement_must_name_its_deciding_session(): void
    {
        config(['achievement_catalogue.achievements.overtime.decided_after' => null]);

        $this->expectException(InvalidArgumentException::class);

        app(AchievementEngine::class)->trigger('overtime');
    }

    public function test_an_aggregate_trigger_with_an_unknown_option_is_refused(): void
    {
        config(['achievement_catalogue.achievements.most_collected.trigger.pik' => 'max']);

        $this->expectException(InvalidArgumentException::class);

        app(AchievementEngine::class)->trigger('most_collected');
    }

    public function test_the_catalogue_version_follows_the_catalogue_the_engine_settings_and_the_stored_mechanics(): void
    {
        $version = fn () => app(Catalogue::class)->version();
        $before = $version();

        $this->assertSame($before, $version());

        config(['achievement_catalogue.achievements.blue_carpet.trigger.at_least' => 9]);
        $changedCatalogue = $version();
        $this->assertNotSame($before, $changedCatalogue);

        config(['achievements.surprise.window_races' => 12]);
        $changedSettings = $version();
        $this->assertNotSame($changedCatalogue, $changedSettings);

        // Points are snapshotted apart, so they are no part of it.
        config(['achievements.points.tiers.bronze' => 11]);
        $this->assertSame($changedSettings, $version());

        Achievement::firstWhere('key', 'fine_dining')->update(['supersedes' => []]);
        $this->assertNotSame($changedSettings, $version());
    }

    public function test_a_template_leaves_out_empty_values_and_picks_a_word_by_count(): void
    {
        $fact = new Fact('flag', 7, 'rcm:1', 'rcm:1', ['detail' => null, 'lap' => 12], FactEvidence::rows('openf1', []));

        $this->assertSame('stop-go', Template::render('stop-go {detail}', $fact));
        $this->assertNull(Template::render('{detail}', $fact));
        $this->assertSame('1 deleted lap', Template::render('{count} deleted {count:lap|laps}', $fact, ['count' => 1]));
        $this->assertSame('3 deleted laps', Template::render('{count} deleted {count:lap|laps}', $fact, ['count' => 3]));
        $this->assertSame('blue_flags:7', Template::render('blue_flags:{entry}', $fact));
    }

    /**
     * A race with a winner and a car that set the fastest lap outside the points.
     */
    private function fastestLapOutsideThePoints(int $year): RaceSession
    {
        $race = $this->raceSession(round: $this->round($year));
        $this->resultFor($this->car($race, 1), ['position' => 1, 'position_text' => '1', 'classification_order' => 1, 'grid' => 1, 'laps_completed' => 57, 'points' => 25, 'fastest_lap_rank' => 2]);
        $this->resultFor($this->car($race, 2), ['position' => 12, 'position_text' => '12', 'classification_order' => 12, 'grid' => 12, 'laps_completed' => 57, 'points' => 0, 'fastest_lap_rank' => 1]);

        return $race;
    }

    /**
     * @return list<Detection>
     */
    private function engineFinds(string $key, RaceSession $session): array
    {
        return app(AchievementEngine::class)->detect($this->data($session))->where('achievementKey', $key)->values()->all();
    }
}
