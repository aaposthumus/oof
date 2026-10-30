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
use App\Normalizers\OpenF1\DriversNormalizer;
use App\Normalizers\OpenF1\LapsNormalizer as OpenF1LapsNormalizer;
use App\Normalizers\OpenF1\MeetingsNormalizer;
use App\Normalizers\OpenF1\PitNormalizer;
use App\Normalizers\OpenF1\PositionNormalizer;
use App\Normalizers\OpenF1\RaceControlNormalizer;
use App\Normalizers\OpenF1\SessionResultNormalizer;
use App\Normalizers\OpenF1\SessionsNormalizer;
use App\Normalizers\OpenF1\StintsNormalizer;
use App\Normalizers\OpenF1\TeamRadioNormalizer;
use App\Normalizers\OpenF1\WeatherNormalizer;
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
     * Normalizers per source, in processing order. OpenF1 comes after Jolpica,
     * as it is matched to the rounds, sessions and entries Jolpica creates.
     *
     * @var array<string, list<class-string<Normalizer>>>
     */
    public const NORMALIZERS = [
        'jolpica' => [
            SeasonsNormalizer::class,
            RacesNormalizer::class,
            ResultsNormalizer::class,
            SprintResultsNormalizer::class,
            QualifyingNormalizer::class,
            DriverStandingsNormalizer::class,
            ConstructorStandingsNormalizer::class,
            PitStopsNormalizer::class,
            LapsNormalizer::class,
        ],
        'openf1' => [
            MeetingsNormalizer::class,
            SessionsNormalizer::class,
            DriversNormalizer::class,
            RaceControlNormalizer::class,
            StintsNormalizer::class,
            OpenF1LapsNormalizer::class,
            PitNormalizer::class,
            PositionNormalizer::class,
            SessionResultNormalizer::class,
            WeatherNormalizer::class,
            TeamRadioNormalizer::class,
        ],
    ];

    /**
     * Endpoints whose entries make up a season's roster.
     */
    private const ROSTER_ENDPOINTS = ['results', 'sprint', 'qualifying'];

    /** @var array<string, array{normalized: int, deferred: int}> */
    private array $stats = [];

    /** @var array<int, true> Seasons whose roster needs rebuilding */
    private array $rosterYears = [];

    public function __construct(
        private Container $container,
        private CoreResolver $core,
        private SeasonRosterBuilder $roster,
    ) {}

    /**
     * @param  list<int>  $years  Only these seasons; empty for all
     * @param  bool  $all  Also normalize responses that were normalized before
     * @param  callable(string, int, int): void|null  $onEndpointDone  Receives endpoint, normalized count, deferred count
     * @param  string|null  $source  Only this source; null for all
     * @return array<string, array{normalized: int, deferred: int}> Keyed by endpoint, prefixed with "openf1:" for OpenF1
     */
    public function run(array $years = [], bool $all = false, bool $includeLaps = false, ?callable $onEndpointDone = null, ?string $source = null): array
    {
        $this->stats = [];
        $this->rosterYears = [];

        foreach (self::NORMALIZERS as $normalizerSource => $classes) {
            if ($source !== null && $source !== $normalizerSource) {
                continue;
            }

            foreach ($classes as $class) {
                $this->runNormalizer($this->container->make($class), $normalizerSource, $years, $all, $includeLaps, $onEndpointDone);
            }
        }

        Season::whereIn('year', array_keys($this->rosterYears))->each(fn (Season $season) => $this->roster->rebuild($season));

        return $this->stats;
    }

    /**
     * @param  list<int>  $years
     * @param  callable(string, int, int): void|null  $onEndpointDone
     */
    private function runNormalizer(Normalizer $normalizer, string $source, array $years, bool $all, bool $includeLaps, ?callable $onEndpointDone): void
    {
        $endpoint = $normalizer->endpoint();
        $label = $source === 'jolpica' ? $endpoint : "{$source}:{$endpoint}";

        if ($source === 'jolpica' && $endpoint === 'laps' && ! $includeLaps) {
            return;
        }

        $this->stats[$label] = ['normalized' => 0, 'deferred' => 0];

        // Only IDs are sorted: sorting rows with their payloads exhausts MySQL's sort buffer.
        $ids = RawResponse::query()
            ->where('source', $source)
            ->where('endpoint', $endpoint)
            ->when($years !== [], fn (Builder $query) => $query->where(
                fn (Builder $query) => $query->whereIn('season', $years)->orWhereNull('season'),
            ))
            ->unless($all, fn (Builder $query) => $query->pendingNormalization())
            ->orderBy('id')
            ->pluck('id');

        foreach ($ids as $id) {
            $response = RawResponse::find($id);

            if ($this->normalizeResponse($normalizer, $response)) {
                $this->stats[$label]['normalized']++;

                if ($source === 'jolpica' && in_array($endpoint, self::ROSTER_ENDPOINTS, true)) {
                    $this->rosterYears[$response->season] = true;
                }
            } else {
                $this->stats[$label]['deferred']++;
            }
        }

        if ($onEndpointDone !== null) {
            $onEndpointDone($label, $this->stats[$label]['normalized'], $this->stats[$label]['deferred']);
        }
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
