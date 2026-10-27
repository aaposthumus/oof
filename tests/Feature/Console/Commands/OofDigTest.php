<?php

namespace Tests\Feature\Console\Commands;

use App\Models\MomentSensitivity;
use App\Models\Season;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\BuildsRaces;
use Tests\TestCase;

class OofDigTest extends TestCase
{
    use BuildsRaces, LazilyRefreshDatabase;

    public function test_digs_with_one_excavator_and_lists_its_top_candidates(): void
    {
        $season = Season::factory()->create(['year' => 2005]);
        $this->createRace($season, $this->field(running: 10));
        $this->createRace($season, $this->field(running: 10));
        $this->createRace($season, $this->field(running: 10));
        $round = $this->createRace($season, $this->field(running: 4, withdrawn: 6), 'United States Grand Prix');
        MomentSensitivity::factory()->for($round)->create(['note' => 'A marshal was killed.']);

        $this->artisan('oof:dig', ['type' => 'fewest_starters', '--season' => [2005]])
            ->expectsOutputToContain('fewest_starters (v1)')
            ->expectsOutputToContain('2005 United States Grand Prix ⚠ SENSITIVE')
            ->expectsOutputToContain('Sensitive, 2005 United States Grand Prix: A marshal was killed.')
            ->doesntExpectOutputToContain('across all excavators')
            ->assertSuccessful();

        $this->assertDatabaseCount('excavation_runs', 1);
        $this->assertDatabaseHas('moment_candidates', ['type' => 'fewest_starters', 'round_id' => $round->id, 'is_sensitive' => true]);
    }

    public function test_runs_every_excavator_without_a_type_and_shows_the_evidence(): void
    {
        $season = Season::factory()->create(['year' => 2005]);
        $this->createRace($season, $this->field(running: 10));
        $this->createRace($season, $this->field(running: 10));
        $this->createRace($season, $this->field(running: 4, withdrawn: 6));

        $this->artisan('oof:dig')
            ->expectsOutputToContain('fewest_starters (v1)')
            ->expectsOutputToContain('starters=4 entries=10 season_median_starters=10.0 ratio=0.4 raw_score=60.0')
            ->expectsOutputToContain('fewest_finishers (v1)')
            ->expectsOutputToContain('grid_to_win_climb (v1)')
            ->expectsOutputToContain('across all excavators')
            ->assertSuccessful();

        $this->assertDatabaseCount('excavation_runs', 3);
    }

    public function test_rejects_an_unknown_excavator(): void
    {
        $this->artisan('oof:dig', ['type' => 'nonsense'])
            ->expectsOutputToContain('Unknown excavator [nonsense]')
            ->assertFailed();
    }

    public function test_a_dry_run_stores_nothing(): void
    {
        $this->artisan('oof:dig', ['--dry-run' => true])->assertSuccessful();

        $this->assertDatabaseCount('excavation_runs', 0);
    }
}
