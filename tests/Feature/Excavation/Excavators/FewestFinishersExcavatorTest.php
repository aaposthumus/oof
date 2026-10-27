<?php

namespace Tests\Feature\Excavation\Excavators;

use App\Excavation\DigParameters;
use App\Excavation\Excavators\FewestFinishersExcavator;
use App\Models\Result;
use App\Models\Season;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\BuildsRaces;
use Tests\TestCase;

class FewestFinishersExcavatorTest extends TestCase
{
    use BuildsRaces, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'excavators.fewest_finishers.max_ratio' => 0.30,
            'excavators.fewest_finishers.max_running' => 0,
            'excavators.fewest_finishers.min_starters' => 10,
        ]);
    }

    public function test_counts_only_classified_cars_running_at_the_flag(): void
    {
        $season = Season::factory()->create(['year' => 1996]);
        $carnage = $this->createRace($season, [
            Result::factory()->classified(1),
            Result::factory()->classified(2),
            Result::factory()->classified(3, '+1 Lap'),
            Result::factory()->classified(4, 'Collision'),
            Result::factory()->classified(5, 'Withdrew'),
            ...array_fill(0, 5, Result::factory()->retired()),
            Result::factory()->withdrawn(),
        ], 'Monaco Grand Prix');
        $this->createRace($season, $this->field(running: 4, retired: 6));

        $candidates = app(FewestFinishersExcavator::class)->dig(new DigParameters);

        $this->assertCount(1, $candidates);
        $candidate = $candidates->sole();
        $this->assertSame($carnage->id, $candidate->roundId);
        $this->assertNull($candidate->driverId);
        $this->assertSame(70.0, $candidate->score);
        $this->assertSame(['running' => 3, 'classified' => 5, 'starters' => 10, 'ratio' => 0.3], $candidate->evidence);
    }

    public function test_hits_few_running_cars_below_the_ratio_threshold(): void
    {
        config(['excavators.fewest_finishers.max_running' => 4]);
        $season = Season::factory()->create(['year' => 1966]);
        $this->createRace($season, $this->field(running: 4, retired: 6));

        $candidates = app(FewestFinishersExcavator::class)->dig(new DigParameters);

        $this->assertSame([60.0], $candidates->pluck('score')->all());
    }

    public function test_ignores_fields_smaller_than_the_minimum(): void
    {
        $season = Season::factory()->create(['year' => 2005]);
        $this->createRace($season, $this->field(running: 1, retired: 8));

        $candidates = app(FewestFinishersExcavator::class)->dig(new DigParameters);

        $this->assertCount(0, $candidates);
    }
}
