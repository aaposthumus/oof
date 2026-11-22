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
 * Framing, at render time only (the sources are never changed): the
 * background is made transparent (BackgroundRemoval), then the image is
 * trimmed to its visible content (every pixel from trim_min_opacity; a row
 * or column needs a few such pixels, so stray specks do not count) and
 * scaled so its bounding box covers the same share of the window for every
 * image (area_share), never closer than min_margin to an edge, centred, on
 * a transparent window (config/stamps.php). The transparent and framed
 * copies are cached by the source's content and the settings.
 */
class StampArt
{
    public function __construct(private Catalogue $catalogue, private BackgroundRemoval $removal) {}

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
        $target = rtrim($settings['cache_path'], '/').'/'.sha1(implode('|', [BackgroundRemoval::cacheKey($source), json_encode(array_diff_key($settings, array_flip(['art_path', 'cache_path', 'background'])))])).'.png';

        if (! is_file($target)) {
            File::ensureDirectoryExists(dirname($target));
            $framed = $this->frame($source);
            imagesavealpha($framed, true);
            imagepng($framed, $target, 6);
        }

        return $target;
    }

    /**
     * Frame an art file for the stamp window, from its transparent copy.
     */
    public function frame(string $source): GdImage
    {
        $image = imagecreatefrompng($this->removal->cutout($source));

        if ($image === false) {
            throw new RuntimeException("Not a readable PNG: {$source}");
        }

        $window = config('stamps.window');
        $canvas = imagecreatetruecolor($window['width'], $window['height']);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 255, 255, 255, 127));
        $box = $this->contentBox($image);

        if ($box === null) {
            return $canvas;
        }

        [$left, $top, $right, $bottom] = $box;
        $width = $right - $left + 1;
        $height = $bottom - $top + 1;
        $scale = self::scale($width, $height, $window['width'], $window['height']);
        $targetWidth = (int) round($width * $scale);
        $targetHeight = (int) round($height * $scale);

        $targetLeft = intdiv($window['width'] - $targetWidth, 2);
        $targetTop = intdiv($window['height'] - $targetHeight, 2);
        imagecopyresampled($canvas, $image, $targetLeft, $targetTop, $left, $top, $targetWidth, $targetHeight, $width, $height);

        if (config('stamps.shadow.enabled')) {
            [$footLeft, $footRight] = $this->footprint($image, $box);
            $this->addShadow($canvas, $targetLeft + ($footLeft - $left + 0.5) * $scale, $targetLeft + ($footRight - $left + 0.5) * $scale, $targetTop + $targetHeight);
        }

        return $canvas;
    }

    /**
     * The left and right of the object's visible pixels in the bottom
     * shadow.footprint share of its box, in the image's coordinates.
     *
     * @param  array{0: int, 1: int, 2: int, 3: int}  $box
     * @return array{0: int, 1: int}
     */
    private function footprint(GdImage $image, array $box): array
    {
        [$left, $top, $right, $bottom] = $box;
        $maximumAlpha = (int) floor((1 - (float) config('stamps.trim_min_opacity')) * 127);
        $from = max($top, $bottom - (int) round(($bottom - $top + 1) * (float) config('stamps.shadow.footprint')) + 1);
        $footLeft = $right;
        $footRight = $left;

        for ($y = $from; $y <= $bottom; $y++) {
            for ($x = $left; $x <= $right; $x++) {
                if (((imagecolorat($image, $x, $y) >> 24) & 0x7F) <= $maximumAlpha) {
                    $footLeft = min($footLeft, $x);
                    $footRight = max($footRight, $x);
                }
            }
        }

        return $footLeft > $footRight ? [$left, $right] : [$footLeft, $footRight];
    }

    /**
     * Put the synthetic shadow under the object: a soft ellipse, the same for
     * every stamp (config stamps.shadow), centred under the footprint on the
     * object's bottom edge and composited behind the object.
     */
    private function addShadow(GdImage $canvas, float $footLeft, float $footRight, float $bottom): void
    {
        $settings = config('stamps.shadow');
        $radiusX = ($footRight - $footLeft) * (float) $settings['width'] / 2;
        $radiusY = $radiusX * (float) $settings['height'];
        $centreX = ($footLeft + $footRight) / 2;
        $centreY = $bottom + $radiusY * (float) $settings['drop'];
        $solid = 1 - max(0.001, min(1.0, (float) $settings['blur']));
        [$red, $green, $blue] = $settings['colour'];
        $canvasWidth = imagesx($canvas);
        $canvasHeight = imagesy($canvas);

        for ($y = max(0, (int) floor($centreY - $radiusY)); $y <= min($canvasHeight - 1, (int) ceil($centreY + $radiusY)); $y++) {
            for ($x = max(0, (int) floor($centreX - $radiusX)); $x <= min($canvasWidth - 1, (int) ceil($centreX + $radiusX)); $x++) {
                $reach = sqrt((($x + 0.5 - $centreX) / $radiusX) ** 2 + (($y + 0.5 - $centreY) / $radiusY) ** 2);

                if ($reach >= 1) {
                    continue;
                }

                $fade = $reach <= $solid ? 1.0 : 1 - (($reach - $solid) / (1 - $solid));
                $shadow = (float) $settings['opacity'] * $fade * $fade * (3 - 2 * $fade);
                $color = imagecolorat($canvas, $x, $y);
                $objectOpacity = (127 - (($color >> 24) & 0x7F)) / 127;
                $opacity = $objectOpacity + $shadow * (1 - $objectOpacity);

                if ($opacity <= 0) {
                    continue;
                }

                $mix = fn (int $object, int $under) => (int) round(($object * $objectOpacity + $under * $shadow * (1 - $objectOpacity)) / $opacity);
                imagesetpixel($canvas, $x, $y, ((127 - (int) round($opacity * 127)) << 24) | ($mix(($color >> 16) & 0xFF, $red) << 16) | ($mix(($color >> 8) & 0xFF, $green) << 8) | $mix($color & 0xFF, $blue));
            }
        }
    }

    /**
     * How much to scale a trimmed object: so its bounding box covers
     * area_share of the window, unless that would bring it closer than
     * min_margin to an edge, in which case the margin wins.
     */
    public static function scale(int $width, int $height, int $windowWidth, int $windowHeight): float
    {
        $minimum = (float) config('stamps.min_margin');
        $byArea = sqrt((float) config('stamps.area_share') * $windowWidth * $windowHeight / ($width * $height));
        $byMargin = min($windowWidth * (1 - 2 * $minimum) / $width, $windowHeight * (1 - 2 * $minimum) / $height);

        return min($byArea, $byMargin);
    }

    /**
     * The bounding box of the visible content (from trim_min_opacity):
     * [left, top, right, bottom], or null for an empty image.
     *
     * @return array{0: int, 1: int, 2: int, 3: int}|null
     */
    public function contentBox(GdImage $image): ?array
    {
        $maximumAlpha = (int) floor((1 - (float) config('stamps.trim_min_opacity')) * 127);
        $minimum = (int) config('stamps.min_content_pixels');
        $width = imagesx($image);
        $height = imagesy($image);
        $rows = array_fill(0, $height, 0);
        $columns = array_fill(0, $width, 0);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $color = imagecolorat($image, $x, $y);

                if ((($color >> 24) & 0x7F) <= $maximumAlpha) {
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
