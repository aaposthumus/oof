<?php

namespace Tests\Feature\Normalizers\Jolpica;

use App\Models\ConstructorStanding;
use App\Normalizers\Jolpica\ConstructorStandingsNormalizer;
use App\Normalizers\Jolpica\ResultsNormalizer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\NormalizesJolpicaFixtures;
use Tests\TestCase;

class ConstructorStandingsNormalizerTest extends TestCase
{
    use LazilyRefreshDatabase, NormalizesJolpicaFixtures;

    public function test_stores_constructor_standings_after_the_round(): void
    {
        $this->normalizeFixtures(ResultsNormalizer::class, 'results_2024_page_0');
        $this->normalizeFixtures(ConstructorStandingsNormalizer::class, 'constructorstandings_2024_5');

        $leader = ConstructorStanding::with('seasonTeam.team')->where('position', 1)->sole();

        $this->assertSame('red_bull', $leader->seasonTeam->team->slug);
        $this->assertSame('195.00', $leader->points);
        $this->assertSame(2, ConstructorStanding::count());
    }
}
