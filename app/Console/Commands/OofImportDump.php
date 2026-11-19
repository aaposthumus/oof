<?php

namespace App\Console\Commands;

use App\Jolpica\Dumps\DumpDownloader;
use App\Jolpica\Dumps\JolpicaDump;
use App\Jolpica\Dumps\LapDumpImporter;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use RuntimeException;

class OofImportDump extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'oof:import:dump
        {--file= : A Jolpica CSV dump (zip) already on disk; without it the latest free dump is fetched}
        {--season=* : Only the laps of these seasons}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import Jolpica lap times from its free CSV database dump instead of the paced API (safe to re-run)';

    /**
     * Execute the console command.
     */
    public function handle(DumpDownloader $downloader, LapDumpImporter $importer): int
    {
        try {
            $dump = new JolpicaDump($this->option('file') ?? $this->download($downloader));
        } catch (RequestException|ConnectionException|RuntimeException $exception) {
            $this->components->error("No dump to import: {$exception->getMessage()}");

            return self::FAILURE;
        }

        $report = $importer->import(
            $dump,
            array_map('intval', $this->option('season')),
            fn (int $year, int $round, int $laps) => $this->line("  {$year} round {$round}: <info>{$laps}</info> laps"),
        );

        $this->components->info("Jolpica dump: {$report['laps']} laps in {$report['races']} races.");

        if ($report['unmatched_laps'] > 0) {
            $this->components->warn("{$report['unmatched_laps']} laps had no single race entry for their driver (not in the results, or a shared drive) and were skipped.");
        }

        if ($report['missing_rounds'] !== []) {
            $this->components->warn('Rounds not in the database, skipped: '.implode(', ', $report['missing_rounds']).'. Normalize their results first.');
        }

        return self::SUCCESS;
    }

    private function download(DumpDownloader $downloader): string
    {
        $dump = $downloader->latest();
        $verb = $dump['downloaded'] ? 'Fetched' : 'Using the stored';

        $this->components->info("{$verb} free Jolpica dump of {$dump['uploaded_at']->toDateString()} (laps of later races come from oof:import --only-laps).");

        return $dump['path'];
    }
}
