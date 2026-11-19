<?php

namespace App\Console\Commands;

use App\Achievements\ThisWeekInOofHistory;
use App\Models\AchievementAward;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class OofHistory extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'oof:history
        {date? : A day in the week to show (default: today)}
        {--limit=40 : Awards to list; 0 lists them all}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This week in OOF history: final awards and historic moments of a calendar week in any year, sensitive ones flagged; a week without any falls back to the nearest one';

    /**
     * Execute the console command.
     */
    public function handle(ThisWeekInOofHistory $history): int
    {
        $week = $history->weekOf(CarbonImmutable::parse($this->argument('date') ?? 'today'));
        $awards = $week['awards'];
        $flagged = $awards->where('is_sensitive', true)->count();

        if ($week['is_fallback']) {
            $this->components->warn("Nothing ever happened {$week['requested_from']->format('j F')} to {$week['requested_to']->format('j F')}: showing the nearest week instead.");
        }

        $this->components->info("{$week['from']->format('j F')} to {$week['to']->format('j F')}: {$awards->count()} awards in {$awards->pluck('round.season.year')->unique()->count()} seasons ({$flagged} flagged sensitive), {$week['moments']->count()} historic moments.");

        $limit = (int) $this->option('limit');
        $this->table(
            ['Date', 'Grand Prix', 'Achievement', 'Driver', 'Detail', 'Sensitive'],
            ($limit > 0 ? $awards->take($limit) : $awards)->map(fn (AchievementAward $award) => [
                $award->occurred_on?->toDateString(),
                $award->round?->name,
                $award->achievement->name,
                $award->driver?->family_name ?? '—',
                $award->detail,
                $award->is_sensitive ? 'yes' : '',
            ])->all(),
        );

        return self::SUCCESS;
    }
}
