<?php

namespace App\Achievements\Stamps;

use GdImage;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Makes the background of the achievement art transparent, before framing.
 *
 * The background colour is sampled from the image's edge pixels (the median
 * per channel). A flood fill from every edge pixel takes in each pixel whose
 * channels all lie within the tolerance of that colour, through 4-connected
 * neighbours only. There is no global near-white threshold: cream and white
 * parts inside a character are not connected to the edge, so they survive.
 *
 * Inside the flooded region the background turns into alpha (colour to
 * alpha): a pixel's opacity follows its distance from the background colour,
 * from transparent at transparent_within to opaque at the tolerance, and its
 * colour is unmixed from the background. A soft shadow so becomes a
 * semi-transparent shadow. Along the object's outline (edge_width pixels on
 * either side) a pixel that is a mix of the background and the nearest solid
 * object colour gets that object colour with the mixing share as its alpha,
 * so anti-aliased edges stay smooth without a light halo.
 *
 * Background-coloured regions not connected to the edge (a doughnut hole, a
 * gap inside a cage) are kept and reported. Such a region is removed only
 * when config/stamps.php marks it with a seed point for that image.
 *
 * The sources are never changed; the transparent copy and its report are
 * cached by the source's content and the settings.
 */
class BackgroundRemoval
{
    /**
     * A pixel on the outline counts as a mix of the background and the object
     * colour only when it lies this close to the line between them, as a
     * share of the object colour's distance from the background.
     */
    private const LINE_SHARE = 0.25;

    private const OPEN = "\0";

    private const BACKGROUND = "\1";

    private const ENCLOSED = "\2";

    private const SEEDED = "\3";

    /**
     * The cached transparent copy of an art file: made once, then served from the cache.
     */
    public function cutout(string $source): string
    {
        $base = $this->cachedBase($source);

        if (! is_file("{$base}.png") || ! is_file("{$base}.json")) {
            $image = @imagecreatefrompng($source);

            if ($image === false) {
                throw new RuntimeException("Not a readable PNG: {$source}");
            }

            $result = $this->remove($image, self::seedsFor($source));
            File::ensureDirectoryExists(dirname($base));
            imagesavealpha($result['image'], true);
            imagepng($result['image'], "{$base}.png", 6);
            unset($result['image']);
            File::put("{$base}.json", json_encode($result, JSON_PRETTY_PRINT));
        }

        return "{$base}.png";
    }

    /**
     * What the removal found for an art file: the background colour, the
     * enclosed background regions (kept, or removed by a seed) and the seeds
     * that matched nothing.
     *
     * @return array{width: int, height: int, background: array{0: int, 1: int, 2: int}, enclosed: list<array{pixels: int, share: float, left: int, top: int, right: int, bottom: int, seed: array{0: int, 1: int}, mean_distance: float, removed: bool}>, unused_seeds: list<array{seed: array{0: int, 1: int}, reason: string}>}
     */
    public function report(string $source): array
    {
        $this->cutout($source);

        return json_decode(File::get($this->cachedBase($source).'.json'), true);
    }

    /**
     * The cache key of an art file's transparent copy: its content, the
     * removal settings and the seeds marked for it.
     */
    public static function cacheKey(string $source): string
    {
        $settings = array_diff_key((array) config('stamps.background'), ['remove_enclosed' => true]);

        return sha1(implode('|', [hash_file('sha1', $source), json_encode($settings), json_encode(self::seedsFor($source))]));
    }

    /**
     * The seed points marked in config for an art file: enclosed background
     * regions to remove.
     *
     * @return list<array{0: int, 1: int}>
     */
    public static function seedsFor(string $source): array
    {
        $root = rtrim((string) config('stamps.art_path'), '/').'/';

        if (! str_starts_with($source, $root)) {
            return [];
        }

        return array_values(((array) config('stamps.background.remove_enclosed'))[substr($source, strlen($root))] ?? []);
    }

