<?php

namespace Tests\Feature\Excavation\Excavators;

use App\Excavation\DigParameters;
use App\Excavation\Excavators\ShortestRaceExcavator;
use App\Models\Circuit;
use App\Models\Driver;
use App\Models\Result;
use App\Models\Round;
use App\Models\Season;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\BuildsRaces;
use Tests\TestCase;

class ShortestRaceExcavatorTest extends TestCase
{
    use BuildsRaces, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'excavators.shortest_race.max_ratio' => 0.75,
            'excavators.shortest_race.window_years' => 5,
            'excavators.shortest_race.half_points_min' => 0.4,
            'excavators.shortest_race.half_points_max' => 0.6,
            'excavators.shortest_race.half_points_score' => 50,
        ]);
    }

    public function test_compares_the_winners_laps_with_nearby_races_at_the_circuit_and_detects_half_points(): void
    {
        $spa = Circuit::factory()->create();
        $other = Circuit::factory()->create();

        foreach ([2020, 2021, 2022] as $year) {
            $season = Season::factory()->create(['year' => $year]);
            $this->race($season, 1, $other, laps: 60, winnerPoints: 25, runnerUpPoints: 18);
            $this->race($season, 2, $other, laps: 60, winnerPoints: 25, runnerUpPoints: 18);
            $year === 2021
                ? $this->race($season, 3, $spa, laps: 11, winnerPoints: 12.5, runnerUpPoints: 9)
                : $this->race($season, 3, $spa, laps: 44, winnerPoints: 25, runnerUpPoints: 18);
        }

        $candidate = app(ShortestRaceExcavator::class)->dig(new DigParameters)->sole();

        $this->assertSame(Round::where('round_number', 3)->whereRelation('season', 'year', 2021)->value('id'), $candidate->roundId);
        $this->assertSame(75.0, $candidate->score);
        $this->assertSame(['laps' => 11, 'estimated_scheduled_laps' => 44.0, 'ratio' => 0.25, 'half_points' => true, 'winner_points' => 12.5], $candidate->evidence);
    }

    public function test_a_shared_win_with_halved_points_is_no_half_points_race(): void
    {
        $season = Season::factory()->create(['year' => 1957]);
        $circuit = Circuit::factory()->create();
        $this->race($season, 1, $circuit, laps: 80, winnerPoints: 8, runnerUpPoints: 6);
        $this->race($season, 2, $circuit, laps: 80, winnerPoints: 8, runnerUpPoints: 6);
        $this->race($season, 3, $circuit, laps: 80, winnerPoints: 4, runnerUpPoints: 6);

        $this->assertCount(0, app(ShortestRaceExcavator::class)->dig(new DigParameters));
    }

    private function race(Season $season, int $roundNumber, Circuit $circuit, int $laps, float $winnerPoints, float $runnerUpPoints): Round
    {
        return $this->createRaceWith($season, $roundNumber, [
            [Driver::factory()->create(), Result::factory()->classified(1)->state(['laps_completed' => $laps, 'points' => $winnerPoints])],
            [Driver::factory()->create(), Result::factory()->classified(2)->state(['laps_completed' => $laps, 'points' => $runnerUpPoints])],
        ], ['circuit_id' => $circuit->id]);
    }
}
