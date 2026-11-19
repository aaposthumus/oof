<?php

namespace App\Console\Commands;

use App\Achievements\Nicknames\Breakthroughs;
use App\Models\NicknameEvolution;
use App\Models\Season;
use Illuminate\Console\Command;

class OofNicknames extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'oof:nicknames
        {--season=* : Only these seasons (default: all from achievements.live_from)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Propose nickname evolutions after a breakthrough (first podium, first win, strong first season); safe to re-run, one proposal per target nickname';

    /**
     * Execute the console command.
     */
    public function handle(Breakthroughs $breakthroughs): int
    {
        $proposals = Season::query()
            ->where('year', '>=', config('achievements.live_from'))
            ->when($this->option('season'), fn ($query, array $years) => $query->whereIn('year', array_map('intval', $years)))
            ->orderBy('year')
            ->get()
            ->flatMap(fn (Season $season) => $breakthroughs->check($season));

        $this->components->info("{$proposals->count()} evolutions proposed.");

        if ($proposals->isNotEmpty()) {
            $this->table(['Driver', 'From', 'To', 'Season', 'Breakthrough'], $proposals->map(fn (NicknameEvolution $evolution) => [
                "{$evolution->driver->given_name} {$evolution->driver->family_name}",
                $evolution->from->nickname,
                $evolution->to->nickname,
                $evolution->season->year,
                $evolution->reason->label(),
            ])->all());
        }

        return self::SUCCESS;
    }
}
