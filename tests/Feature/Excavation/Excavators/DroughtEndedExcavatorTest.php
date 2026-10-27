<?php

namespace Tests\Feature\Excavation\Excavators;

use App\Excavation\DigParameters;
use App\Excavation\Excavators\PodiumDroughtEndedExcavator;
use App\Excavation\Excavators\WinDroughtEndedExcavator;
use App\Models\Driver;
use App\Models\Result;
use App\Models\Season;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\BuildsRaces;
use Tests\TestCase;

class DroughtEndedExcavatorTest extends TestCase
{
    use BuildsRaces, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'excavators.win_drought_ended.min_starts' => 3,
            'excavators.podium_drought_ended.min_starts' => 3,
        ]);
    }

    public function test_counts_starts_before_the_first_podium_and_win_across_seasons_without_withdrawals(): void
    {
        $driver = Driver::factory()->create();
        $earlier = Season::factory()->create(['year' => 2010]);
        $this->createRaceWith($earlier, 1, [[$driver, Result::factory()->classified(5)]]);
        $this->createRaceWith($earlier, 2, [[$driver, Result::factory()->withdrawn()]]);
        $this->createRaceWith($earlier, 3, [[$driver, Result::factory()->classified(5)]]);
        $this->createRaceWith($earlier, 4, [[$driver, Result::factory()->classified(6)]]);
        $later = Season::factory()->create(['year' => 2011]);
        $podium = $this->createRaceWith($later, 1, [[$driver, Result::factory()->classified(3)]]);
        $win = $this->createRaceWith($later, 2, [[$driver, Result::factory()->classified(1)]]);
        $this->createRaceWith($later, 3, [[$driver, Result::factory()->classified(1)]]);

        $podiums = app(PodiumDroughtEndedExcavator::class)->dig(new DigParameters(seasons: [2011]));
        $wins = app(WinDroughtEndedExcavator::class)->dig(new DigParameters(seasons: [2011]));

        $this->assertSame($podium->id, $podiums->sole()->roundId);
        $this->assertSame(['starts_before' => 3, 'start_number' => 4, 'position' => 3], $podiums->sole()->evidence);
        $this->assertSame([$win->id, 4.0], [$wins->sole()->roundId, $wins->sole()->score]);
    }

    public function test_ignores_a_first_win_after_fewer_starts_than_the_minimum(): void
    {
        $season = Season::factory()->create(['year' => 2010]);
        $driver = Driver::factory()->create();
        $this->createRaceWith($season, 1, [[$driver, Result::factory()->classified(4)]]);
        $this->createRaceWith($season, 2, [[$driver, Result::factory()->classified(1)]]);

        $this->assertCount(0, app(WinDroughtEndedExcavator::class)->dig(new DigParameters));
    }
}
