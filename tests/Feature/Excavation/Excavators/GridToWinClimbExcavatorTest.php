<?php

namespace Tests\Feature\Excavation\Excavators;

use App\Excavation\DigParameters;
use App\Excavation\Excavators\GridToWinClimbExcavator;
use App\Models\Result;
use App\Models\Season;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\BuildsRaces;
use Tests\TestCase;

class GridToWinClimbExcavatorTest extends TestCase
{
    use BuildsRaces, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'excavators.grid_to_win_climb.min_grid' => 5,
            'excavators.grid_to_win_climb.min_relative' => 0.4,
        ]);
    }

    public function test_scores_the_winner_by_grid_position_relative_to_the_starters(): void
    {
        $season = Season::factory()->create(['year' => 1983]);
        $round = $this->createRace($season, [
            Result::factory()->classified(1)->fromGrid(11),
            ...$this->field(running: 0, retired: 10, withdrawn: 2),
        ]);
        $this->createRace($season, [Result::factory()->classified(1)->fromGrid(2), ...$this->field(running: 0, retired: 10)]);

        $candidates = app(GridToWinClimbExcavator::class)->dig(new DigParameters);

        $this->assertCount(1, $candidates);
        $candidate = $candidates->sole();
        $winner = $round->raceSessions->sole()->driverEntries()->whereRelation('result', 'position', 1)->sole();
        $this->assertSame($round->id, $candidate->roundId);
        $this->assertSame($winner->driver_id, $candidate->driverId);
        $this->assertSame($winner->season_team_id, $candidate->seasonTeamId);
        $this->assertSame(100.0, $candidate->score);
        $this->assertSame(['grid' => 11, 'effective_grid' => 11, 'pit_lane_start' => false, 'starters' => 11, 'places_gained' => 10], $candidate->evidence);
    }

    public function test_counts_a_pit_lane_start_as_starting_last(): void
    {
        $season = Season::factory()->create(['year' => 2010]);
        $this->createRace($season, [Result::factory()->classified(1)->fromGrid(0), ...$this->field(running: 0, retired: 10)]);

        $candidates = app(GridToWinClimbExcavator::class)->dig(new DigParameters);

        $this->assertSame(
            ['grid' => 0, 'effective_grid' => 11, 'pit_lane_start' => true, 'starters' => 11, 'places_gained' => 10],
            $candidates->sole()->evidence,
        );
    }

    public function test_requires_both_a_minimum_grid_position_and_a_minimum_relative_position(): void
    {
        $season = Season::factory()->create(['year' => 2005]);
        $this->createRace($season, [Result::factory()->classified(1)->fromGrid(4), ...$this->field(running: 0, retired: 4)]);
        $this->createRace($season, [Result::factory()->classified(1)->fromGrid(5), ...$this->field(running: 0, retired: 15)]);

        $candidates = app(GridToWinClimbExcavator::class)->dig(new DigParameters);

        $this->assertCount(0, $candidates);
    }
}
