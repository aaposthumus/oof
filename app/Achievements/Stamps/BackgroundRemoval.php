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
 * gap inside a cage) are found too. One from enclosed_min_pixels is removed
 * when its mean distance from the background colour is at most
 * remove_enclosed_up_to, unless the keep-list in config/stamps.php names it
 * by a point inside it (a white clock face); every other one is kept. All of
 * them are reported (decided by the owner on 22 November 2026).
 *
 * When an object mask from an AI segmentation model exists for the source
 * (made beforehand by bin/stamp-masks.py, outside the Laravel runtime), every
 * pixel outside it becomes transparent as well: the final opacity is the one
 * above times the mask's, ramped from mask.transparent_below to
 * mask.opaque_from. That takes away floor shadows and light rims; the flood
 * fill still shapes the object's own edges. Per art file, mask.files can
 * switch the mask off ('mask' => false) or list boxes in source pixels
 * ('mask_keep') inside which the mask removes nothing, where the model cut
 * off part of the art.
 *
 * The sources are never changed; the transparent copy and its report are
 * cached by the source's content, its mask and the settings.
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

    private const REMOVED = "\3";

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

            $maskPath = self::maskPath($source);
            $mask = $maskPath === null ? null : @imagecreatefrompng($maskPath);
            $keepBoxes = self::maskKeepFor($source);

            if ($mask) {
                imagepalettetotruecolor($mask);

                foreach ($keepBoxes as [$left, $top, $right, $bottom]) {
                    imagefilledrectangle($mask, $left, $top, $right, $bottom, 0xFFFFFF);
                }
            }

            $result = $this->remove($image, self::keepSeedsFor($source), $mask ?: null);
            $result['mask'] = $maskPath === null ? null : config('stamps.background.mask.model');
            $result['mask_keep'] = $maskPath === null ? [] : $keepBoxes;
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
     * enclosed background regions (removed or kept, and why) and the
     * keep-list seeds that matched no region.
     *
     * @return array{width: int, height: int, background: array{0: int, 1: int, 2: int}, enclosed: list<array{pixels: int, share: float, left: int, top: int, right: int, bottom: int, seed: array{0: int, 1: int}, mean_distance: float, removed: bool, why: string}>, unused_seeds: list<array{seed: array{0: int, 1: int}, reason: string}>, mask: string|null}
     */
    public function report(string $source): array
    {
        $this->cutout($source);

        return json_decode(File::get($this->cachedBase($source).'.json'), true);
    }

    /**
     * The cache key of an art file's transparent copy: its content, the
     * removal settings and its keep-list seeds.
     */
    public static function cacheKey(string $source): string
    {
        $settings = array_diff_key((array) config('stamps.background'), ['keep_enclosed' => true]);
        $settings['mask'] = array_diff_key((array) ($settings['mask'] ?? []), ['files' => true]);
        $mask = self::maskPath($source);

        return sha1(implode('|', [hash_file('sha1', $source), json_encode($settings), json_encode(self::keepSeedsFor($source)), $mask === null ? 'no mask' : hash_file('sha1', $mask), json_encode(self::maskKeepFor($source))]));
    }

    /**
     * An art file's path relative to art_path, or null outside it.
     */
    private static function relativePath(string $source): ?string
    {
        $root = rtrim((string) config('stamps.art_path'), '/').'/';

        return str_starts_with($source, $root) ? substr($source, strlen($root)) : null;
    }

    /**
     * The per-file mask settings of an art file (mask.files): 'mask' => false
     * and/or 'mask_keep' boxes.
     *
     * @return array{mask?: bool, mask_keep?: list<array{0: int, 1: int, 2: int, 3: int}>}
     */
    private static function maskSettingsFor(string $source): array
    {
        $relative = self::relativePath($source);

        return $relative === null ? [] : (array) (((array) config('stamps.background.mask.files'))[$relative] ?? []);
    }

    /**
     * The boxes ([left, top, right, bottom] in source pixels, inclusive) inside
     * which the mask removes nothing for an art file.
     *
     * @return list<array{0: int, 1: int, 2: int, 3: int}>
     */
    public static function maskKeepFor(string $source): array
    {
        return array_values(self::maskSettingsFor($source)['mask_keep'] ?? []);
    }

    /**
     * The object mask made for an art file by the segmentation model, or null
     * when there is none or mask.files switches it off for the file:
     * <mask.path>/<model>/<sha1 of the source>.png.
     */
    public static function maskPath(string $source): ?string
    {
        $model = config('stamps.background.mask.model');

        if (! is_string($model) || $model === '' || (self::maskSettingsFor($source)['mask'] ?? true) === false) {
            return null;
        }

        $path = rtrim((string) config('stamps.background.mask.path'), '/')."/{$model}/".hash_file('sha1', $source).'.png';

        return is_file($path) ? $path : null;
    }

    /**
     * The keep-list of an art file: enclosed regions never to remove, by a
     * point inside each.
     *
     * @return list<array{0: int, 1: int}>
     */
    public static function keepSeedsFor(string $source): array
    {
        $relative = self::relativePath($source);

        return $relative === null ? [] : array_values(((array) config('stamps.background.keep_enclosed'))[$relative] ?? []);
    }

    /**
     * Make the background of an image transparent.
     *
     * @param  list<array{0: int, 1: int}>  $keep  Enclosed regions never to remove, by a point inside each
     * @param  GdImage|null  $mask  The object mask from the segmentation model (white = object), same size
     * @return array{image: GdImage, width: int, height: int, background: array{0: int, 1: int, 2: int}, enclosed: list<array{pixels: int, share: float, left: int, top: int, right: int, bottom: int, seed: array{0: int, 1: int}, mean_distance: float, removed: bool, why: string}>, unused_seeds: list<array{seed: array{0: int, 1: int}, reason: string}>}
     */
    public function remove(GdImage $source, array $keep = [], ?GdImage $mask = null): array
    {
        imagepalettetotruecolor($source);
        $width = imagesx($source);
        $height = imagesy($source);
        $tolerance = (int) config('stamps.background.tolerance');
        $background = $this->backgroundColour($source);
        $distance = $this->distances($source, $background);
        $region = str_repeat(self::OPEN, $width * $height);

        $this->spread($this->edgeIndexes($width, $height), $region, $distance, $width, $tolerance, self::BACKGROUND);

        $minimum = (int) config('stamps.background.enclosed_min_pixels');
        $removeUpTo = (float) config('stamps.background.remove_enclosed_up_to');
        $keepIndexes = [];

        foreach ($keep as [$x, $y]) {
            if ($x >= 0 && $y >= 0 && $x < $width && $y < $height) {
                $keepIndexes[$y * $width + $x] = [$x, $y];
            }
        }

        $matched = [];
        $enclosed = [];

        for ($index = 0, $count = strlen($region); $index < $count; $index++) {
            if ($region[$index] !== self::OPEN || ord($distance[$index]) > $tolerance) {
                continue;
            }

            $found = $this->spread([$index], $region, $distance, $width, $tolerance, self::ENCLOSED);
            $members = $found['members'];
            unset($found['members']);

            if ($found['pixels'] < $minimum) {
                continue;
            }

            $kept = array_intersect_key($keepIndexes, array_flip($members));
            $matched += $kept;
            $removed = $kept === [] && $found['mean_distance'] <= $removeUpTo;

            if ($removed) {
                foreach ($members as $member) {
                    $region[$member] = self::REMOVED;
                }
            }

            $enclosed[] = $found + ['removed' => $removed, 'why' => match (true) {
                $removed => "mean distance {$found['mean_distance']} up to {$removeUpTo}",
                $kept !== [] => 'keep-list',
                default => "mean distance {$found['mean_distance']} above {$removeUpTo}",
            }];
        }

        $unused = [];

        foreach ($keep as [$x, $y]) {
            $index = $y * $width + $x;

            if (isset($matched[$index])) {
                continue;
            }

            $unused[] = ['seed' => [$x, $y], 'reason' => match (true) {
                ! isset($keepIndexes[$index]) => 'outside the image',
                $region[$index] === self::BACKGROUND => 'background connected to the edge',
                ord($distance[$index]) > $tolerance => 'not the background colour',
                default => 'in a region below enclosed_min_pixels',
            }];
        }

        usort($enclosed, fn (array $a, array $b) => $b['pixels'] <=> $a['pixels']);

        return [
            'image' => $this->render($source, $background, $distance, $region, $mask),
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
     * filled, with as seed the filled pixel nearest the centre of its box,
     * and (except for the edge region) the filled pixels.
     *
     * @param  list<int>  $start
     * @return array{pixels: int, share: float, left: int, top: int, right: int, bottom: int, seed: array{0: int, 1: int}, mean_distance: float, members: list<int>}
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
            'members' => $members,
        ];
    }

    /**
     * The transparent copy: colour to alpha in the removed region, and the
     * outline unmixed from the background.
     *
     * @param  array{0: int, 1: int, 2: int}  $background
     */
    private function render(GdImage $source, array $background, string $distance, string $region, ?GdImage $mask): GdImage
    {
        [$red, $green, $blue] = $background;
        $width = imagesx($source);
        $height = imagesy($source);
        $clear = (int) config('stamps.background.transparent_within');
        $span = max(1, (int) config('stamps.background.tolerance') - $clear);
        $curve = (float) config('stamps.background.opacity_curve');
        $solid = strtr($region, self::OPEN.self::BACKGROUND.self::ENCLOSED.self::REMOVED, "\1\0\1\0");
        $outline = $this->outline($source, $background, $solid, $width, $height);
        $transparent = (127 << 24) | ($red << 16) | ($green << 8) | $blue;
        [$maskBytes, $gates] = $mask === null ? [null, []] : $this->maskOpacities($mask, $width, $height);

        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagecopy($image, $source, 0, 0, 0, 0, $width, $height);
        $index = 0;

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++, $index++) {
                $edge = $outline[$index] ?? null;
                $gate = $maskBytes === null ? 1.0 : $gates[ord($maskBytes[$index])];

                if ($gate <= 0.0) {
                    imagesetpixel($image, $x, $y, $transparent);

                    continue;
                }

                if ($edge === null && $solid[$index] === "\1" && $gate >= 1.0) {
                    continue;
                }

                $color = imagecolorat($source, $x, $y);
                $opacity = (127 - (($color >> 24) & 0x7F)) / 127 * $gate;

                if ($edge !== null) {
                    [$alpha, $rgb] = $edge;
                } elseif ($solid[$index] === "\1") {
                    $alpha = 1.0;
                    $rgb = $color & 0xFFFFFF;
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
     * The mask as one byte per pixel, and the opacity for each byte value:
     * 0 up to mask.transparent_below, 1 from mask.opaque_from, linear between.
     *
     * @return array{0: string, 1: list<float>}
     */
    private function maskOpacities(GdImage $mask, int $width, int $height): array
    {
        if (imagesx($mask) !== $width || imagesy($mask) !== $height) {
            $mask = imagescale($mask, $width, $height, IMG_BILINEAR_FIXED);
        }

        imagepalettetotruecolor($mask);
        $low = (int) config('stamps.background.mask.transparent_below');
        $high = max($low + 1, (int) config('stamps.background.mask.opaque_from'));
        $bytes = str_repeat("\0", $width * $height);
        $index = 0;

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++, $index++) {
                $value = (imagecolorat($mask, $x, $y) >> 8) & 0xFF;

                if ($value > 0) {
                    $bytes[$index] = chr($value);
                }
            }
        }

        $opacities = array_map(fn (int $value) => max(0.0, min(1.0, ($value - $low) / ($high - $low))), range(0, 255));

        return [$bytes, $opacities];
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
