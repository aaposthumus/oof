<?php

namespace Tests\Feature\Excavation\Excavators;

use App\Excavation\DigParameters;
use App\Excavation\Excavators\TitleDeciderExcavator;
use App\Models\Driver;
use App\Models\DriverStanding;
use App\Models\Result;
use App\Models\Round;
use App\Models\Season;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\BuildsRaces;
use Tests\TestCase;

class TitleDeciderExcavatorTest extends TestCase
{
    use BuildsRaces, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'excavators.title_decider.max_margin_wins' => 0.5,
            'excavators.title_decider.lead_change_score' => 80,
            'excavators.title_decider.open_score' => 60,
            'excavators.title_decider.close_score' => 40,
            'excavators.title_decider.margin_score' => 20,
            'excavators.title_decider.dropped_scores_until' => 1990,
        ]);
    }

    public function test_finds_a_title_won_in_the_final_race_from_the_leader_and_relates_the_season(): void
    {
        [$leader, $champion] = Driver::factory()->count(2)->create();
        $season = Season::factory()->create(['year' => 2007]);
        $first = $this->race($season, 1, $leader, $champion);
        $second = $this->race($season, 2, $leader, $champion, [$leader->id => 10, $champion->id => 8]);
        $final = $this->race($season, 3, $champion, $leader, [$champion->id => 18, $leader->id => 17]);
        $this->standings($first, [$leader->id => 10, $champion->id => 8]);

        $candidate = app(TitleDeciderExcavator::class)->dig(new DigParameters)->sole();

        $this->assertSame([$final->id, $champion->id], [$candidate->roundId, $candidate->driverId]);
        $this->assertSame(98.0, $candidate->score);
        $this->assertSame([$first->id, $second->id], $candidate->relatedRoundIds);
        $this->assertSame(1.0, $candidate->evidence['final_margin']);
        $this->assertTrue($candidate->evidence['lead_change']);
        $this->assertFalse($candidate->evidence['estimate_with_dropped_scores']);
    }

    public function test_ignores_a_title_settled_before_the_final_race_and_an_unfinished_season(): void
    {
        [$champion, $other] = Driver::factory()->count(2)->create();
        $settled = Season::factory()->create(['year' => 2002]);
        $this->race($settled, 1, $champion, $other, [$champion->id => 40, $other->id => 10]);
        $this->race($settled, 2, $champion, $other, [$champion->id => 50, $other->id => 16]);
        $running = Season::factory()->create(['year' => 2026]);
        $this->race($running, 1, $other, $champion, [$other->id => 10, $champion->id => 9]);
        $this->race($running, 2, $other, $champion, [$other->id => 20, $champion->id => 19]);
        Round::factory()->for($running)->create(['round_number' => 3]);

        $this->assertCount(0, app(TitleDeciderExcavator::class)->dig(new DigParameters));
    }

    /**
     * @param  array<int, float>  $standings  Points after the race by driver id, leader first
     */
    private function race(Season $season, int $roundNumber, Driver $winner, Driver $runnerUp, array $standings = []): Round
    {
        $round = $this->createRaceWith($season, $roundNumber, [
            [$winner, Result::factory()->classified(1)->state(['points' => 10])],
            [$runnerUp, Result::factory()->classified(2)->state(['points' => 6])],
        ]);
        $this->standings($round, $standings);

        return $round;
    }

    /**
     * @param  array<int, float>  $points  By driver id, leader first
     */
    private function standings(Round $round, array $points): void
    {
        $position = 1;

        foreach ($points as $driverId => $driverPoints) {
            DriverStanding::updateOrCreate(
                ['round_id' => $round->id, 'driver_id' => $driverId],
                ['position' => $position, 'position_text' => (string) $position++, 'points' => $driverPoints, 'wins' => 0],
            );
        }
    }
}
