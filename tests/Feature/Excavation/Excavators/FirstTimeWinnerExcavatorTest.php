<?php

namespace Tests\Feature\Excavation\Excavators;

use App\Excavation\DigParameters;
use App\Excavation\Excavators\FirstTimeWinnerExcavator;
use App\Models\Driver;
use App\Models\Result;
use App\Models\Season;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\BuildsRaces;
use Tests\TestCase;

class FirstTimeWinnerExcavatorTest extends TestCase
{
    use BuildsRaces, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'excavators.first_time_winner.first_win_score' => 50,
            'excavators.first_time_winner.one_hit_score' => 100,
        ]);
    }

    public function test_scores_a_one_hit_wonder_above_a_first_of_several_wins_and_ignores_disqualified_wins(): void
    {
        $season = Season::factory()->create(['year' => 1999]);
        [$serial, $oneHit, $disqualified] = Driver::factory()->count(3)->create();
        $first = $this->createRaceWith($season, 1, [[$serial, Result::factory()->classified(1)], [$oneHit, Result::factory()->classified(2)]]);
        $this->createRaceWith($season, 2, [[$serial, Result::factory()->classified(1)], [$disqualified, Result::factory()->disqualified(1)]]);
        $only = $this->createRaceWith($season, 3, [[$oneHit, Result::factory()->classified(1)], [$serial, Result::factory()->classified(2)]]);

        $candidates = app(FirstTimeWinnerExcavator::class)->dig(new DigParameters)->keyBy('driverId');

        $this->assertSame([$serial->id, $oneHit->id], $candidates->keys()->all());
        $this->assertSame([$first->id, 50.0], [$candidates[$serial->id]->roundId, $candidates[$serial->id]->score]);
        $this->assertSame([$only->id, 100.0], [$candidates[$oneHit->id]->roundId, $candidates[$oneHit->id]->score]);
        $this->assertSame(['start_number' => 2, 'career_wins' => 1, 'one_hit_wonder' => true], $candidates[$oneHit->id]->evidence);
    }
}
