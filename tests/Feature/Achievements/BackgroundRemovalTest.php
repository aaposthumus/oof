<?php

namespace Tests\Feature\Achievements;

use App\Achievements\Stamps\BackgroundRemoval;
use App\Achievements\Stamps\StampArt;
use Closure;
use GdImage;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Background removal for the stamp art: a flood fill from the image edges
 * with colour to alpha inside it, never a global near-white threshold;
 * enclosed background regions are kept and reported unless a seed in config
 * removes them; the sources are never changed. Fixtures are drawn in a
 * scratch folder, never in resources/images.
 */
class BackgroundRemovalTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * The near-white background of the fixtures, like the art's.
     */
    private const BACKGROUND = [252, 251, 249];

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
     * A 400 x 400 art file on the near-white background, drawn by the callback.
     *
     * @param  Closure(GdImage): void  $draw
     */
    private function art(string $relative, Closure $draw): string
    {
        $image = imagecreatetruecolor(400, 400);
        imagefill($image, 0, 0, imagecolorallocate($image, ...self::BACKGROUND));
        $draw($image);

        $path = "{$this->root}/art/{$relative}";
        File::ensureDirectoryExists(dirname($path));
        imagepng($image, $path);

        return $path;
    }

    /**
     * The transparent copy of an art file.
     */
    private function cutout(string $source): GdImage
    {
        return imagecreatefrompng(app(BackgroundRemoval::class)->cutout($source));
    }

    /**
     * A pixel as [red, green, blue, opacity from 0 to 1].
     *
     * @return array{0: int, 1: int, 2: int, 3: float}
     */
    private function pixel(GdImage $image, int $x, int $y): array
    {
        $color = imagecolorat($image, $x, $y);

        return [($color >> 16) & 0xFF, ($color >> 8) & 0xFF, $color & 0xFF, (127 - (($color >> 24) & 0x7F)) / 127.0];
    }

    /**
     * A doughnut: a dark ring with a background-coloured hole (radius 40) at the centre.
     */
    private function doughnut(string $relative): string
    {
        return $this->art($relative, function (GdImage $image) {
            imagefilledellipse($image, 200, 200, 240, 240, imagecolorallocate($image, 110, 60, 40));
            imagefilledellipse($image, 200, 200, 80, 80, imagecolorallocate($image, ...self::BACKGROUND));
        });
    }

    public function test_cream_and_white_parts_of_a_character_survive_while_the_background_goes(): void
    {
        $source = $this->art('glory/polar-bear.png', function (GdImage $image) {
            // A cream object straight on the background.
            imagefilledrectangle($image, 20, 20, 120, 120, imagecolorallocate($image, 246, 238, 222));
            // A character with a dark outline, a white belly in the background's own colour and a cream patch near-white enough for a global threshold.
            imagefilledellipse($image, 260, 260, 200, 200, imagecolorallocate($image, 90, 70, 60));
            imagefilledellipse($image, 260, 260, 180, 180, imagecolorallocate($image, ...self::BACKGROUND));
            imagefilledrectangle($image, 250, 190, 290, 220, imagecolorallocate($image, 248, 244, 236));
        });

        $cutout = $this->cutout($source);

        $this->assertSame(0.0, $this->pixel($cutout, 5, 395)[3], 'The background is transparent.');
        $this->assertSame(0.0, $this->pixel($cutout, 200, 60)[3]);
        $this->assertSame([246, 238, 222, 1.0], $this->pixel($cutout, 70, 70), 'The cream object is untouched.');
        $this->assertSame([...self::BACKGROUND, 1.0], $this->pixel($cutout, 260, 280), 'The white belly is untouched.');
        $this->assertSame([248, 244, 236, 1.0], $this->pixel($cutout, 270, 205), 'The cream patch is untouched.');
    }

    public function test_a_soft_shadow_becomes_a_partly_transparent_shadow(): void
    {
        $source = $this->art('glory/shadow.png', function (GdImage $image) {
            for ($radius = 60; $radius >= 1; $radius--) {
                $depth = (int) round(14 * (1 - $radius / 60));
                imagefilledellipse($image, 200, 200, $radius * 2, $radius * 2, imagecolorallocate($image, 252 - $depth, 251 - $depth, 249 - $depth));
            }
        });

        $cutout = $this->cutout($source);
        $original = imagecreatefrompng($source);
        [, , , $centre] = $this->pixel($cutout, 200, 200);
        [, , , $halfway] = $this->pixel($cutout, 230, 200);

        $this->assertGreaterThan(0.3, $centre);
        $this->assertLessThan(1.0, $centre, 'The shadow is partly transparent, not cut out as a solid grey disc.');
        $this->assertGreaterThan(0.0, $halfway);
        $this->assertLessThan($centre, $halfway, 'It fades out towards its edge.');
        $this->assertSame(0.0, $this->pixel($cutout, 200, 280)[3]);

        foreach ([[200, 200], [215, 200], [230, 200], [245, 200]] as [$x, $y]) {
            [$red, $green, $blue, $opacity] = $this->pixel($cutout, $x, $y);
            $before = $this->pixel($original, $x, $y);

            foreach ([$red, $green, $blue] as $channel => $value) {
                $this->assertLessThanOrEqual(self::BACKGROUND[$channel], $value, 'The shadow is darker than the background.');
                $this->assertEqualsWithDelta($before[$channel], $value * $opacity + self::BACKGROUND[$channel] * (1 - $opacity), 2.5, 'Over the old background it looks as before.');
            }
        }
    }

    public function test_an_anti_aliased_edge_stays_smooth_without_a_light_halo(): void
    {
        $source = $this->art('glory/dark-disc.png', function (GdImage $image) {
            $large = imagecreatetruecolor(1600, 1600);
            imagefill($large, 0, 0, imagecolorallocate($large, ...self::BACKGROUND));
            imagefilledellipse($large, 800, 800, 1000, 1000, imagecolorallocate($large, 30, 40, 80));
            imagecopyresampled($image, $large, 0, 0, 0, 0, 400, 400, 1600, 1600);
        });

        $cutout = $this->cutout($source);
        $partial = 0;
        $light = [];

        for ($y = 0; $y < 400; $y++) {
            for ($x = 0; $x < 400; $x++) {
                [$red, $green, $blue, $opacity] = $this->pixel($cutout, $x, $y);

                if ($opacity > 0 && $opacity < 1) {
                    $partial++;
                }

                if ($opacity >= 0.05 && ($red > 30 + 30 || $green > 40 + 30 || $blue > 80 + 30)) {
                    $light[] = "{$x},{$y}: {$red},{$green},{$blue} at {$opacity}";
                }
            }
        }

        $this->assertSame([], $light, 'No pixel of the cut-out is lighter than the disc: no halo.');
        $this->assertGreaterThan(200, $partial, 'The anti-aliasing stays as partial opacity.');
    }

    public function test_an_enclosed_background_region_is_kept_and_reported_with_a_seed(): void
    {
        $source = $this->doughnut('glory/fastest-food.png');

        $cutout = $this->cutout($source);
        $report = app(BackgroundRemoval::class)->report($source);

        $this->assertSame(1.0, $this->pixel($cutout, 200, 200)[3], 'The hole is not removed automatically.');
        $this->assertSame(0.0, $this->pixel($cutout, 5, 5)[3]);
        $this->assertSame(self::BACKGROUND, $report['background']);
        $this->assertCount(1, $report['enclosed']);

        $hole = $report['enclosed'][0];
        $this->assertFalse($hole['removed']);
        $this->assertEqualsWithDelta(M_PI * 40 * 40, $hole['pixels'], 200);
        $this->assertEqualsWithDelta(160, $hole['left'], 2);
        $this->assertEqualsWithDelta(240, $hole['right'], 2);
        $this->assertEqualsWithDelta(160, $hole['top'], 2);
        $this->assertEqualsWithDelta(240, $hole['bottom'], 2);
        $this->assertLessThan(40, hypot($hole['seed'][0] - 200, $hole['seed'][1] - 200), 'The seed lies in the hole.');
    }

    public function test_an_enclosed_region_marked_by_a_seed_is_removed_and_a_stray_seed_is_reported(): void
    {
        $source = $this->doughnut('glory/fastest-food.png');
        $this->doughnut('glory/other-food.png');
        config(['stamps.background.remove_enclosed' => ['glory/fastest-food.png' => [[200, 200], [200, 120]]]]);

        $cutout = $this->cutout($source);
        $report = app(BackgroundRemoval::class)->report($source);

        $this->assertSame(0.0, $this->pixel($cutout, 200, 200)[3], 'The seeded hole is removed.');
        $this->assertSame(1.0, $this->pixel($cutout, 200, 130)[3], 'The ring stays.');
        $this->assertTrue($report['enclosed'][0]['removed']);
        $this->assertSame([['seed' => [200, 120], 'reason' => 'not the background colour']], $report['unused_seeds']);
        $this->assertSame(1.0, $this->pixel($this->cutout("{$this->root}/art/glory/other-food.png"), 200, 200)[3], 'A seed applies to its own art file only.');
    }

    public function test_the_source_is_never_changed_and_the_copies_are_cached(): void
    {
        $source = $this->doughnut('glory/fastest-food.png');
        $before = hash_file('sha1', $source);
        $modified = filemtime($source);

        $cutout = app(BackgroundRemoval::class)->cutout($source);
        $framed = app(StampArt::class)->framed($source);

        $this->assertSame($before, hash_file('sha1', $source));
        $this->assertSame($modified, filemtime($source));
        $this->assertStringStartsWith("{$this->root}/cache/cutout/", $cutout);
        $this->assertStringStartsWith("{$this->root}/cache/", $framed);
        $this->assertSame($cutout, app(BackgroundRemoval::class)->cutout($source));

        config(['stamps.background.remove_enclosed' => ['glory/fastest-food.png' => [[200, 200]]]]);
        $this->assertNotSame($cutout, app(BackgroundRemoval::class)->cutout($source), 'A seed gives another copy.');
        $this->assertNotSame($framed, app(StampArt::class)->framed($source));
        $this->assertSame($before, hash_file('sha1', $source));
    }

    public function test_the_framing_trims_by_the_new_alpha_so_faint_shading_leaves_no_box(): void
    {
        $source = $this->art('glory/shaded.png', function (GdImage $image) {
            // Faint background shading (2 to 3 levels below the background) around a red block.
            imagefilledrectangle($image, 40, 40, 360, 360, imagecolorallocate($image, 250, 248, 247));
            imagefilledrectangle($image, 150, 150, 250, 250, imagecolorallocate($image, 200, 60, 40));
        });

        $framed = app(StampArt::class)->frame($source);
        [$left, $top, $right, $bottom] = app(StampArt::class)->contentBox($framed);

        $this->assertEqualsWithDelta(1.0, ($right - $left + 1) / ($bottom - $top + 1), 0.01, 'The box is the block, not the shading around it.');
        $this->assertEqualsWithDelta(0.392, ($right - $left + 1) * ($bottom - $top + 1) / (800 * 1000), 0.003);
        $this->assertSame(127, (imagecolorat($framed, 2, 2) >> 24) & 0x7F, 'The window around it is transparent.');
    }

    public function test_the_preview_command_reports_the_enclosed_regions_with_their_seeds(): void
    {
        $this->doughnut('glory/fastest-food.png');

        $this->artisan('oof:stamps:preview', ['--out' => "{$this->root}/preview"])
            ->expectsOutputToContain('1 enclosed background regions')
            ->expectsOutputToContain('glory/fastest-food.png')
            ->assertSuccessful();
    }
}
