<?php

namespace Tests\Feature\Achievements;

use App\Achievements\Stamps\StampArt;
use GdImage;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Framing the achievement art for the stamp window (Phase 12f): background
 * removed, trimmed to its visible content and scaled so every object covers
 * the same share of the window, never closer than 6 percent to an edge,
 * centred, on a transparent window;
 * the sources are never changed. Fixtures are
 * drawn in a scratch folder, never in resources/images.
 */
class StampArtTest extends TestCase
{
    use LazilyRefreshDatabase;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = storage_path('framework/testing/stamps-'.uniqid());
        config(['stamps.art_path' => "{$this->root}/art", 'stamps.cache_path' => "{$this->root}/cache"]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);

        parent::tearDown();
    }

    /**
     * A white 1122 x 1402 image (the size of the art) with a dark block at
     * the given place, and optionally a stray speck near a corner.
     */
    private function art(string $relative, int $left, int $top, int $width, int $height, bool $speck = false, bool $transparent = false): string
    {
        $image = imagecreatetruecolor(1122, 1402);
        imagesavealpha($image, true);
        imagealphablending($image, false);
        imagefill($image, 0, 0, $transparent ? imagecolorallocatealpha($image, 255, 255, 255, 127) : imagecolorallocate($image, 255, 255, 255));
        imagefilledrectangle($image, $left, $top, $left + $width - 1, $top + $height - 1, imagecolorallocate($image, 200, 60, 40));

        if ($speck) {
            imagesetpixel($image, 5, 5, imagecolorallocate($image, 0, 0, 0));
        }

        $path = "{$this->root}/art/{$relative}";
        File::ensureDirectoryExists(dirname($path));
        imagepng($image, $path);

        return $path;
    }

    /**
     * The empty margins of a framed image, as shares of its width and height.
     *
     * @return array{left: float, right: float, top: float, bottom: float}
     */
    private function margins(GdImage $framed): array
    {
        [$left, $top, $right, $bottom] = app(StampArt::class)->contentBox($framed);
        $width = imagesx($framed);
        $height = imagesy($framed);

        return ['left' => $left / $width, 'right' => ($width - 1 - $right) / $width, 'top' => $top / $height, 'bottom' => ($height - 1 - $bottom) / $height];
    }

    /**
     * The share of the window the framed object's bounding box covers.
     */
    private function areaShare(GdImage $framed): float
    {
        [$left, $top, $right, $bottom] = app(StampArt::class)->contentBox($framed);

        return ($right - $left + 1) * ($bottom - $top + 1) / (imagesx($framed) * imagesy($framed));
    }

    public function test_a_square_object_is_unchanged_15_percent_left_and_right(): void
    {
        $framed = app(StampArt::class)->frame($this->art('glory/square.png', 80, 700, 400, 400));
        $margins = $this->margins($framed);

        $this->assertSame([800, 1000], [imagesx($framed), imagesy($framed)]);
        $this->assertEqualsWithDelta(0.15, $margins['left'], 0.002);
        $this->assertEqualsWithDelta(0.15, $margins['right'], 0.002);
        $this->assertEqualsWithDelta(0.22, $margins['top'], 0.002);
        $this->assertEqualsWithDelta(0.22, $margins['bottom'], 0.002);
        $this->assertEqualsWithDelta(0.392, $this->areaShare($framed), 0.003);
    }

    public function test_a_wide_object_covers_the_same_area_and_gets_more_width_than_a_15_percent_margin(): void
    {
        $framed = app(StampArt::class)->frame($this->art('glory/wide.png', 300, 50, 500, 350));
        $margins = $this->margins($framed);

        $this->assertEqualsWithDelta(0.392, $this->areaShare($framed), 0.003);
        $this->assertLessThan(0.15, $margins['left'], 'Wider than the old 15% margin gave it.');
        $this->assertGreaterThanOrEqual(0.06, $margins['left']);
        $this->assertEqualsWithDelta($margins['left'], $margins['right'], 0.002);
        $this->assertEqualsWithDelta($margins['top'], $margins['bottom'], 0.002);
    }

    public function test_a_very_wide_object_stops_at_the_6_percent_minimum_margin(): void
    {
        $framed = app(StampArt::class)->frame($this->art('glory/very-wide.png', 600, 50, 500, 200));
        $margins = $this->margins($framed);

        $this->assertEqualsWithDelta(0.06, $margins['left'], 0.002);
        $this->assertEqualsWithDelta(0.06, $margins['right'], 0.002);
        $this->assertLessThan(0.392, $this->areaShare($framed), 'The minimum margin wins over the area.');
        $this->assertEqualsWithDelta($margins['top'], $margins['bottom'], 0.002);
    }

    public function test_a_tall_object_covers_the_same_area_and_stays_inside_the_minimum_margin(): void
    {
        $framed = app(StampArt::class)->frame($this->art('glory/tall.png', 80, 700, 300, 600));
        $margins = $this->margins($framed);

        $this->assertEqualsWithDelta(0.392, $this->areaShare($framed), 0.003);
        $this->assertGreaterThanOrEqual(0.06, $margins['top']);
        $this->assertEqualsWithDelta($margins['top'], $margins['bottom'], 0.002);
        $this->assertEqualsWithDelta($margins['left'], $margins['right'], 0.002);
    }

    public function test_a_stray_speck_does_not_count_as_content(): void
    {
        $framed = app(StampArt::class)->frame($this->art('glory/speck.png', 400, 400, 400, 500, speck: true));
        $margins = $this->margins($framed);

        $this->assertEqualsWithDelta(0.392, $this->areaShare($framed), 0.003);
        $this->assertEqualsWithDelta($margins['left'], $margins['right'], 0.002);
        $this->assertEqualsWithDelta($margins['top'], $margins['bottom'], 0.002);
    }

    public function test_a_transparent_background_counts_as_empty_and_the_window_is_transparent(): void
    {
        $framed = app(StampArt::class)->frame($this->art('glory/clear.png', 100, 100, 400, 500, transparent: true));

        $this->assertEqualsWithDelta(0.392, $this->areaShare($framed), 0.003);
        $this->assertSame(127, (imagecolorat($framed, 2, 2) >> 24) & 0x7F);
    }

    public function test_the_framed_copy_is_cached_and_the_source_is_never_changed(): void
    {
        $source = $this->art('oof/cached.png', 100, 100, 400, 500);
        $before = hash_file('sha1', $source);
        $art = app(StampArt::class);

        $first = $art->framed($source);
        $this->assertSame($first, $art->framed($source));
        $this->assertSame($before, hash_file('sha1', $source));
        $this->assertStringStartsWith("{$this->root}/cache/", $first);

        config(['stamps.area_share' => 0.3]);
        $this->assertNotSame($first, $art->framed($source), 'Other settings give another copy.');
    }

    public function test_the_art_path_follows_the_shelf_the_oofie_side_and_the_variant(): void
    {
        $root = "{$this->root}/art";

        $this->assertSame("{$root}/glory/fun-size-win.png", StampArt::expectedPath('fun_size_win', 'glory'));
        $this->assertSame("{$root}/glory/fun-size-win--dog.png", StampArt::expectedPath('fun_size_win', 'glory', label: 'dog'));
        $this->assertSame("{$root}/oof/all-dressed-up-nowhere-to-go.png", StampArt::expectedPath('all_dressed_up_nowhere_to_go', 'oof'));
        $this->assertSame("{$root}/safety-car/took-the-lead.png", StampArt::expectedPath('took_the_lead', 'safety_car'));
        $this->assertSame("{$root}/glory/creature-of-habit.png", StampArt::expectedPath('creature_of_habit', 'oofies', 'glory'));
        $this->assertSame("{$root}/oof/homebody.png", StampArt::expectedPath('homebody', 'oofies', 'oof'));
    }

    public function test_the_report_lists_every_catalogue_achievement_without_art(): void
    {
        $this->art('oof/itchy-feet.png', 100, 100, 400, 500);
        $this->art('glory/creature-of-habit.png', 100, 100, 400, 500);
        $this->art('glory/stray-file-not-in-the-catalogue.png', 100, 100, 400, 500);

        $missing = app(StampArt::class)->missing();

        $this->assertArrayNotHasKey('itchy_feet', $missing);
        $this->assertArrayNotHasKey('creature_of_habit', $missing);
        $this->assertSame("{$this->root}/art/glory/early-bird.png", $missing['early_bird']);
        $this->assertSame("{$this->root}/art/safety-car/took-the-lead.png", $missing['took_the_lead']);
        $this->assertCount(count(config('achievement_catalogue.achievements')) - 2, $missing);
    }

    public function test_the_preview_command_frames_every_art_file_and_reports_what_is_missing(): void
    {
        $this->art('oof/itchy-feet.png', 100, 100, 400, 500);
        $this->art('glory/fun-size-win--dog.png', 100, 100, 400, 500);
        $out = "{$this->root}/preview";

        $this->artisan('oof:stamps:preview', ['--out' => $out])
            ->expectsOutputToContain('2 art files framed')
            ->expectsOutputToContain('catalogue achievements have no art')
            ->assertSuccessful();

        $this->assertFileExists("{$out}/oof/itchy-feet.png");
        $this->assertFileExists("{$out}/glory/fun-size-win--dog.png");
        $this->assertSame([800, 1000], array_slice(getimagesize("{$out}/oof/itchy-feet.png"), 0, 2));
    }
}