    /**
     * Make the background of an image transparent.
     *
     * @param  list<array{0: int, 1: int}>  $seeds  Enclosed background regions to remove, by a point inside each
     * @return array{image: GdImage, width: int, height: int, background: array{0: int, 1: int, 2: int}, enclosed: list<array{pixels: int, share: float, left: int, top: int, right: int, bottom: int, seed: array{0: int, 1: int}, mean_distance: float, removed: bool}>, unused_seeds: list<array{seed: array{0: int, 1: int}, reason: string}>}
     */
    public function remove(GdImage $source, array $seeds = []): array
    {
        imagepalettetotruecolor($source);
        $width = imagesx($source);
        $height = imagesy($source);
        $tolerance = (int) config('stamps.background.tolerance');
        $background = $this->backgroundColour($source);
        $distance = $this->distances($source, $background);
        $region = str_repeat(self::OPEN, $width * $height);

        $this->spread($this->edgeIndexes($width, $height), $region, $distance, $width, $tolerance, self::BACKGROUND);

        $enclosed = [];
        $unused = [];

        foreach ($seeds as [$x, $y]) {
            $index = $y * $width + $x;
            $reason = match (true) {
                $x < 0 || $y < 0 || $x >= $width || $y >= $height => 'outside the image',
                $region[$index] === self::BACKGROUND => 'already background, connected to the edge',
                $region[$index] === self::SEEDED => 'already removed by another seed',
                ord($distance[$index]) > $tolerance => 'not the background colour',
                default => null,
            };

            if ($reason !== null) {
                $unused[] = ['seed' => [$x, $y], 'reason' => $reason];

                continue;
            }

            $enclosed[] = $this->spread([$index], $region, $distance, $width, $tolerance, self::SEEDED) + ['removed' => true];
        }

        $minimum = (int) config('stamps.background.enclosed_min_pixels');

        for ($index = 0, $count = strlen($region); $index < $count; $index++) {
            if ($region[$index] === self::OPEN && ord($distance[$index]) <= $tolerance) {
                $found = $this->spread([$index], $region, $distance, $width, $tolerance, self::ENCLOSED);

                if ($found['pixels'] >= $minimum) {
                    $enclosed[] = $found + ['removed' => false];
                }
            }
        }

        usort($enclosed, fn (array $a, array $b) => $b['pixels'] <=> $a['pixels']);

        return [
            'image' => $this->render($source, $background, $distance, $region),
            'width' => $width,
            'height' => $height,
            'background' => $background,
            'enclosed' => $enclosed,
            'unused_seeds' => $unused,
        ];
    }

    /**
     * Where the transparent copy and its report are cached, without extension.
     */
    private function cachedBase(string $source): string
    {
        return rtrim((string) config('stamps.cache_path'), '/').'/cutout/'.self::cacheKey($source);
    }

    /**
     * The background colour: the median of the edge pixels, per channel.
     *
     * @return array{0: int, 1: int, 2: int}
     */
    private function backgroundColour(GdImage $image): array
    {
        $width = imagesx($image);
        $histograms = [array_fill(0, 256, 0), array_fill(0, 256, 0), array_fill(0, 256, 0)];
        $indexes = $this->edgeIndexes($width, imagesy($image));

        foreach ($indexes as $index) {
            $color = imagecolorat($image, $index % $width, intdiv($index, $width));
            $histograms[0][($color >> 16) & 0xFF]++;
            $histograms[1][($color >> 8) & 0xFF]++;
            $histograms[2][$color & 0xFF]++;
        }

        $half = intdiv(count($indexes), 2);

        return array_map(function (array $histogram) use ($half): int {
            $seen = 0;

            foreach ($histogram as $value => $count) {
                $seen += $count;

                if ($seen > $half) {
                    return $value;
                }
            }

            return 255;
        }, $histograms);
    }

