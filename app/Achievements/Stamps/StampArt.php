<?php

namespace App\Achievements\Stamps;

use App\Achievements\Catalogue;
use App\Enums\AchievementShelf;
use App\Models\Achievement;
use GdImage;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * The achievement art and its framing for the stamp window (Phase 12f).
 *
 * Art: resources/images/origami/achievements/<folder>/<key in kebab case>.png,
 * the folder being the shelf (glory, oof, safety-car) or, for an Oofie, its
 * side; a variant is <name>--<label>.png.
 *
 * Framing, at render time only (the sources are never changed): the image
 * is trimmed to its visible content (every pixel that is not near-white or
 * transparent; a row or column needs a few such pixels, so stray specks do
 * not count) and scaled to sit centred in the window with an even empty
 * margin on all sides, on white (config/stamps.php). The framed copy is
 * cached by the source's content and the settings.
 */
class StampArt
{
    public function __construct(private Catalogue $catalogue) {}

    /**
     * The art folder for a shelf, or for an Oofie its side.
     */
    public static function folder(string $shelf, ?string $side = null): string
    {
        return match (true) {
            $shelf === AchievementShelf::Oofies->value => $side ?? 'oof',
            $shelf === AchievementShelf::SafetyCar->value => 'safety-car',
            default => $shelf,
        };
    }

    /**
     * Where an achievement's art is expected, whether or not it exists.
     */
    public static function expectedPath(string $key, string $shelf, ?string $side = null, ?string $label = null): string
    {
        $name = str_replace('_', '-', $key).($label === null || $label === '' ? '' : "--{$label}");

        return rtrim((string) config('stamps.art_path'), '/').'/'.self::folder($shelf, $side)."/{$name}.png";
    }

    /**
     * The achievement's art file, or null when there is none.
     */
    public function pathFor(Achievement $achievement, ?string $label = null): ?string
    {
        $path = self::expectedPath($achievement->key, $achievement->shelf->value, $achievement->side?->value, $label);

        return is_file($path) ? $path : null;
    }

    /**
     * The CMS address of the achievement's framed art, or null without art.
     */
    public function url(Achievement $achievement): ?string
    {
        return $this->pathFor($achievement) === null ? null : route('stamps.show', $achievement);
    }

    /**
     * Catalogue achievements without art, with the path where it is expected.
     *
     * @return array<string, string> Keyed by achievement key, in catalogue order
     */
    public function missing(): array
    {
        return collect($this->catalogue->entries())
            ->map(fn (array $entry, string $key) => self::expectedPath($key, $entry['shelf'], $entry['side'] ?? null))
            ->reject(fn (string $path) => is_file($path))
            ->all();
    }

    /**
     * The framed copy of an art file: made once, then served from the cache.
     */
    public function framed(string $source): string
    {
        $settings = config('stamps');
        $target = rtrim($settings['cache_path'], '/').'/'.sha1(implode('|', [hash_file('sha1', $source), json_encode(array_diff_key($settings, array_flip(['art_path', 'cache_path'])))])).'.png';

        if (! is_file($target)) {
            File::ensureDirectoryExists(dirname($target));
            $framed = $this->frame($source);
            imagepng($framed, $target, 6);
        }

        return $target;
    }

    /**
     * Frame an art file for the stamp window.
     */
    public function frame(string $source): GdImage
    {
        $image = imagecreatefrompng($source);

        if ($image === false) {
            throw new RuntimeException("Not a readable PNG: {$source}");
        }

        imagepalettetotruecolor($image);
        $window = config('stamps.window');
        $margin = (float) config('stamps.margin');
        $canvas = imagecreatetruecolor($window['width'], $window['height']);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        $box = $this->contentBox($image);

        if ($box === null) {
            return $canvas;
        }

        [$left, $top, $right, $bottom] = $box;
        $width = $right - $left + 1;
        $height = $bottom - $top + 1;
        $scale = min($window['width'] * (1 - 2 * $margin) / $width, $window['height'] * (1 - 2 * $margin) / $height);
        $targetWidth = (int) round($width * $scale);
        $targetHeight = (int) round($height * $scale);

        imagealphablending($canvas, true);
        imagecopyresampled($canvas, $image, intdiv($window['width'] - $targetWidth, 2), intdiv($window['height'] - $targetHeight, 2), $left, $top, $targetWidth, $targetHeight, $width, $height);

        return $canvas;
    }

    /**
     * The bounding box of the visible content: [left, top, right, bottom], or
     * null for an empty image.
     *
     * @return array{0: int, 1: int, 2: int, 3: int}|null
     */
    public function contentBox(GdImage $image): ?array
    {
        $threshold = (int) config('stamps.white_threshold');
        $minimum = (int) config('stamps.min_content_pixels');
        $width = imagesx($image);
        $height = imagesy($image);
        $rows = array_fill(0, $height, 0);
        $columns = array_fill(0, $width, 0);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $color = imagecolorat($image, $x, $y);

                if ((($color >> 24) & 0x7F) >= 100) {
                    continue;
                }

                if ((($color >> 16) & 0xFF) < $threshold || (($color >> 8) & 0xFF) < $threshold || ($color & 0xFF) < $threshold) {
                    $rows[$y]++;
                    $columns[$x]++;
                }
            }
        }

        $filledRows = array_keys(array_filter($rows, fn (int $count) => $count >= $minimum));
        $filledColumns = array_keys(array_filter($columns, fn (int $count) => $count >= $minimum));

        if ($filledRows === [] || $filledColumns === []) {
            return null;
        }

        return [min($filledColumns), min($filledRows), max($filledColumns), max($filledRows)];
    }
}
