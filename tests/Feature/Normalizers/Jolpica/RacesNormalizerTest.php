<?php

namespace Tests\Feature\Normalizers\Jolpica;

use App\Enums\SessionType;
use App\Models\RaceSession;
use App\Models\Round;
use App\Normalizers\Jolpica\RacesNormalizer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\NormalizesJolpicaFixtures;
use Tests\TestCase;

class RacesNormalizerTest extends TestCase
{
    use LazilyRefreshDatabase, NormalizesJolpicaFixtures;

    public function test_names_round_after_its_grand_prix_and_not_its_circuit(): void
    {
        $this->normalizeFixtures(RacesNormalizer::class, 'races_2026');

        $round = Round::with('circuit')->where('round_number', 16)->sole();

        $this->assertSame('Bahrain Grand Prix', $round->name);
        $this->assertSame('Bahrain', $round->short_name);
        $this->assertSame('BHR', $round->country_code);
        $this->assertSame('Bahrain Grand Prix in Malaysia', $round->jolpica_race_name);
        $this->assertSame('sepang', $round->circuit->jolpica_circuit_id);
        $this->assertSame('Malaysia', $round->circuit->country);
    }

    public function test_creates_scheduled_race_qualifying_and_sprint_sessions(): void
    {
        $this->normalizeFixtures(RacesNormalizer::class, 'races_2026');

        $singapore = Round::where('round_number', 17)->sole();
        $types = $singapore->raceSessions()->pluck('type')->map(fn (SessionType $type) => $type->value)->sort()->values()->all();

        $this->assertSame(['qualifying', 'race', 'sprint', 'sprint_qualifying'], $types);
        $this->assertSame('2026-10-11', $singapore->date->toDateString());
        $this->assertSame('12:00:00', $singapore->time);
    }

    public function test_treats_2023_sprint_shootout_as_sprint_qualifying(): void
    {
        $this->normalizeFixtures(RacesNormalizer::class, 'races_2023');

        $this->assertTrue(RaceSession::where('type', SessionType::SprintQualifying)->exists());
    }

    public function test_normalizing_twice_keeps_the_same_rows(): void
    {
        $this->normalizeFixtures(RacesNormalizer::class, 'races_2026');
        $this->normalizeFixtures(RacesNormalizer::class, 'races_2026');

        $this->assertSame(2, Round::count());
        $this->assertSame(6, RaceSession::count());
    }
}
