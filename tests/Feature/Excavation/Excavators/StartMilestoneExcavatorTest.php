<?php

namespace Tests\Feature\Excavation\Excavators;

use App\Excavation\DigParameters;
use App\Excavation\Excavators\StartMilestoneExcavator;
use App\Models\Driver;
use App\Models\Result;
use App\Models\Season;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\BuildsRaces;
use Tests\TestCase;

class StartMilestoneExcavatorTest extends TestCase
{
    use BuildsRaces, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'excavators.start_milestone.milestones' => [2 => 50, 3 => 80],
            'excavators.start_milestone.first_to_reach_bonus' => 10,
        ]);
    }

    public function test_finds_milestone_starts_and_the_first_driver_to_reach_each(): void
    {
        $season = Season::factory()->create(['year' => 1990]);
        [$pioneer, $follower] = Driver::factory()->count(2)->create();
        $first = $this->createRaceWith($season, 1, [[$pioneer, Result::factory()->classified(1)], [$follower, Result::factory()->withdrawn()]]);
        $second = $this->createRaceWith($season, 2, [[$pioneer, Result::factory()->classified(1)], [$follower, Result::factory()->classified(2)]]);
        $third = $this->createRaceWith($season, 3, [[$pioneer, Result::factory()->retired()], [$follower, Result::factory()->classified(1)]]);
        $fourth = $this->createRaceWith($season, 4, [[$follower, Result::factory()->classified(1)]]);

        $candidates = app(StartMilestoneExcavator::class)->dig(new DigParameters)
            ->map(fn ($candidate) => [$candidate->roundId, $candidate->driverId, $candidate->evidence['start_number'], $candidate->score])
            ->all();

        $this->assertEqualsCanonicalizing([
            [$second->id, $pioneer->id, 2, 60.0],
            [$third->id, $follower->id, 2, 50.0],
            [$third->id, $pioneer->id, 3, 90.0],
            [$fourth->id, $follower->id, 3, 80.0],
        ], $candidates);
        $this->assertNotContains($first->id, array_column($candidates, 0));
    }
}
