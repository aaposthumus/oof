<?php

namespace App\Console\Commands;

use App\Achievements\GoldenMaster;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OofAchievementsGolden extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'oof:achievements:golden
        {--export : Write the golden master from what the engine gives now, instead of comparing with it}
        {--force : With --export, replace an existing golden master}
        {--season=* : Only compare these seasons (default: every season in the golden master)}
        {--limit=20 : How many differences to list; 0 lists them all}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Compare a dry run of the achievements engine with the golden master (stores no awards)';

    /**
     * Execute the console command.
     */
    public function handle(GoldenMaster $goldenMaster): int
    {
        $seasons = array_map('intval', $this->option('season'));

        return $this->option('export') ? $this->export($goldenMaster, $seasons) : $this->compare($goldenMaster, $seasons);
    }

    /**
     * @param  list<int>  $seasons
     */
    private function export(GoldenMaster $goldenMaster, array $seasons): int
    {
        if ($seasons !== []) {
            $this->components->error('The golden master always covers every season: export without --season.');

            return self::FAILURE;
        }

        if ($goldenMaster->exists() && ! $this->option('force')) {
            $this->components->error('A golden master exists already. Replace it with --force once its differences are the ones you intended.');

            return self::FAILURE;
        }

        $snapshot = $this->dryRun($goldenMaster, []);
        $goldenMaster->write($snapshot);

        $this->components->info(sprintf(
            'Golden master written: %d awards in %d sessions (storage/app/private/%s).',
            count($snapshot['awards']),
            count($snapshot['sessions']),
            GoldenMaster::PATH,
        ));

        return self::SUCCESS;
    }

    /**
     * @param  list<int>  $seasons
     */
    private function compare(GoldenMaster $goldenMaster, array $seasons): int
    {
        if (! $goldenMaster->exists()) {
            $this->components->error('There is no golden master yet. Write one with --export.');

            return self::FAILURE;
        }

        $master = $goldenMaster->read();
        $diff = $goldenMaster->diff($master, $this->dryRun($goldenMaster, $seasons), $seasons);
        $differences = count($diff['missing']) + count($diff['added']) + count($diff['changed']);

        $this->components->info("Compared {$diff['compared']} awards with the golden master of {$master['exported_at']}.");

        if ($diff['uncovered'] !== []) {
            $this->components->warn(sprintf(
                '%d sessions were first run after the export and are not compared (%s to %s).',
                count($diff['uncovered']),
                $diff['uncovered'][0],
                $diff['uncovered'][count($diff['uncovered']) - 1],
            ));
        }

        if ($differences === 0) {
            $this->components->info('No differences.');

            return self::SUCCESS;
        }

        $this->components->error(sprintf(
            '%d differences: %d missing, %d added, %d changed.',
            $differences,
            count($diff['missing']),
            count($diff['added']),
            count($diff['changed']),
        ));
        $this->table(
            ['Achievement', 'Missing', 'Added', 'Changed'],
            $goldenMaster->countsPerAchievement($diff)->map(fn (array $counts, string $key) => [$key, ...array_values($counts)])->values()->all(),
        );
        $this->listDifferences($diff, $differences);

        return self::FAILURE;
    }

    /**
     * Run the engine and read what it gives, then roll everything back.
     *
     * @param  list<int>  $seasons
     * @return array{sessions: list<string>, awards: array<string, array<string, string|int|null>>}
     */
    private function dryRun(GoldenMaster $goldenMaster, array $seasons): array
    {
        DB::beginTransaction();

        try {
            if ($this->callSilently('oof:achievements', ['--season' => $seasons]) !== self::SUCCESS) {
                throw new RuntimeException('oof:achievements failed, so there is nothing to compare.');
            }

            return $goldenMaster->snapshot($seasons);
        } finally {
            DB::rollBack();
        }
    }

    /**
     * @param  array{missing: array<string, array<string, string|int|null>>, added: array<string, array<string, string|int|null>>, changed: array<string, array<string, array{0: string|int|null, 1: string|int|null}>>}  $diff
     */
    private function listDifferences(array $diff, int $differences): void
    {
        $describe = fn (array $award) => implode(' · ', array_filter([$award['driver'], $award['detail'], $award['status']]));
        $lines = [
            ...collect($diff['missing'])->map(fn (array $award, string $key) => "missing  {$key}  ({$describe($award)})")->values(),
            ...collect($diff['added'])->map(fn (array $award, string $key) => "added    {$key}  ({$describe($award)})")->values(),
            ...collect($diff['changed'])->map(fn (array $fields, string $key) => "changed  {$key}  ".collect($fields)
                ->map(fn (array $values, string $field) => "{$field}: ".json_encode($values[0]).' -> '.json_encode($values[1]))
                ->implode(', '))->values(),
        ];
        $limit = (int) $this->option('limit');
        $shown = $limit > 0 ? array_slice($lines, 0, $limit) : $lines;

        foreach ($shown as $line) {
            $this->line($line);
        }

        if (count($shown) < $differences) {
            $this->line(sprintf('... and %d more (--limit=0 lists them all).', $differences - count($shown)));
        }
    }
}
