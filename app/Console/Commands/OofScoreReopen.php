<?php

namespace App\Console\Commands;

use App\Achievements\OofScoreStandings;
use App\Achievements\SeasonNotClosable;
use App\Models\Season;
use Illuminate\Console\Command;

/**
 * For the owner only: there is no CMS action for it, and it asks for the
 * year to be typed again unless --force is given.
 */
class OofScoreReopen extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'oof:oofscore:reopen
        {season : The year to reopen}
        {--force : Reopen without asking}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Owner only: reopen a closed season, so its award points follow the catalogue again and its OOFscore is rebuilt';

    /**
     * Execute the console command.
     */
    public function handle(OofScoreStandings $standings): int
    {
        $season = Season::query()->where('year', (int) $this->argument('season'))->first();

        if ($season === null) {
            $this->components->error("There is no season {$this->argument('season')}.");

            return self::FAILURE;
        }

        if (! $this->option('force') && $this->ask("Reopening {$season->year} gives its awards the catalogue's current points and rebuilds its OOFscore. Type the year to go on") !== (string) $season->year) {
            $this->components->warn('Not reopened.');

            return self::FAILURE;
        }

        try {
            $standings->reopen($season);
        } catch (SeasonNotClosable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("{$season->year} is open again.");

        return self::SUCCESS;
    }
}
