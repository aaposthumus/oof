<?php

namespace Tests\Concerns;

use App\Models\RaceSession;
use App\Normalizers\Jolpica\PitStopsNormalizer;
use App\Normalizers\Jolpica\RacesNormalizer;
use App\Normalizers\Jolpica\ResultsNormalizer;
use App\Normalizers\Jolpica\Support\CoreResolver;
use App\Normalizers\Normalizer;
use App\Normalizers\OpenF1\DriversNormalizer;
use App\Normalizers\OpenF1\MeetingsNormalizer;
use App\Normalizers\OpenF1\SessionsNormalizer;

/**
 * Real, trimmed OpenF1 responses from tests/Fixtures/openf1, around the 2026
 * United States Grand Prix (meeting 1297: FP1 11389, race 11396).
 */
trait NormalizesOpenF1Fixtures
{
    /**
     * @return array<mixed>
     */
    protected function openF1Fixture(string $name): array
    {
        return json_decode(file_get_contents(base_path("tests/Fixtures/openf1/{$name}.json")), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * Normalize fixtures as one normalizer run would.
     *
     * @param  class-string<Normalizer>  $normalizer
     */
    protected function normalizeOpenF1(string $normalizer, string ...$fixtures): void
    {
        app(CoreResolver::class)->flush();

        foreach ($fixtures as $fixture) {
            app($normalizer)->normalize($this->openF1Fixture($fixture));
        }
    }

    /**
     * Jolpica's 2026 rounds 16, 18 and 20 with the round 18 race results and
     * pit stops, then the OpenF1 2026 meetings and sessions matched to them.
     */
    protected function matchSeason2026(): void
    {
        $this->normalizeOpenF1(RacesNormalizer::class, 'jolpica_races_2026');
        $this->normalizeOpenF1(ResultsNormalizer::class, 'jolpica_results_2026_18');
        $this->normalizeOpenF1(PitStopsNormalizer::class, 'jolpica_pitstops_2026_18');
        $this->normalizeOpenF1(MeetingsNormalizer::class, 'meetings_2026');
        $this->normalizeOpenF1(SessionsNormalizer::class, 'sessions_2026');
    }

    /**
     * The 2026 US GP race with its OpenF1 cars matched.
     */
    protected function austinRace(): RaceSession
    {
        $this->matchSeason2026();
        $this->normalizeOpenF1(DriversNormalizer::class, 'drivers_11396');

        return RaceSession::firstWhere('openf1_session_key', 11396);
    }
}
