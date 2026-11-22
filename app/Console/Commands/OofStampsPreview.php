<?php

namespace App\Console\Commands;

use App\Achievements\Stamps\StampArt;
use Illuminate\Console\Command;
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
    protected $description = 'Frame every achievement art file for the stamp window and write the framed copies, for a visual check; the sources are never changed';

    /**
     * Execute the console command.
     */
    public function handle(StampArt $art): int
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

        $missing = $art->missing();

        if ($missing === []) {
            $this->components->info('Every catalogue achievement has art.');

            return self::SUCCESS;
        }

        $this->components->warn(count($missing).' catalogue achievements have no art:');
        $this->table(['Achievement', 'Expected at'], collect($missing)->map(fn (string $path, string $key) => [$key, str_replace($root.'/', '', $path)])->values()->all());

        return self::SUCCESS;
    }
}
