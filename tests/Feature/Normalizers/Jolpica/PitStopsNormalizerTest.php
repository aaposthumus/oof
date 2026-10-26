<?php

namespace Tests\Feature\Normalizers\Jolpica;

use App\Models\PitStop;
use App\Normalizers\Jolpica\PitStopsNormalizer;
use App\Normalizers\Jolpica\ResultsNormalizer;
use App\Normalizers\MissingDependencyException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\NormalizesJolpicaFixtures;
use Tests\TestCase;

class PitStopsNormalizerTest extends TestCase
{
    use LazilyRefreshDatabase, NormalizesJolpicaFixtures;

    public function test_attaches_pit_stops_to_the_drivers_race_entry(): void
    {
        $this->normalizeFixtures(ResultsNormalizer::class, 'results_2024_page_0');
        $this->normalizeFixtures(PitStopsNormalizer::class, 'pitstops_2024_5');

        $stops = PitStop::whereRelation('driverEntry.driver', 'jolpica_driver_id', 'max_verstappen')->orderBy('stop')->get();

        $this->assertSame([13, 23], $stops->pluck('lap')->all());
        $this->assertSame(22_456, $stops->first()->duration_millis);
        $this->assertSame('15:25:33', $stops->first()->time_of_day);
    }

    public function test_defers_pit_stops_until_race_results_are_normalized(): void
    {
        $this->expectException(MissingDependencyException::class);

        $this->normalizeFixtures(PitStopsNormalizer::class, 'pitstops_2024_5');
    }
}
