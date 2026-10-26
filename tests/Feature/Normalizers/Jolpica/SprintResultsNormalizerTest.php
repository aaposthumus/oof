<?php

namespace Tests\Feature\Normalizers\Jolpica;

use App\Models\Result;
use App\Normalizers\Jolpica\SprintResultsNormalizer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\NormalizesJolpicaFixtures;
use Tests\TestCase;

class SprintResultsNormalizerTest extends TestCase
{
    use LazilyRefreshDatabase, NormalizesJolpicaFixtures;

    public function test_stores_sprint_results_in_a_sprint_session(): void
    {
        $this->normalizeFixtures(SprintResultsNormalizer::class, 'sprint_2024');

        $this->assertSame(0, Result::race()->count());
        $this->assertSame(['8.00', '3.00'], Result::sprint()->orderBy('classification_order')->pluck('points')->all());
    }
}
