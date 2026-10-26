<?php

namespace Tests\Feature\Normalizers\Jolpica;

use App\Enums\SessionType;
use App\Models\QualifyingResult;
use App\Normalizers\Jolpica\QualifyingNormalizer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\NormalizesJolpicaFixtures;
use Tests\TestCase;

class QualifyingNormalizerTest extends TestCase
{
    use LazilyRefreshDatabase, NormalizesJolpicaFixtures;

    public function test_stores_qualifying_times_in_milliseconds(): void
    {
        $this->normalizeFixtures(QualifyingNormalizer::class, 'qualifying_2024');

        $norris = QualifyingResult::with('driverEntry.raceSession')
            ->whereRelation('driverEntry.driver', 'jolpica_driver_id', 'norris')
            ->sole();

        $this->assertSame(SessionType::Qualifying, $norris->driverEntry->raceSession->type);
        $this->assertSame(4, $norris->position);
        $this->assertSame([94_842, 94_460, 94_165], [$norris->q1_millis, $norris->q2_millis, $norris->q3_millis]);
    }
}
