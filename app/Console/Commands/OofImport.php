<?php

namespace App\Console\Commands;

use App\Jolpica\Importer;
use App\Jolpica\JolpicaRequest;
use App\OpenF1\Importer as OpenF1Importer;
use App\OpenF1\OpenF1Request;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;

class OofImport extends Command
{
    private const SOURCES = ['jolpica', 'openf1', 'all'];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'oof:import
        {--source=jolpica : Where to import from: jolpica, openf1 or all}
        {--season=* : Only import these seasons}
        {--from= : First season to import}
        {--to= : Last season to import}
        {--meeting= : OpenF1 only: only import the sessions of this meeting key}
        {--laps : Jolpica only: also import lap times (1996 onwards), a long extra step}
        {--only-laps : Jolpica only: only import lap times}
        {--refresh : Fetch again even when the stored response is final}
        {--dry-run : Count the requests needed without fetching anything}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch F1 history from Jolpica-F1 and session data from OpenF1 into the raw layer (safe to re-run; resumes where it stopped)';

    /**
     * Execute the console command.
     */
    public function handle(Importer $jolpica, OpenF1Importer $openF1): int
    {
        $source = $this->option('source');

        if (! in_array($source, self::SOURCES, true)) {
            $this->components->error('The source must be one of: '.implode(', ', self::SOURCES).'.');

            return self::FAILURE;
        }

        if ($source === 'openf1' && ($this->option('laps') || $this->option('only-laps'))) {
            $this->components->error('--laps and --only-laps are Jolpica options; OpenF1 laps are always imported.');

            return self::FAILURE;
        }

        if ($source !== 'openf1' && $this->option('meeting') !== null) {
            $this->components->error('--meeting is an OpenF1 option: use it with --source=openf1.');

            return self::FAILURE;
        }

        try {
            if ($source !== 'openf1') {
                $this->importJolpica($jolpica);
            }

            if ($source !== 'jolpica') {
                $this->importOpenF1($openF1);
            }
        } catch (RequestException|ConnectionException $exception) {
            $this->components->error("Import stopped: {$exception->getMessage()}");
            $this->components->info('Everything fetched so far is stored. Run the command again to resume.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function importJolpica(Importer $importer): void
    {
        $report = $importer->import(
            years: $this->years(config('jolpica.first_season')),
            includeLaps: (bool) $this->option('laps'),
            onlyLaps: (bool) $this->option('only-laps'),
            refresh: (bool) $this->option('refresh'),
            dryRun: (bool) $this->option('dry-run'),
            onFetch: fn (JolpicaRequest $request) => $this->line("  fetched <info>{$request->requestKey()}</info>"),
        );

        if ($this->option('dry-run')) {
            $minutes = (int) ceil($report['planned'] * config('jolpica.min_request_interval_ms') / 60_000);
            $this->components->info("Jolpica: {$report['planned']} requests to fetch (about {$minutes} min), {$report['skipped']} stored responses are final.");
            $this->components->warn('Seasons without a stored schedule also need per-round requests that cannot be counted yet.');

            return;
        }

        $this->components->info("Jolpica: fetched {$report['fetched']} responses, skipped {$report['skipped']} final ones. Next: php artisan oof:normalize");
    }

    private function importOpenF1(OpenF1Importer $importer): void
    {
        $report = $importer->import(
            years: $this->years(config('openf1.first_season')),
            meetingKey: $this->option('meeting') === null ? null : (int) $this->option('meeting'),
            refresh: (bool) $this->option('refresh'),
            dryRun: (bool) $this->option('dry-run'),
            onFetch: fn (OpenF1Request $request) => $this->line("  fetched <info>{$request->requestKey()}</info>"),
        );

        if ($this->option('dry-run')) {
            $minutes = (int) ceil($report['planned'] * config('openf1.min_request_interval_ms') / 60_000);
            $this->components->info("OpenF1: {$report['planned']} requests to fetch (about {$minutes} min), {$report['skipped']} stored responses are up to date.");

            if ($report['unknown_seasons'] > 0) {
                $this->components->warn("{$report['unknown_seasons']} seasons have no stored session list yet, so their session requests cannot be counted.");
            }

            return;
        }

        $this->components->info("OpenF1: fetched {$report['fetched']} responses, skipped {$report['skipped']} up-to-date ones. Next: php artisan oof:normalize");
    }

    /**
     * @return list<int>
     */
    private function years(int $firstSeason): array
    {
        if ($this->option('season') !== []) {
            return array_map('intval', $this->option('season'));
        }

        if ($this->option('from') === null && $this->option('to') === null) {
            return [];
        }

        return range(
            max($firstSeason, (int) ($this->option('from') ?? $firstSeason)),
            (int) ($this->option('to') ?? now()->year),
        );
    }
}
