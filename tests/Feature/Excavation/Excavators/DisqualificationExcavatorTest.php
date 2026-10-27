<?php

namespace Tests\Feature\Excavation\Excavators;

use App\Excavation\DigParameters;
use App\Excavation\Excavators\DisqualificationExcavator;
use App\Models\Driver;
use App\Models\Result;
use App\Models\Season;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\BuildsRaces;
use Tests\TestCase;

class DisqualificationExcavatorTest extends TestCase
{
    use BuildsRaces, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'excavators.disqualified_statuses' => ['Disqualified', 'Excluded'],
            'excavators.disqualification.road_winner_score' => 100,
            'excavators.disqualification.road_podium_score' => 80,
            'excavators.disqualification.full_distance_score' => 60,
            'excavators.disqualification.other_score' => 30,
            'excavators.disqualification.extra_driver_score' => 5,
        ]);
    }

    public function test_a_driver_disqualified_after_finishing_first_on_the_road_heads_the_race(): void
    {
        $season = Season::factory()->create(['year' => 2024]);
        [$winner, $runnerUp, $disqualified] = Driver::factory()->count(3)->create();
        $round = $this->createRaceWith($season, 1, [
            [$winner, Result::factory()->classified(1)->state(['laps_completed' => 44, 'time_millis' => 4_797_566])],
            [$runnerUp, Result::factory()->classified(2)->state(['laps_completed' => 44, 'time_millis' => 4_798_213])],
            // Disqualified rows can carry a numeric position text since 2023.
            [$disqualified, Result::factory()->disqualified()->state(['position_text' => '20', 'laps_completed' => 44, 'time_millis' => 4_797_040])],
        ]);

        $candidate = app(DisqualificationExcavator::class)->dig(new DigParameters)->sole();

        $this->assertSame([$round->id, $disqualified->id, 100.0], [$candidate->roundId, $candidate->driverId, $candidate->score]);
        $this->assertSame(1, $candidate->evidence['road_position']);
    }

    public function test_without_times_scores_full_distance_and_each_further_disqualified_driver(): void
    {
        $season = Season::factory()->create(['year' => 1982]);
        [$winner, $first, $second] = Driver::factory()->count(3)->create();
        $this->createRaceWith($season, 1, [
            [$winner, Result::factory()->classified(1)->state(['laps_completed' => 63])],
            [$second, Result::factory()->disqualified(12, 'Excluded')->state(['laps_completed' => 40])],
            [$first, Result::factory()->disqualified(11)->state(['laps_completed' => 63])],
        ]);
        $this->createRaceWith($season, 2, [[$winner, Result::factory()->classified(1)]]);

        $candidate = app(DisqualificationExcavator::class)->dig(new DigParameters)->sole();

        $this->assertSame($first->id, $candidate->driverId);
        $this->assertSame(65.0, $candidate->score);
        $this->assertSame([
            'disqualified' => 2,
            'drivers' => "{$first->given_name} {$first->family_name}, {$second->given_name} {$second->family_name}",
            'statuses' => 'Disqualified, Excluded',
            'road_position' => null,
            'full_distance' => true,
            'laps_completed' => 63,
        ], $candidate->evidence);
    }
}
