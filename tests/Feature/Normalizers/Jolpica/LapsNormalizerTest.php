<?php

namespace Tests\Feature\Normalizers\Jolpica;

use App\Models\LapTime;
use App\Normalizers\Jolpica\LapsNormalizer;
use App\Normalizers\Jolpica\ResultsNormalizer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\NormalizesJolpicaFixtures;
use Tests\TestCase;

class LapsNormalizerTest extends TestCase
{
    use LazilyRefreshDatabase, NormalizesJolpicaFixtures;

    public function test_attaches_lap_times_to_the_drivers_race_entry(): void
    {
        $this->normalizeFixtures(ResultsNormalizer::class, 'results_2024_page_0');
        $this->normalizeFixtures(LapsNormalizer::class, 'laps_2024_5');

        $norris = LapTime::whereRelation('driverEntry.driver', 'jolpica_driver_id', 'norris')->orderBy('lap')->get();

        $this->assertSame([104_524, 101_267], $norris->pluck('time_millis')->all());
        $this->assertSame([4, 4], $norris->pluck('position')->all());
    }

    public function test_normalizing_twice_keeps_the_same_rows(): void
    {
        $this->normalizeFixtures(ResultsNormalizer::class, 'results_2024_page_0');
        $this->normalizeFixtures(LapsNormalizer::class, 'laps_2024_5');
        $this->normalizeFixtures(LapsNormalizer::class, 'laps_2024_5');

        $this->assertSame(4, LapTime::count());
    }
}
