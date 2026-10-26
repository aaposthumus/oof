<?php

namespace App\Normalizers;

use App\Models\RawResponse;
use App\Models\Season;
use App\Normalizers\Jolpica\ConstructorStandingsNormalizer;
use App\Normalizers\Jolpica\DriverStandingsNormalizer;
use App\Normalizers\Jolpica\LapsNormalizer;
use App\Normalizers\Jolpica\PitStopsNormalizer;
use App\Normalizers\Jolpica\QualifyingNormalizer;
use App\Normalizers\Jolpica\RacesNormalizer;
use App\Normalizers\Jolpica\ResultsNormalizer;
use App\Normalizers\Jolpica\SeasonsNormalizer;
use App\Normalizers\Jolpica\SprintResultsNormalizer;
use App\Normalizers\Jolpica\Support\CoreResolver;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Feeds pending raw responses to their normalizers, one transaction per
 * response, and marks each one normalized. Safe to interrupt and re-run.
 */
class NormalizationRunner
{
    /**
     * Normalizers in processing order.
     *
     * @var list<class-string<Normalizer>>
     */
    public const NORMALIZERS = [
        SeasonsNormalizer::class,
        RacesNormalizer::class,
        ResultsNormalizer::class,
        SprintResultsNormalizer::class,
        QualifyingNormalizer::class,
        DriverStandingsNormalizer::class,
        ConstructorStandingsNormalizer::class,
        PitStopsNormalizer::class,
        LapsNormalizer::class,
    ];

    /**
     * Endpoints whose entries make up a season's roster.
     */
    private const ROSTER_ENDPOINTS = ['results', 'sprint', 'qualifying'];

    public function __construct(
        private Container $container,
        private CoreResolver $core,
        private SeasonRosterBuilder $roster,
    ) {}

    /**
     * @param  list<int>  $years  Only these seasons; empty for all
     * @param  bool  $all  Also normalize responses that were normalized before
     * @param  callable(string, int, int): void|null  $onEndpointDone  Receives endpoint, normalized count, deferred count
     * @return array<string, array{normalized: int, deferred: int}>
     */
    public function run(array $years = [], bool $all = false, bool $includeLaps = false, ?callable $onEndpointDone = null): array
    {
        $stats = [];
        $rosterYears = [];

        foreach (self::NORMALIZERS as $class) {
            /** @var Normalizer $normalizer */
            $normalizer = $this->container->make($class);
            $endpoint = $normalizer->endpoint();

            if ($endpoint === 'laps' && ! $includeLaps) {
                continue;
            }

            $stats[$endpoint] = ['normalized' => 0, 'deferred' => 0];

            $responses = RawResponse::query()
                ->where('source', 'jolpica')
                ->where('endpoint', $endpoint)
                ->when($years !== [], fn (Builder $query) => $query->where(
                    fn (Builder $query) => $query->whereIn('season', $years)->orWhereNull('season'),
                ))
                ->unless($all, fn (Builder $query) => $query->pendingNormalization())
                ->lazyById(20);

            foreach ($responses as $response) {
                if ($this->normalizeResponse($normalizer, $response)) {
                    $stats[$endpoint]['normalized']++;

                    if (in_array($endpoint, self::ROSTER_ENDPOINTS, true)) {
                        $rosterYears[$response->season] = true;
                    }
                } else {
                    $stats[$endpoint]['deferred']++;
                }
            }

            if ($onEndpointDone !== null) {
                $onEndpointDone($endpoint, $stats[$endpoint]['normalized'], $stats[$endpoint]['deferred']);
            }
        }

        Season::whereIn('year', array_keys($rosterYears))->each(fn (Season $season) => $this->roster->rebuild($season));

        return $stats;
    }

    /**
     * @return bool False when the response depends on rows that do not exist yet
     */
    private function normalizeResponse(Normalizer $normalizer, RawResponse $response): bool
    {
        try {
            DB::transaction(function () use ($normalizer, $response) {
                $normalizer->normalize($response->payload);
                $response->update(['normalized_at' => now()]);
            });

            return true;
        } catch (MissingDependencyException $exception) {
            // Rows created inside the rolled-back transaction may be cached.
            $this->core->flush();
            Log::warning("Deferred {$response->request_key}: {$exception->getMessage()}");

            return false;
        } catch (Throwable $exception) {
            $this->core->flush();

            throw $exception;
        }
    }
}
