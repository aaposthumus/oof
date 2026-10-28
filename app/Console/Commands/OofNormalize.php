<?php

namespace App\Console\Commands;

use App\Normalizers\NormalizationRunner;
use App\OpenF1\MatchReport;
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
        {--laps : Also normalize Jolpica lap times}
        {--source= : Only normalize this source: jolpica or openf1}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Translate raw Jolpica and OpenF1 responses into the core tables (safe to re-run; resumes where it stopped)';

    /**
     * Execute the console command.
     */
    public function handle(NormalizationRunner $runner, MatchReport $openF1): int
    {
        $runner->run(
            years: array_map('intval', $this->option('season')),
            all: (bool) $this->option('all'),
            includeLaps: (bool) $this->option('laps'),
            source: $this->option('source'),
            onEndpointDone: function (string $endpoint, int $normalized, int $deferred) {
                $deferredNote = $deferred > 0 ? ", <comment>{$deferred} deferred</comment>" : '';
                $this->components->twoColumnDetail($endpoint, "{$normalized} normalized{$deferredNote}");
            },
        );

        if (in_array($this->option('source'), [null, 'openf1'], true)) {
            $this->newLine();
            $this->components->info('OpenF1 matching (details: php artisan oof:status)');

            foreach ($openF1->counts() as $ledger => $counts) {
                $this->components->twoColumnDetail(ucfirst($ledger), collect($counts)->map(fn (int $count, string $status) => "{$count} {$status}")->implode(', ') ?: 'none');
            }
        }

        return self::SUCCESS;
    }
}
