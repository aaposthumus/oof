<?php

namespace App\Console\Commands;

use App\Achievements\CatalogueSync;
use Illuminate\Console\Command;

class OofAchievementsSync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'oof:achievements:sync
        {--dry-run : List what would change, change nothing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Write the catalogue\'s mechanics (sessions, stacks, withheld on sensitive weekends) and derived data needs to the achievements table, and create missing achievements';

    /**
     * Execute the console command.
     */
    public function handle(CatalogueSync $sync): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $plan = $dryRun ? $sync->plan() : $sync->apply();

        foreach ($plan['create'] as $key) {
            $this->components->twoColumnDetail($key, $dryRun ? 'would be created' : 'created');
        }

        if ($plan['update'] !== []) {
            $this->table(['Achievement', 'Field', 'Now', 'Catalogue'], collect($plan['update'])->map(fn (array $change) => [
                $change['key'],
                $change['field'],
                self::show($change['from']),
                self::show($change['to']),
            ])->all());
        }

        foreach ($plan['unknown'] as $key) {
            $this->components->warn("{$key} is not in the catalogue (left as it is).");
        }

        $this->components->info(sprintf(
            '%s: %d created, %d fields on %d achievements%s.',
            $dryRun ? 'Dry run' : 'Synced',
            count($plan['create']),
            count($plan['update']),
            collect($plan['update'])->unique('key')->count(),
            $dryRun ? ' would change' : ' changed',
        ));

        return self::SUCCESS;
    }

    private static function show(mixed $value): string
    {
        return match (true) {
            $value === null => '—',
            is_bool($value) => $value ? 'yes' : 'no',
            is_array($value) => implode(', ', $value),
            default => (string) $value,
        };
    }
}
