<?php

namespace App\Console\Commands;

use App\Achievements\OofScoreStandings;
use App\Enums\AchievementShelf;
use App\Models\Season;
use Illuminate\Console\Command;

class OofScore extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'oof:oofscore
        {--season=* : Only these seasons (default: all from achievements.shelf_from.oof)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rebuild the OOFscore standings from the final awards (closed seasons stay frozen)';

    /**
     * Execute the console command.
     */
    public function handle(OofScoreStandings $standings): int
    {
        $seasons = Season::query()
            ->where('year', '>=', AchievementShelf::Oof->firstSeason())
            ->when($this->option('season'), fn ($query, array $years) => $query->whereIn('year', array_map('intval', $years)))
            ->orderBy('year')
            ->get();

        $rows = $seasons->map(function (Season $season) use ($standings) {
            $result = $standings->rebuild($season);

            return [$season->year, $result === null ? 'closed, frozen' : 'rebuilt', $result['rounds'] ?? '–', $result['rows'] ?? '–', $result['changed_rounds'] ?? '–'];
        });

        $this->table(['Season', 'Result', 'Rounds', 'Rows', 'Rounds changed'], $rows->all());

        return self::SUCCESS;
    }
}
