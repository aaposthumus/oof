<?php

namespace App\Jolpica;

use App\Models\RawResponse;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Closure;

/**
 * Fetches Jolpica responses into raw_responses. A stored response is fetched
 * again only while it may still change (until a few days after the race it
 * covers), so re-running resumes where an interrupted run stopped.
 */
class Importer
{
    private bool $dryRun = false;

    private bool $refresh = false;

    /** @var (Closure(JolpicaRequest): void)|null */
    private ?Closure $onFetch = null;

    /** @var array{fetched: int, skipped: int, planned: int} */
    private array $report = ['fetched' => 0, 'skipped' => 0, 'planned' => 0];

    public function __construct(private JolpicaClient $client) {}

    /**
     * @param  list<int>  $years  Seasons to import; empty for every season Jolpica lists
     * @param  bool  $dryRun  Count the requests that would be made without fetching
     * @param  (Closure(JolpicaRequest): void)|null  $onFetch  Called after each fetched request
     * @return array{fetched: int, skipped: int, planned: int}
     */
    public function import(
        array $years = [],
        bool $includeLaps = false,
        bool $onlyLaps = false,
        bool $refresh = false,
        bool $dryRun = false,
        ?Closure $onFetch = null,
    ): array {
        $this->dryRun = $dryRun;
        $this->refresh = $refresh;
        $this->onFetch = $onFetch;
        $this->report = ['fetched' => 0, 'skipped' => 0, 'planned' => 0];

        $seasonPages = $this->ensureAllPages(new JolpicaRequest('seasons'), now()->startOfYear());
        $years = $years ?: $this->listedSeasons($seasonPages);

        $schedules = [];

        foreach ($years as $year) {
            $schedules[$year] = $this->schedule($year);

            if (! $onlyLaps) {
                $this->importSeason($year, $schedules[$year]);
            }
        }

        // Lap times come last, as a separate long step, once everything else is in.
        if ($includeLaps || $onlyLaps) {
            foreach ($years as $year) {
                if ($year >= config('jolpica.laps_from')) {
                    foreach ($this->pastRounds($schedules[$year]) as $round => $date) {
                        $this->ensureAllPages(new JolpicaRequest('laps', $year, $round), $this->finalSince($date));
                    }
                }
            }
        }

        return $this->report;
    }

    /**
     * @param  array<int, array{date: CarbonImmutable, has_sprint: bool}>  $schedule
     */
    private function importSeason(int $year, array $schedule): void
    {
        $pastRounds = $this->pastRounds($schedule);

        if ($pastRounds === []) {
            return;
        }

        $seasonFinalSince = $this->finalSince(max(array_column($schedule, 'date')));

        $this->ensureAllPages(new JolpicaRequest('results', $year), $seasonFinalSince);
        $this->ensureAllPages(new JolpicaRequest('qualifying', $year), $seasonFinalSince);

        if ($year >= config('jolpica.sprint_from') && in_array(true, array_column($schedule, 'has_sprint'), true)) {
            $this->ensureAllPages(new JolpicaRequest('sprint', $year), $seasonFinalSince);
        }

        foreach ($pastRounds as $round => $date) {
            $roundFinalSince = $this->finalSince($date);

            $this->ensureAllPages(new JolpicaRequest('driverstandings', $year, $round), $roundFinalSince);

            if ($year >= config('jolpica.constructor_standings_from')) {
                $this->ensureAllPages(new JolpicaRequest('constructorstandings', $year, $round), $roundFinalSince);
            }

            if ($year >= config('jolpica.pit_stops_from')) {
                $this->ensureAllPages(new JolpicaRequest('pitstops', $year, $round), $roundFinalSince);
            }
        }
    }

    /**
     * Fetch (or reuse) a season's schedule. It is final once the season's last
     * race is a few days past.
     *
     * @return array<int, array{date: CarbonImmutable, has_sprint: bool}> Keyed by round number
     */
    private function schedule(int $year): array
    {
        $pages = $this->ensureAllPages(
            new JolpicaRequest('races', $year),
            fn (RawResponse $stored) => $this->finalSince(max(array_column($this->scheduleFrom([$stored]), 'date') ?: [now()])),
        );

        return $this->scheduleFrom($pages);
    }