    /**
     * Every pixel's distance from the background colour (the largest channel
     * difference), one byte per pixel; fully transparent pixels count as
     * background.
     *
     * @param  array{0: int, 1: int, 2: int}  $background
     */
    private function distances(GdImage $image, array $background): string
    {
        [$red, $green, $blue] = $background;
        $width = imagesx($image);
        $height = imagesy($image);
        $distance = str_repeat("\0", $width * $height);
        $index = 0;

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++, $index++) {
                $color = imagecolorat($image, $x, $y);

                if ((($color >> 24) & 0x7F) === 127) {
                    continue;
                }

                $value = max(abs((($color >> 16) & 0xFF) - $red), abs((($color >> 8) & 0xFF) - $green), abs(($color & 0xFF) - $blue));

                if ($value > 0) {
                    $distance[$index] = chr($value);
                }
            }
        }

        return $distance;
    }

    /**
     * The indexes of every pixel on the image's edge.
     *
     * @return list<int>
     */
    private function edgeIndexes(int $width, int $height): array
    {
        $indexes = [...range(0, $width - 1), ...range(($height - 1) * $width, $height * $width - 1)];

        for ($y = 1; $y < $height - 1; $y++) {
            $indexes[] = $y * $width;
            $indexes[] = $y * $width + $width - 1;
        }

        return $indexes;
    }

    /**
     * Flood fill from the start pixels through 4-connected open pixels within
     * the tolerance, marking them; returns the size and place of what it
     * filled, with as seed the filled pixel nearest the centre of its box.
     *
     * @param  list<int>  $start
     * @return array{pixels: int, share: float, left: int, top: int, right: int, bottom: int, seed: array{0: int, 1: int}, mean_distance: float}
     */
    private function spread(array $start, string &$region, string $distance, int $width, int $tolerance, string $mark): array
    {
        $count = strlen($region);
        $stack = [];

        foreach ($start as $index) {
            if ($region[$index] === self::OPEN && ord($distance[$index]) <= $tolerance) {
                $region[$index] = $mark;
                $stack[] = $index;
            }
        }

        $pixels = 0;
        $sum = 0;
        $left = $top = PHP_INT_MAX;
        $right = $bottom = -1;
        $members = [];
        $keepMembers = $mark !== self::BACKGROUND;

        while ($stack !== []) {
            $index = array_pop($stack);
            $x = $index % $width;
            $y = intdiv($index, $width);
            $value = ord($distance[$index]);
            $pixels++;
            $sum += $value;
            $left = min($left, $x);
            $right = max($right, $x);
            $top = min($top, $y);
            $bottom = max($bottom, $y);

            if ($keepMembers) {
                $members[] = $index;
            }

            foreach ([$x > 0 ? $index - 1 : -1, $x < $width - 1 ? $index + 1 : -1, $index - $width, $index + $width] as $next) {
                if ($next >= 0 && $next < $count && $region[$next] === self::OPEN && ord($distance[$next]) <= $tolerance) {
                    $region[$next] = $mark;
                    $stack[] = $next;
                }
            }
        }

        $centreX = ($left + $right) / 2;
        $centreY = ($top + $bottom) / 2;
        $seed = $start[0] ?? 0;
        $nearest = INF;

        foreach ($members as $index) {
            $away = ($index % $width - $centreX) ** 2 + (intdiv($index, $width) - $centreY) ** 2;

            if ($away < $nearest) {
                $nearest = $away;
                $seed = $index;
            }
        }

        return [
            'pixels' => $pixels,
            'share' => round($pixels / max(1, $count), 5),
            'left' => $left,
            'top' => $top,
            'right' => $right,
            'bottom' => $bottom,
            'seed' => [$seed % $width, intdiv($seed, $width)],
            'mean_distance' => round($sum / max(1, $pixels), 1),
        ];
    }

    /**
     * The transparent copy: colour to alpha in the removed region, and the
     * outline unmixed from the background.
     *
     * @param  array{0: int, 1: int, 2: int}  $background
     */
    private function render(GdImage $source, array $background, string $distance, string $region): GdImage
    {
        [$red, $green, $blue] = $background;
        $width = imagesx($source);
        $height = imagesy($source);
        $clear = (int) config('stamps.background.transparent_within');
        $span = max(1, (int) config('stamps.background.tolerance') - $clear);
        $curve = (float) config('stamps.background.opacity_curve');
        $solid = strtr($region, self::OPEN.self::BACKGROUND.self::ENCLOSED.self::SEEDED, "\1\0\1\0");
        $outline = $this->outline($source, $background, $solid, $width, $height);
        $transparent = (127 << 24) | ($red << 16) | ($green << 8) | $blue;

        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagecopy($image, $source, 0, 0, 0, 0, $width, $height);
        $index = 0;

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++, $index++) {
                $edge = $outline[$index] ?? null;

                if ($edge === null && $solid[$index] === "\1") {
                    continue;
                }

                $color = imagecolorat($source, $x, $y);
                $opacity = (127 - (($color >> 24) & 0x7F)) / 127;

                if ($edge !== null) {
                    [$alpha, $rgb] = $edge;
                } else {
                    $value = ord($distance[$index]);

                    if ($value <= $clear) {
                        imagesetpixel($image, $x, $y, $transparent);

                        continue;
                    }

                    $alpha = min(1.0, (($value - $clear) / $span) ** $curve);
                    $rgb = (self::unmix(($color >> 16) & 0xFF, $red, $alpha) << 16) | (self::unmix(($color >> 8) & 0xFF, $green, $alpha) << 8) | self::unmix($color & 0xFF, $blue, $alpha);
                }

                $gdAlpha = 127 - (int) round($alpha * $opacity * 127);
                imagesetpixel($image, $x, $y, $gdAlpha >= 127 ? $transparent : ($gdAlpha << 24) | $rgb);
            }
        }

        return $image;
    }

    /**
     * A channel with the background taken out, for a pixel of the given opacity.
     */
    private static function unmix(int $channel, int $background, float $alpha): int
    {
        return max(0, min(255, (int) round($background + ($channel - $background) / $alpha)));
    }

    /**
     * The pixels within edge_width of the outline, on either side, that are a
     * mix of the background and the nearest solid object colour: for each,
     * that colour and the mixing share as its alpha.
     *
     * @param  array{0: int, 1: int, 2: int}  $background
     * @return array<int, array{0: float, 1: int}>
     */
    private function outline(GdImage $source, array $background, string $solid, int $width, int $height): array
    {
        $edgeWidth = (int) config('stamps.background.edge_width');

        if ($edgeWidth < 1) {
            return [];
        }

        $count = $width * $height;
        $ring = str_repeat("\0", $count);
        $layer = [];

        foreach ([1, $width] as $step) {
            $differs = $solid ^ substr($solid, $step);

            for ($at = strpos($differs, "\1"); $at !== false; $at = strpos($differs, "\1", $at + 1)) {
                if ($step === 1 && $at % $width === $width - 1) {
                    continue;
                }

                foreach ([$at, $at + $step] as $index) {
                    if ($ring[$index] === "\0") {
                        $ring[$index] = "\1";
                        $layer[] = $index;
                    }
                }
            }
        }

        $edge = $layer;

        for ($depth = 2; $depth <= $edgeWidth; $depth++) {
            $next = [];

            foreach ($layer as $index) {
                $x = $index % $width;

                foreach ([-$width - 1, -$width, -$width + 1, -1, 1, $width - 1, $width, $width + 1] as $offset) {
                    $neighbour = $index + $offset;
                    $neighbourX = $neighbour % $width;

                    if ($neighbour >= 0 && $neighbour < $count && abs($neighbourX - $x) <= 1 && $ring[$neighbour] === "\0" && $solid[$neighbour] === $solid[$index]) {
                        $ring[$neighbour] = chr($depth);
                        $next[] = $neighbour;
                    }
                }
            }

            $edge = [...$edge, ...$next];
            $layer = $next;
        }

        $radius = $edgeWidth + 2;
        $offsets = [];

        for ($dy = -$radius; $dy <= $radius; $dy++) {
            for ($dx = -$radius; $dx <= $radius; $dx++) {
                if (($dx !== 0 || $dy !== 0) && $dx * $dx + $dy * $dy <= $radius * $radius) {
                    $offsets[] = [$dx, $dy, $dx * $dx + $dy * $dy];
                }
            }
        }

        usort($offsets, fn (array $a, array $b) => $a[2] <=> $b[2]);
        [$red, $green, $blue] = $background;
        $tolerance = (int) config('stamps.background.tolerance');
        $mixes = [];

        foreach ($edge as $index) {
            $x = $index % $width;
            $y = intdiv($index, $width);
            $object = null;
            $fallback = null;
            $fallbackDistance = -1;

            foreach ($offsets as [$dx, $dy]) {
                $nearX = $x + $dx;
                $nearY = $y + $dy;

                if ($nearX < 0 || $nearY < 0 || $nearX >= $width || $nearY >= $height) {
                    continue;
                }

                $near = $nearY * $width + $nearX;

                if ($solid[$near] !== "\1") {
                    continue;
                }

                if ($ring[$near] === "\0") {
                    $object = imagecolorat($source, $nearX, $nearY);

                    break;
                }

                $color = imagecolorat($source, $nearX, $nearY);
                $value = max(abs((($color >> 16) & 0xFF) - $red), abs((($color >> 8) & 0xFF) - $green), abs(($color & 0xFF) - $blue));

                if ($value > $fallbackDistance) {
                    $fallback = $color;
                    $fallbackDistance = $value;
                }
            }

            $object ??= $fallback;

            if ($object === null) {
                continue;
            }

            $towards = [(($object >> 16) & 0xFF) - $red, (($object >> 8) & 0xFF) - $green, ($object & 0xFF) - $blue];
            $length = sqrt($towards[0] ** 2 + $towards[1] ** 2 + $towards[2] ** 2);

            if ($length <= $tolerance) {
                continue;
            }

            $color = imagecolorat($source, $x, $y);
            $offset = [(($color >> 16) & 0xFF) - $red, (($color >> 8) & 0xFF) - $green, ($color & 0xFF) - $blue];
            $share = ($offset[0] * $towards[0] + $offset[1] * $towards[1] + $offset[2] * $towards[2]) / ($length * $length);
            $miss = sqrt(($offset[0] - $share * $towards[0]) ** 2 + ($offset[1] - $share * $towards[1]) ** 2 + ($offset[2] - $share * $towards[2]) ** 2);

            if ($miss > self::LINE_SHARE * $length + 2) {
                continue;
            }

            if ($solid[$index] === "\1" && $share >= 1) {
                continue;
            }

            $mixes[$index] = [max(0.0, min(1.0, $share)), $object & 0xFFFFFF];
        }

        return $mixes;
    }
}
