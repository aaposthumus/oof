<?php

namespace Tests\Feature\Excavation\Excavators;

use App\Excavation\DigParameters;
use App\Excavation\Excavators\ClosestFinishExcavator;
use App\Excavation\Excavators\DominantWinExcavator;
use App\Models\Driver;
use App\Models\Result;
use App\Models\Round;
use App\Models\Season;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\BuildsRaces;
use Tests\TestCase;

class RaceFinishExcavatorsTest extends TestCase
{
    use BuildsRaces, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'excavators.dominant_win.min_lapped_share' => 0.9,
            'excavators.dominant_win.min_gap_share' => 0.02,
            'excavators.dominant_win.lapped_score' => 70,
            'excavators.dominant_win.extra_lap_score' => 15,
            'excavators.closest_finish.max_gap_ms' => 500,
        ]);
    }

    public function test_finds_wins_by_laps_and_by_a_huge_gap(): void
    {
        $season = Season::factory()->create(['year' => 1969]);
        $twoLaps = $this->race($season, 1, runnerUpLaps: 88, runnerUpGap: null);
        $this->race($season, 2, runnerUpLaps: 90, runnerUpGap: 50_000);
        $hugeGap = $this->race($season, 3, runnerUpLaps: 90, runnerUpGap: 150_000);

        $candidates = app(DominantWinExcavator::class)->dig(new DigParameters)->keyBy('roundId');

        $this->assertSame([$twoLaps->id, $hugeGap->id], $candidates->keys()->all());
        $this->assertSame(85.0, $candidates[$twoLaps->id]->score);
        $this->assertSame(2, $candidates[$twoLaps->id]->evidence['laps_ahead']);
        $this->assertSame(52.5, $candidates[$hugeGap->id]->score);
        $this->assertSame(0.03, $candidates[$hugeGap->id]->evidence['gap_share']);
    }

    public function test_finds_the_closest_finishes_on_the_same_lap(): void
    {
        $season = Season::factory()->create(['year' => 1971]);
        $closest = $this->race($season, 1, runnerUpLaps: 90, runnerUpGap: 10);
        $this->race($season, 2, runnerUpLaps: 90, runnerUpGap: 600);
        $this->race($season, 3, runnerUpLaps: 89, runnerUpGap: 10);

        $candidate = app(ClosestFinishExcavator::class)->dig(new DigParameters)->sole();

        $this->assertSame($closest->id, $candidate->roundId);
        $this->assertSame(98.0, $candidate->score);
        $this->assertSame(10, $candidate->evidence['gap_ms']);
    }

    private function race(Season $season, int $roundNumber, int $runnerUpLaps, ?int $runnerUpGap): Round
    {
        $winnerTime = 5_000_000;

        return $this->createRaceWith($season, $roundNumber, [
            [Driver::factory()->create(), Result::factory()->classified(1)->state(['laps_completed' => 90, 'time_millis' => $winnerTime])],
            [Driver::factory()->create(), Result::factory()->classified(2)->state(['laps_completed' => $runnerUpLaps, 'time_millis' => $runnerUpGap === null ? null : $winnerTime + $runnerUpGap])],
            [Driver::factory()->create(), Result::factory()->classified(3)->state(['laps_completed' => 88])],
        ]);
    }
}
