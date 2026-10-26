<?php

namespace Tests\Concerns;

use App\Models\RawResponse;
use App\Normalizers\Jolpica\Support\CoreResolver;
use App\Normalizers\Normalizer;

trait NormalizesJolpicaFixtures
{
    /**
     * @return array<string, mixed>
     */
    protected function jolpicaFixture(string $name): array
    {
        return json_decode(file_get_contents(base_path("tests/Fixtures/jolpica/{$name}.json")), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * Normalize fixtures as one normalizer run would.
     *
     * @param  class-string<Normalizer>  $normalizer
     */
    protected function normalizeFixtures(string $normalizer, string ...$fixtures): void
    {
        app(CoreResolver::class)->flush();

        foreach ($fixtures as $fixture) {
            app($normalizer)->normalize($this->jolpicaFixture($fixture));
        }
    }

    /**
     * Store a fixture in the raw layer as the importer would.
     */
    protected function storeRawFixture(string $fixture, string $endpoint, ?int $season = null, ?int $round = null, int $offset = 0): RawResponse
    {
        return RawResponse::create([
            'source' => 'jolpica',
            'endpoint' => $endpoint,
            'request_key' => implode(':', ['jolpica', $endpoint, $season ?? '-', $round ?? '-', $offset]),
            'url' => "{$fixture}.json",
            'parameters' => compact('season', 'round', 'offset'),
            'season' => $season,
            'round' => $round,
            'http_status' => 200,
            'payload' => $this->jolpicaFixture($fixture),
            'fetched_at' => now(),
        ]);
    }
}
