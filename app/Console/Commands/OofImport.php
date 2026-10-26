<?php

namespace App\Console\Commands;

use App\Jolpica\Importer;
use App\Jolpica\JolpicaRequest;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;

class OofImport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'oof:import
        {--season=* : Only import these seasons}
        {--from= : First season to import}
        {--to= : Last season to import}
        {--laps : Also import lap times (1996 onwards), a long extra step}
        {--only-laps : Only import lap times}
        {--refresh : Fetch again even when the stored response is final}
        {--dry-run : Count the requests needed without fetching anything}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch F1 history from Jolpica-F1 into the raw layer (safe to re-run; resumes where it stopped)';

    /**
     * Execute the console command.
     */
    public function handle(Importer $importer): int
    {
        $onFetch = fn (JolpicaRequest $request) => $this->line("  fetched <info>{$request->requestKey()}</info>");

        try {
            $report = $importer->import(
                years: $this->years(),
                includeLaps: (bool) $this->option('laps'),
                onlyLaps: (bool) $this->option('only-laps'),
                refresh: (bool) $this->option('refresh'),
                dryRun: (bool) $this->option('dry-run'),
                onFetch: $onFetch,
            );
        } catch (RequestException|ConnectionException $exception) {
            $this->components->error("Import stopped: {$exception->getMessage()}");
            $this->components->info('Everything fetched so far is stored. Run the command again to resume.');

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $minutes = (int) ceil($report['planned'] * config('jolpica.min_request_interval_ms') / 60_000);
            $this->components->info("{$report['planned']} requests to fetch (about {$minutes} min), {$report['skipped']} stored responses are final.");
            $this->components->warn('Seasons without a stored schedule also need per-round requests that cannot be counted yet.');

            return self::SUCCESS;
        }

        $this->components->info("Fetched {$report['fetched']} responses, skipped {$report['skipped']} final ones. Next: php artisan oof:normalize");

        return self::SUCCESS;
    }

    /**
     * @return list<int>
     */
    private function years(): array
    {
        if ($this->option('season') !== []) {
            return array_map('intval', $this->option('season'));
        }

        if ($this->option('from') === null && $this->option('to') === null) {
            return [];
        }

        return range(
            (int) ($this->option('from') ?? config('jolpica.first_season')),
            (int) ($this->option('to') ?? now()->year),
        );
    }
}
