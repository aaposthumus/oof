<?php

namespace Tests\Feature\Normalizers\Jolpica;

use App\Models\DriverStanding;
use App\Normalizers\Jolpica\DriverStandingsNormalizer;
use App\Normalizers\Jolpica\ResultsNormalizer;
use App\Normalizers\MissingDependencyException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\NormalizesJolpicaFixtures;
use Tests\TestCase;

class DriverStandingsNormalizerTest extends TestCase
{
    use LazilyRefreshDatabase, NormalizesJolpicaFixtures;

    public function test_stores_driver_standings_after_the_round(): void
    {
        $this->normalizeFixtures(ResultsNormalizer::class, 'results_2024_page_0');
        $this->normalizeFixtures(DriverStandingsNormalizer::class, 'driverstandings_2024_5');

        $standings = DriverStanding::with('round', 'driver')->orderBy('position')->get();

        $this->assertSame(['max_verstappen', 'norris'], $standings->pluck('driver.jolpica_driver_id')->all());
        $this->assertSame(5, $standings->first()->round->round_number);
        $this->assertSame('110.00', $standings->first()->points);
        $this->assertSame(4, $standings->first()->wins);
    }

    public function test_defers_standings_of_a_round_that_is_not_normalized_yet(): void
    {
        $this->expectException(MissingDependencyException::class);

        $this->normalizeFixtures(DriverStandingsNormalizer::class, 'driverstandings_2024_5');
    }
}