    /**
     * @param  list<RawResponse>  $pages
     * @return array<int, array{date: CarbonImmutable, has_sprint: bool}>
     */
    private function scheduleFrom(array $pages): array
    {
        $schedule = [];

        foreach ($pages as $page) {
            foreach ($page->payload['MRData']['RaceTable']['Races'] as $race) {
                $schedule[(int) $race['round']] = [
                    'date' => CarbonImmutable::parse($race['date']),
                    'has_sprint' => isset($race['Sprint']),
                ];
            }
        }

        ksort($schedule);

        return $schedule;
    }

    /**
     * @param  array<int, array{date: CarbonImmutable, has_sprint: bool}>  $schedule
     * @return array<int, CarbonImmutable> Rounds that have taken place, keyed by round number
     */
    private function pastRounds(array $schedule): array
    {
        return array_filter(
            array_map(fn (array $round) => $round['date'], $schedule),
            fn (CarbonImmutable $date) => $date->lte(now()),
        );
    }

    /**
     * @param  list<RawResponse>  $pages
     * @return list<int>
     */
    private function listedSeasons(array $pages): array
    {
        $years = [];

        foreach ($pages as $page) {
            foreach ($page->payload['MRData']['SeasonTable']['Seasons'] as $season) {
                $years[] = (int) $season['season'];
            }
        }

        // A dry run before the first import has no stored season list to go by.
        return $years ?: range(config('jolpica.first_season'), now()->year);
    }

    private function finalSince(CarbonInterface $raceDate): CarbonImmutable
    {
        return CarbonImmutable::parse($raceDate)->addDays(config('jolpica.final_after_days'));
    }

    /**
     * Ensure every page of a paginated request is stored.
     *
     * @param  CarbonInterface|(Closure(RawResponse): CarbonInterface)  $finalSince
     * @return list<RawResponse>
     */
    private function ensureAllPages(JolpicaRequest $request, CarbonInterface|Closure $finalSince): array
    {
        $request = new JolpicaRequest($request->endpoint, $request->season, $request->round, 0, config('jolpica.page_limit'));
        $first = $this->ensure($request, $finalSince);

        if ($first === null) {
            return [];
        }

        $pages = [$first];

        for ($offset = $request->limit; $offset < ($first->total_rows ?? 0); $offset += $request->limit) {
            if ($page = $this->ensure($request->withOffset($offset), $finalSince)) {
                $pages[] = $page;
            }
        }

        return $pages;
    }

    /**
     * Return the stored response if it is final, otherwise fetch and store it.
     * In a dry run nothing is fetched and the stored response, if any, is returned.
     *
     * @param  CarbonInterface|(Closure(RawResponse): CarbonInterface)  $finalSince
     */
    private function ensure(JolpicaRequest $request, CarbonInterface|Closure $finalSince): ?RawResponse
    {
        $stored = RawResponse::firstWhere('request_key', $request->requestKey());

        if ($stored !== null && ! $this->refresh) {
            $since = $finalSince instanceof Closure ? $finalSince($stored) : $finalSince;

            if ($stored->fetched_at->gte($since)) {
                $this->report['skipped']++;

                return $stored;
            }
        }

        if ($this->dryRun) {
            $this->report['planned']++;

            return $stored;
        }

        $response = $this->client->get($request);
        $payload = $response->json();

        $stored = RawResponse::updateOrCreate(['request_key' => $request->requestKey()], [
            'source' => JolpicaRequest::SOURCE,
            'endpoint' => $request->endpoint,
            'url' => $request->url(),
            'parameters' => $request->parameters(),
            'season' => $request->season,
            'round' => $request->round,
            'http_status' => $response->status(),
            'total_rows' => isset($payload['MRData']['total']) ? (int) $payload['MRData']['total'] : null,
            'payload' => $payload,
            'fetched_at' => now(),
        ]);

        $this->report['fetched']++;

        if ($this->onFetch !== null) {
            ($this->onFetch)($request);
        }

        return $stored;
    }
}
