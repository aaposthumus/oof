<?php

namespace Tests\Feature\Excavation\Excavators;

use App\Excavation\Candidate;
use App\Excavation\DigParameters;
use App\Excavation\Excavators\FewestStartersExcavator;
use App\Models\Season;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\BuildsRaces;
use Tests\TestCase;

class FewestStartersExcavatorTest extends TestCase
{
    use BuildsRaces, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'excavators.fewest_starters.max_ratio' => 0.75,
            'excavators.fewest_starters.max_starters' => 0,
        ]);
    }

    public function test_scores_starters_against_the_season_median_without_counting_withdrawn_cars(): void
    {
        $season = Season::factory()->create(['year' => 2005]);
        $this->createRace($season, $this->field(running: 10));
        $this->createRace($season, $this->field(running: 10));
        $small = $this->createRace($season, $this->field(running: 4, withdrawn: 6), 'United States Grand Prix');

        $candidates = app(FewestStartersExcavator::class)->dig(new DigParameters);

        $this->assertCount(1, $candidates);
        $this->assertEquals(new Candidate(
            roundId: $small->id,
            driverId: null,
            seasonTeamId: null,
            score: 60.0,
            evidence: ['starters' => 4, 'entries' => 10, 'season_median_starters' => 10.0, 'ratio' => 0.4],
        ), $candidates->sole());
    }

    public function test_judges_a_field_against_its_own_era(): void
    {
        $bigFieldEra = Season::factory()->create(['year' => 1989]);
        $this->createRace($bigFieldEra, $this->field(running: 10));
        $this->createRace($bigFieldEra, $this->field(running: 10));
        $smallForItsEra = $this->createRace($bigFieldEra, $this->field(running: 7));

        $smallFieldEra = Season::factory()->create(['year' => 1966]);
        $this->createRace($smallFieldEra, $this->field(running: 7));
        $this->createRace($smallFieldEra, $this->field(running: 7));

        $candidates = app(FewestStartersExcavator::class)->dig(new DigParameters);

        $this->assertSame([$smallForItsEra->id], $candidates->pluck('roundId')->all());
    }

    public function test_hits_an_absolutely_small_field_even_in_a_small_field_era(): void
    {
        config(['excavators.fewest_starters.max_starters' => 7]);
        $season = Season::factory()->create(['year' => 1958]);
        $this->createRace($season, $this->field(running: 7));

        $candidates = app(FewestStartersExcavator::class)->dig(new DigParameters);

        $this->assertCount(1, $candidates);
        $this->assertSame(0.0, $candidates->sole()->score);
    }

    public function test_skips_the_indianapolis_500(): void
    {
        $season = Season::factory()->create(['year' => 1955]);
        $this->createRace($season, $this->field(running: 10));
        $this->createRace($season, $this->field(running: 10));
        $this->createRace($season, $this->field(running: 3), 'Indianapolis 500');

        $candidates = app(FewestStartersExcavator::class)->dig(new DigParameters);

        $this->assertCount(0, $candidates);
    }

    public function test_digs_only_in_the_selected_seasons(): void
    {
        $rounds = collect([1990, 2000, 2010])->mapWithKeys(function (int $year) {
            $season = Season::factory()->create(['year' => $year]);
            $this->createRace($season, $this->field(running: 10));
            $this->createRace($season, $this->field(running: 10));

            return [$year => $this->createRace($season, $this->field(running: 2))->id];
        });

        $excavator = app(FewestStartersExcavator::class);

        $this->assertSame([$rounds[2000]], $excavator->dig(new DigParameters(seasons: [2000]))->pluck('roundId')->all());
        $this->assertSame([$rounds[2000], $rounds[2010]], $excavator->dig(new DigParameters(from: 2000))->pluck('roundId')->sort()->values()->all());
        $this->assertSame([$rounds[1990], $rounds[2000]], $excavator->dig(new DigParameters(to: 2000))->pluck('roundId')->sort()->values()->all());
    }
}
