<?php

namespace App\Console\Commands;

use App\Achievements\OofScoreStandings;
use App\Achievements\SeasonNotClosable;
use App\Models\Season;
use Illuminate\Console\Command;

class OofScoreClose extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'oof:oofscore:close
        {season : The year to close}
        {--force : Close without asking}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Close a finished season: rebuild its OOFscore a last time and freeze its standings and points';

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

        if (! $this->option('force') && ! $this->confirm("Close {$season->year}? Its OOFscore and award points are frozen for good.")) {
            return self::FAILURE;
        }

        try {
            $standings->close($season);
        } catch (SeasonNotClosable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("{$season->year} is closed.");

        return self::SUCCESS;
    }
}
