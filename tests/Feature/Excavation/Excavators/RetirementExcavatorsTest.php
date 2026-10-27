<?php

namespace Tests\Feature\Excavation\Excavators;

use App\Excavation\DigParameters;
use App\Excavation\Excavators\CuriousRetirementExcavator;
use App\Excavation\Excavators\MostRetirementsExcavator;
use App\Models\Driver;
use App\Models\Result;
use App\Models\Season;
use Database\Factories\ResultFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\BuildsRaces;
use Tests\TestCase;

class RetirementExcavatorsTest extends TestCase
{
    use BuildsRaces, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'excavators.skipped_statuses' => ['Injury', 'Fatal accident'],
            'excavators.most_retirements.min_retirements' => 3,
            'excavators.curious_retirement.statuses' => ['Out of fuel', 'Seat', 'Injury'],
        ]);
    }

    public function test_counts_retirements_without_disqualifications_or_skipped_statuses(): void
    {
        $season = Season::factory()->create(['year' => 1982]);
        $carnage = $this->createRaceWith($season, 1, $this->entries([
            Result::factory()->classified(1),
            Result::factory()->classified(2, '+1 Lap'),
            Result::factory()->classified(3, 'Accident'),
            Result::factory()->retired('Engine'),
            Result::factory()->retired('Engine'),
            Result::factory()->retired('Injury'),
            Result::factory()->disqualified(),
            Result::factory()->withdrawn(),
        ]));
        $this->createRaceWith($season, 2, $this->entries([Result::factory()->classified(1), Result::factory()->retired(), Result::factory()->retired()]));

        $candidate = app(MostRetirementsExcavator::class)->dig(new DigParameters)->sole();

        $this->assertSame($carnage->id, $candidate->roundId);
        $this->assertSame(3.0, $candidate->score);
        $this->assertSame(['retirements' => 3, 'starters' => 7, 'top_statuses' => 'Engine ×2, Accident ×1'], $candidate->evidence);
    }

    public function test_scores_curious_statuses_by_rarity_and_never_uses_a_skipped_one(): void
    {
        $season = Season::factory()->create(['year' => 2017]);
        $this->createRaceWith($season, 1, $this->entries([Result::factory()->classified(1), Result::factory()->retired('Out of fuel'), Result::factory()->retired('Injury')]));
        $this->createRaceWith($season, 2, $this->entries([Result::factory()->classified(1, 'Out of fuel'), Result::factory()->retired('Seat'), Result::factory()->retired('Engine')]));

        $candidates = app(CuriousRetirementExcavator::class)->dig(new DigParameters);

        $this->assertSame(['Out of fuel', 'Out of fuel', 'Seat'], $candidates->pluck('evidence.status')->all());
        $this->assertSame([33.33, 33.33, 66.67], $candidates->pluck('score')->all());
        $this->assertSame([false, true, false], $candidates->pluck('evidence.classified')->all());
    }

    /**
     * @param  list<ResultFactory>  $results
     * @return list<array{0: Driver, 1: ResultFactory}>
     */
    private function entries(array $results): array
    {
        return array_map(fn ($result) => [Driver::factory()->create(), $result], $results);
    }
}
