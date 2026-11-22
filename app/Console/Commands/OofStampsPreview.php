<?php

namespace App\Console\Commands;

use App\Achievements\Stamps\BackgroundRemoval;
use App\Achievements\Stamps\StampArt;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\SplFileInfo;

class OofStampsPreview extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'oof:stamps:preview
        {--only=* : Only art files whose name contains this (e.g. early-bird)}
        {--out= : Where to write the framed copies (default storage/app/private/stamps/preview)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Make the background of every achievement art file transparent, frame it for the stamp window and write the framed copies, for a visual check; reports the enclosed background regions; the sources are never changed';

    /**
     * Execute the console command.
     */
    public function handle(StampArt $art, BackgroundRemoval $removal): int
    {
        $root = rtrim((string) config('stamps.art_path'), '/');
        $out = rtrim($this->option('out') ?: storage_path('app/private/stamps/preview'), '/');
        $only = $this->option('only');

        $files = collect(File::allFiles($root))
            ->filter(fn (SplFileInfo $file) => strtolower($file->getExtension()) === 'png')
            ->filter(fn (SplFileInfo $file) => $only === [] || collect($only)->contains(fn (string $part) => str_contains($file->getFilename(), $part)))
            ->sortBy(fn (SplFileInfo $file) => $file->getRelativePathname())
            ->values();

        foreach ($files as $file) {
            $target = "{$out}/{$file->getRelativePathname()}";
            File::ensureDirectoryExists(dirname($target));
            File::copy($art->framed($file->getPathname()), $target);
        }

        $this->components->info("{$files->count()} art files framed into {$out}.");
        $this->reportEnclosed($files, $removal);

        $missing = $art->missing();

        if ($missing === []) {
            $this->components->info('Every catalogue achievement has art.');

            return self::SUCCESS;
        }

        $this->components->warn(count($missing).' catalogue achievements have no art:');
        $this->table(['Achievement', 'Expected at'], collect($missing)->map(fn (string $path, string $key) => [$key, str_replace($root.'/', '', $path)])->values()->all());

        return self::SUCCESS;
    }

    /**
     * The enclosed background regions per art file, removed or kept and why;
     * and the keep-list seeds that matched no region.
     *
     * @param  Collection<int, SplFileInfo>  $files
     */
    private function reportEnclosed(Collection $files, BackgroundRemoval $removal): void
    {
        $rows = [];
        $unused = [];

        foreach ($files as $file) {
            $report = $removal->report($file->getPathname());
            $name = $file->getRelativePathname();

            foreach ($report['enclosed'] as $region) {
                $rows[] = [
                    $name,
                    number_format($region['pixels']),
                    number_format($region['share'] * 100, 2).'%',
                    "({$region['left']}, {$region['top']}) to ({$region['right']}, {$region['bottom']})",
                    "[{$region['seed'][0]}, {$region['seed'][1]}]",
                    $region['mean_distance'],
                    ($region['removed'] ? 'removed' : 'kept').": {$region['why']}",
                ];
            }

            foreach ($report['unused_seeds'] as $seed) {
                $unused[] = [$name, "[{$seed['seed'][0]}, {$seed['seed'][1]}]", $seed['reason']];
            }
        }

        if ($rows === []) {
            $this->components->info('No enclosed background regions.');
        } else {
            $this->components->warn(count($rows).' enclosed background regions (removed up to background.remove_enclosed_up_to, unless on background.keep_enclosed in config/stamps.php):');
            $this->table(['Art', 'Pixels', 'Share', 'Box', 'Seed', 'Mean distance', 'State'], $rows);
        }

        if ($unused !== []) {
            $this->components->warn(count($unused).' keep-list seeds in config/stamps.php matched no enclosed region:');
            $this->table(['Art', 'Seed', 'Why'], $unused);
        }
    }
}
