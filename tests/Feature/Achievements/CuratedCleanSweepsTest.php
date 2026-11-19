<?php

namespace Tests\Feature\Achievements;

use App\Achievements\Facts\Requirements;
use App\Enums\SessionType;
use App\Models\Driver;
use App\Models\DriverEntry;
use App\Models\RaceSession;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\BuildsSessions;
use Tests\TestCase;

/**
 * Clean Sweeps before the lap data come from the curated list of Grand
 * Chelems, and only once the owner has approved it (Phase 11b, Q5).
 */
class CuratedCleanSweepsTest extends TestCase
{
    use BuildsSessions, LazilyRefreshDatabase;

    private RaceSession $race;

    private DriverEntry $clark;

    protected function setUp(): void
    {
        parent::setUp();

        $this->catalogue();
        $this->race = $this->raceSession(SessionType::Race, $this->round(1963, 3, ['date' => '1963-06-23']));
        $this->clark = $this->car($this->race, 6, driver: Driver::factory()->create(['jolpica_driver_id' => 'clark']));
        $this->resultFor($this->clark, ['position' => 1, 'position_text' => '1', 'grid' => 1, 'laps_completed' => 80, 'status' => 'Finished', 'points' => 8]);
        $this->resultFor($this->car($this->race, 7), ['position' => 2, 'position_text' => '2', 'grid' => 2, 'laps_completed' => 79, 'status' => '+1 Lap', 'points' => 6]);
    }

    public function test_nothing_before_1996_is_awarded_while_the_list_waits_for_review(): void
    {
        config(['curated_clean_sweeps.approved' => false]);

        $this->assertFalse(app(Requirements::class)->allows('clean_sweep', $this->data($this->race)));
        $this->runEngine($this->race);
        $this->assertCount(0, $this->awards('clean_sweep'));
    }

    public function test_once_approved_a_listed_race_is_a_clean_sweep_for_its_driver(): void
    {
        config(['curated_clean_sweeps.approved' => true, 'curated_clean_sweeps.races' => [['season' => 1963, 'round' => 3, 'driver' => 'clark']]]);

        $this->runEngine($this->race);

        $award = $this->awards('clean_sweep')->sole();
        $this->assertSame($this->clark->id, $award->driver_entry_id);
        $this->assertCount(0, $this->awards('did_the_thing'));
    }

    public function test_an_unlisted_race_is_no_clean_sweep(): void
    {
        config(['curated_clean_sweeps.approved' => true, 'curated_clean_sweeps.races' => [['season' => 1963, 'round' => 4, 'driver' => 'clark']]]);

        $this->assertCount(0, $this->detect('clean_sweep', $this->race));
    }

    public function test_the_list_names_each_race_once_before_the_lap_data(): void
    {
        $races = collect(config('curated_clean_sweeps.races'));

        $this->assertCount(44, $races);
        $this->assertSame($races->count(), $races->unique(fn (array $race) => "{$race['season']}-{$race['round']}")->count());
        $this->assertTrue($races->every(fn (array $race) => $race['season'] < config('curated_clean_sweeps.before') && is_string($race['driver'])));
    }
}
