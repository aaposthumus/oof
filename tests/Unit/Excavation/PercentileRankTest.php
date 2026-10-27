<?php

namespace Tests\Unit\Excavation;

use App\Excavation\Support\PercentileRank;
use PHPUnit\Framework\TestCase;

class PercentileRankTest extends TestCase
{
    public function test_ranks_the_best_score_100_and_the_lowest_0(): void
    {
        $this->assertEquals(['a' => 0.0, 'b' => 50.0, 'c' => 100.0], PercentileRank::of(['c' => 90.0, 'a' => 10.0, 'b' => 40.0]));
    }

    public function test_equal_scores_share_the_higher_rank(): void
    {
        $this->assertEquals([0 => 100.0, 1 => 100.0, 2 => 33.33, 3 => 0.0], PercentileRank::of([30.0, 30.0, 20.0, 10.0]));
    }

    public function test_a_single_score_ranks_100(): void
    {
        $this->assertSame([7 => 100.0], PercentileRank::of([7 => 12.5]));
    }

    public function test_no_scores_give_no_ranks(): void
    {
        $this->assertSame([], PercentileRank::of([]));
    }
}
