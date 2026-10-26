<?php

namespace App\Console\Commands;

use App\Normalizers\NormalizationRunner;
use Illuminate\Console\Command;

class OofNormalize extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'oof:normalize
        {--season=* : Only normalize these seasons}
        {--all : Also normalize responses that were normalized before}
        {--laps : Also normalize lap times}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Translate raw Jolpica responses into the core tables (safe to re-run; resumes where it stopped)';

    /**
     * Execute the console command.
     */
    public function handle(NormalizationRunner $runner): int
    {
        $runner->run(
            years: array_map('intval', $this->option('season')),
            all: (bool) $this->option('all'),
            includeLaps: (bool) $this->option('laps'),
            onEndpointDone: function (string $endpoint, int $normalized, int $deferred) {
                $deferredNote = $deferred > 0 ? ", <comment>{$deferred} deferred</comment>" : '';
                $this->components->twoColumnDetail($endpoint, "{$normalized} normalized{$deferredNote}");
            },
        );

        return self::SUCCESS;
    }
}
