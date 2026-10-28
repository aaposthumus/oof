<?php

namespace App\OpenF1;

use App\Models\RawResponse;
use Carbon\CarbonImmutable;
use Closure;

/**
 * Fetches OpenF1 responses into raw_responses: each season's meetings and
 * sessions, then every endpoint of each session that has ended. A session's
 * responses are fetched again at a few moments after it ended and are final
 * after the last, so re-running resumes where an interrupted run stopped.
 */
class Importer
{
    private bool $dryRun = false;

    private bool $refresh = false;

    /** @var (Closure(OpenF1Request): void)|null */
    private ?Closure $onFetch = null;

    /** @var array{fetched: int, skipped: int, planned: int, unknown_seasons: int} */
    private array $report = ['fetched' => 0, 'skipped' => 0, 'planned' => 0, 'unknown_seasons' => 0];

    public function __construct(private OpenF1Client $client) {}

    /**
     * @param  list<int>  $years  Seasons to import; empty for every season from the first OpenF1 has
     * @param  int|null  $meetingKey  Only fetch the sessions of this meeting
     * @param  bool  $dryRun  Count the requests that would be made without fetching
     * @param  (Closure(OpenF1Request): void)|null  $onFetch  Called after each fetched request
     * @return array{fetched: int, skipped: int, planned: int, unknown_seasons: int}
     */
    public function import(
        array $years = [],
        ?int $meetingKey = null,
        bool $refresh = false,
        bool $dryRun = false,
        ?Closure $onFetch = null,
    ): array {
        $this->dryRun = $dryRun;
        $this->refresh = $refresh;
        $this->onFetch = $onFetch;
        $this->report = ['fetched' => 0, 'skipped' => 0, 'planned' => 0, 'unknown_seasons' => 0];

        $years = array_filter(
            $years ?: range(config('openf1.first_season'), now()->year),
            fn (int $year) => $year >= config('openf1.first_season'),
        );

        foreach ($years as $year) {
            $this->ensureListing(OpenF1Request::forSeason('meetings', $year));
            $sessions = $this->ensureListing(OpenF1Request::forSeason('sessions', $year));

            if ($sessions === null) {
                $this->report['unknown_seasons']++;

                continue;
            }

            foreach ($this->endedSessions($sessions->payload, $meetingKey) as $session) {
                foreach ($this->endpointsFor($session) as $endpoint) {
                    $this->ensureSessionData(OpenF1Request::forSession($endpoint, $session), $session);
                }
            }
        }

        return $this->report;
    }

    /**
     * Sessions of our session types that are not cancelled and have ended long
     * enough ago for their data to be in. Testing days have no session type.
     *
     * @param  list<array<string, mixed>>  $sessions
     * @return list<array<string, mixed>>
     */
    private function endedSessions(array $sessions, ?int $meetingKey): array
    {
        $firstFetchAfter = min(config('openf1.refetch_after_minutes'));

        return array_values(array_filter($sessions, fn (array $session) => isset(config('openf1.session_types')[$session['session_name']])
            && ! ($session['is_cancelled'] ?? false)
            && ($meetingKey === null || $session['meeting_key'] === $meetingKey)
            && CarbonImmutable::parse($session['date_end'])->addMinutes($firstFetchAfter)->lte(now())));
    }

    /**
     * @param  array<string, mixed>  $session
     * @return list<string>
     */
    private function endpointsFor(array $session): array
    {
        $endpoints = config('openf1.session_endpoints');

        if (in_array($session['session_name'], config('openf1.grid_session_names'), true)) {
            $endpoints[] = 'starting_grid';
        }

        return $endpoints;
    }

    /**
     * A season's listing is fetched again when older than the maximum age,
     * until it was fetched a few days after the season's year ended.
     */
    private function ensureListing(OpenF1Request $request): ?RawResponse
    {
        $finalSince = CarbonImmutable::create($request->year)->endOfYear()->addDays(config('openf1.listing_final_after_days'));
        $staleBefore = now()->subHours(config('openf1.listing_max_age_hours'));

        return $this->ensure($request, fn (RawResponse $stored) => $stored->fetched_at->lt($finalSince) && $stored->fetched_at->lt($staleBefore));
    }

    /**
     * Session data is fetched again once each refetch moment after the session
     * ended has passed since it was last fetched.
     *
     * @param  array<string, mixed>  $session
     */
    private function ensureSessionData(OpenF1Request $request, array $session): void
    {
        $endedAt = CarbonImmutable::parse($session['date_end']);
        $passedMoments = array_filter(
            array_map(fn (int $minutes) => $endedAt->addMinutes($minutes), config('openf1.refetch_after_minutes')),
            fn (CarbonImmutable $moment) => $moment->lte(now()),
        );
        $latestMoment = max($passedMoments);

        $this->ensure($request, fn (RawResponse $stored) => $stored->fetched_at->lt($latestMoment));
    }

    /**
     * Return the stored response unless it is stale, otherwise fetch and store it.
     * In a dry run nothing is fetched and the stored response, if any, is returned.
     *
     * @param  Closure(RawResponse): bool  $isStale
     */
    private function ensure(OpenF1Request $request, Closure $isStale): ?RawResponse
    {
        $stored = RawResponse::firstWhere('request_key', $request->requestKey());

        if ($stored !== null && ! $this->refresh && ! $isStale($stored)) {
            $this->report['skipped']++;

            return $stored;
        }

        if ($this->dryRun) {
            $this->report['planned']++;

            return $stored;
        }

        $response = $this->client->get($request);
        $rows = OpenF1Client::rows($response);

        $stored = RawResponse::updateOrCreate(['request_key' => $request->requestKey()], [
            'source' => OpenF1Request::SOURCE,
            'endpoint' => $request->endpoint,
            'url' => $request->url(),
            'parameters' => $request->parameters(),
            'season' => $request->year,
            'round' => null,
            'http_status' => $response->status(),
            'total_rows' => count($rows),
            'payload' => $rows,
            'fetched_at' => now(),
        ]);

        $this->report['fetched']++;

        if ($this->onFetch !== null) {
            ($this->onFetch)($request);
        }

        return $stored;
    }
}
