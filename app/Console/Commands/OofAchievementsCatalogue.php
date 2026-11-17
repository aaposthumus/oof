<?php

namespace App\Console\Commands;

use App\Achievements\Catalogue;
use App\Achievements\Facts\DataSource;
use App\Achievements\Facts\Requirements;
use Illuminate\Console\Command;

class OofAchievementsCatalogue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'oof:achievements:catalogue
        {--each : One row per achievement, instead of grouping those with the same needs}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Print the data each achievement needs and the first year it supports, derived from its trigger';

    /**
     * Execute the console command.
     */
    public function handle(Catalogue $catalogue, Requirements $requirements): int
    {
        $rows = collect($catalogue->entries())->map(fn (array $entry, string $key) => [
            'name' => $entry['name'],
            'needs' => implode(', ', array_map(fn (DataSource $source) => $source->label(), $requirements->for($key)['sources'])),
            'from' => $requirements->for($key)['available_from'],
            'notes' => implode(' ', $requirements->for($key)['notes']),
        ]);

        if (! $this->option('each')) {
            $rows = $rows
                ->groupBy(fn (array $row) => "{$row['needs']}|{$row['from']}|{$row['notes']}")
                ->map(fn ($group) => [...$group->first(), 'name' => $group->pluck('name')->implode(', ')])
                ->sortBy([['from', 'asc'], ['needs', 'asc']]);
        }

        $this->table(['Achievements', 'Needs', 'From', 'Notes'], $rows->map(fn (array $row) => [
            wordwrap($row['name'], 40),
            wordwrap($row['needs'], 30),
            $row['from'],
            wordwrap($row['notes'], 60),
        ])->values()->all());

        return self::SUCCESS;
    }
}
