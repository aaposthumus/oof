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
 * enclosed background regions are removed up to a mean distance, unless on
 * the keep-list, and reported; the sources are never changed. Fixtures are drawn in a
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
     * A doughnut: a dark ring with a hole (radius 40) at the centre, by default in the background colour.
     *
     * @param  array{0: int, 1: int, 2: int}  $hole
     */
    private function doughnut(string $relative, array $hole = self::BACKGROUND): string
    {
        return $this->art($relative, function (GdImage $image) use ($hole) {
            imagefilledellipse($image, 200, 200, 240, 240, imagecolorallocate($image, 110, 60, 40));
            imagefilledellipse($image, 200, 200, 80, 80, imagecolorallocate($image, ...$hole));
        });
    }

    public function test_cream_and_white_parts_of_a_character_survive_while_the_background_goes(): void
    {
        $source = $this->art('glory/polar-bear.png', function (GdImage $image) {
            // A cream object straight on the background.
            imagefilledrectangle($image, 20, 20, 120, 120, imagecolorallocate($image, 246, 238, 222));
            // A character with a dark outline, a white belly within the tolerance (distance 9) and a cream patch, both near-white enough for a global threshold.
            imagefilledellipse($image, 260, 260, 200, 200, imagecolorallocate($image, 90, 70, 60));
            imagefilledellipse($image, 260, 260, 180, 180, imagecolorallocate($image, 246, 244, 240));
            imagefilledrectangle($image, 250, 190, 290, 220, imagecolorallocate($image, 248, 244, 236));
        });

        $cutout = $this->cutout($source);

        $this->assertSame(0.0, $this->pixel($cutout, 5, 395)[3], 'The background is transparent.');
        $this->assertSame(0.0, $this->pixel($cutout, 200, 60)[3]);
        $this->assertSame([246, 238, 222, 1.0], $this->pixel($cutout, 70, 70), 'The cream object is untouched.');
        $this->assertSame([246, 244, 240, 1.0], $this->pixel($cutout, 260, 280), 'The white belly is untouched.');
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

    public function test_an_enclosed_background_hole_is_removed_by_the_rule_and_reported_with_a_seed(): void
    {
        $source = $this->doughnut('glory/fastest-food.png');

        $cutout = $this->cutout($source);
        $report = app(BackgroundRemoval::class)->report($source);

        $this->assertSame(0.0, $this->pixel($cutout, 200, 200)[3], 'The hole (mean distance 0) is removed.');
        $this->assertSame(1.0, $this->pixel($cutout, 200, 130)[3], 'The ring stays.');
        $this->assertSame(self::BACKGROUND, $report['background']);
        $this->assertCount(1, $report['enclosed']);

        $hole = $report['enclosed'][0];
        $this->assertTrue($hole['removed']);
        $this->assertSame('mean distance 0 up to 6', $hole['why']);
        $this->assertEqualsWithDelta(M_PI * 40 * 40, $hole['pixels'], 200);
        $this->assertEqualsWithDelta(160, $hole['left'], 2);
        $this->assertEqualsWithDelta(240, $hole['right'], 2);
        $this->assertEqualsWithDelta(160, $hole['top'], 2);
        $this->assertEqualsWithDelta(240, $hole['bottom'], 2);
        $this->assertLessThan(40, hypot($hole['seed'][0] - 200, $hole['seed'][1] - 200), 'The seed lies in the hole.');
    }

    public function test_an_enclosed_light_surface_above_the_mean_distance_is_kept(): void
    {
        $source = $this->doughnut('glory/fastest-food.png', [244, 243, 241]);

        $cutout = $this->cutout($source);
        $hole = app(BackgroundRemoval::class)->report($source)['enclosed'][0];

        $this->assertSame([244, 243, 241, 1.0], $this->pixel($cutout, 200, 200), 'Mean distance 8 is a surface, not background.');
        $this->assertFalse($hole['removed']);
        $this->assertSame('mean distance 8 above 6', $hole['why']);
    }

    public function test_the_keep_list_keeps_a_region_for_its_own_file_and_a_stray_keep_seed_is_reported(): void
    {
        $source = $this->doughnut('glory/fastest-food.png');
        $this->doughnut('glory/other-food.png');
        config(['stamps.background.keep_enclosed' => ['glory/fastest-food.png' => [[200, 200], [200, 120]]]]);

        $cutout = $this->cutout($source);
        $report = app(BackgroundRemoval::class)->report($source);

        $this->assertSame([...self::BACKGROUND, 1.0], $this->pixel($cutout, 200, 200), 'The listed hole is kept.');
        $this->assertFalse($report['enclosed'][0]['removed']);
        $this->assertSame('keep-list', $report['enclosed'][0]['why']);
        $this->assertSame([['seed' => [200, 120], 'reason' => 'not the background colour']], $report['unused_seeds']);
        $this->assertSame(0.0, $this->pixel($this->cutout("{$this->root}/art/glory/other-food.png"), 200, 200)[3], 'The keep-list applies to its own art file only.');
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

        config(['stamps.background.keep_enclosed' => ['glory/fastest-food.png' => [[200, 200]]]]);
        $this->assertNotSame($cutout, app(BackgroundRemoval::class)->cutout($source), 'A keep-list entry gives another copy.');
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
        config(['stamps.shadow.enabled' => false]);

        $framed = app(StampArt::class)->frame($source);
        [$left, $top, $right, $bottom] = app(StampArt::class)->contentBox($framed);

        $this->assertEqualsWithDelta(1.0, ($right - $left + 1) / ($bottom - $top + 1), 0.01, 'The box is the block, not the shading around it.');
        $this->assertEqualsWithDelta(0.392, ($right - $left + 1) * ($bottom - $top + 1) / (800 * 1000), 0.003);
        $this->assertSame(127, (imagecolorat($framed, 2, 2) >> 24) & 0x7F, 'The window around it is transparent.');
    }

    public function test_an_object_mask_makes_a_floor_shadow_outside_it_transparent_and_keeps_the_object(): void
    {
        $source = $this->art('glory/standing.png', function (GdImage $image) {
            // A floor shadow darker than the tolerance, touching the bottom of a red block.
            imagefilledellipse($image, 200, 300, 260, 40, imagecolorallocate($image, 200, 195, 190));
            imagefilledrectangle($image, 150, 150, 250, 300, imagecolorallocate($image, 200, 60, 40));
        });
        config(['stamps.background.mask' => ['model' => 'test-model', 'path' => "{$this->root}/masks", 'transparent_below' => 32, 'opaque_from' => 128]]);
        $before = $this->pixel($this->cutout($source), 320, 300);

        $mask = imagecreatetruecolor(400, 400);
        imagefilledrectangle($mask, 150, 150, 250, 300, imagecolorallocate($mask, 255, 255, 255));
        File::ensureDirectoryExists("{$this->root}/masks/test-model");
        imagepng($mask, "{$this->root}/masks/test-model/".sha1_file($source).'.png');

        $cutout = $this->cutout($source);

        $this->assertSame(1.0, $before[3], 'Without a mask the dark shadow core stays.');
        $this->assertSame(0.0, $this->pixel($cutout, 320, 300)[3], 'Outside the mask the shadow goes.');
        $this->assertSame([200, 60, 40, 1.0], $this->pixel($cutout, 200, 220), 'The object stays.');
        $this->assertSame('test-model', app(BackgroundRemoval::class)->report($source)['mask']);
    }

    /**
     * A red block with a floor shadow and a cream prop beside it, and a mask
     * from the "model" that covers only the block: it cut off the prop.
     */
    private function propWithMask(string $relative): string
    {
        $source = $this->art($relative, function (GdImage $image) {
            imagefilledellipse($image, 200, 300, 260, 40, imagecolorallocate($image, 200, 195, 190));
            imagefilledrectangle($image, 150, 150, 250, 300, imagecolorallocate($image, 200, 60, 40));
            imagefilledrectangle($image, 40, 340, 140, 370, imagecolorallocate($image, 240, 215, 190));
        });
        $mask = imagecreatetruecolor(400, 400);
        imagefilledrectangle($mask, 150, 150, 250, 300, imagecolorallocate($mask, 255, 255, 255));
        File::ensureDirectoryExists("{$this->root}/masks/test-model");
        imagepng($mask, "{$this->root}/masks/test-model/".sha1_file($source).'.png');

        return $source;
    }

    public function test_inside_a_mask_keep_box_the_mask_removes_nothing(): void
    {
        config(['stamps.background.mask' => ['model' => 'test-model', 'path' => "{$this->root}/masks", 'transparent_below' => 32, 'opaque_from' => 128]]);
        $source = $this->propWithMask('glory/prop.png');
        $before = $this->cutout($source);
        config(['stamps.background.mask.files' => ['glory/prop.png' => ['mask_keep' => [[40, 340, 140, 370]]]]]);

        $cutout = $this->cutout($source);

        $this->assertSame(0.0, $this->pixel($before, 90, 355)[3], 'Without the box the mask cuts off the prop.');
        $this->assertSame([240, 215, 190, 1.0], $this->pixel($cutout, 90, 355), 'Inside the box the prop is back.');
        $this->assertSame(0.0, $this->pixel($cutout, 320, 300)[3], 'Outside the box the floor shadow stays removed.');
        $this->assertSame([200, 60, 40, 1.0], $this->pixel($cutout, 200, 220));
        $this->assertSame([[40, 340, 140, 370]], app(BackgroundRemoval::class)->report($source)['mask_keep']);
    }

    public function test_the_mask_can_be_switched_off_for_one_file(): void
    {
        config(['stamps.background.mask' => ['model' => 'test-model', 'path' => "{$this->root}/masks", 'transparent_below' => 32, 'opaque_from' => 128, 'files' => ['glory/prop.png' => ['mask' => false]]]]);
        $source = $this->propWithMask('glory/prop.png');
        $other = $this->propWithMask('glory/other-prop.png');

        $cutout = $this->cutout($source);

        $this->assertSame([240, 215, 190, 1.0], $this->pixel($cutout, 90, 355), 'Flood fill only: the prop stays.');
        $this->assertSame(1.0, $this->pixel($cutout, 320, 300)[3], 'And so does the dark floor shadow core.');
        $this->assertNull(app(BackgroundRemoval::class)->report($source)['mask']);
        $this->assertSame(0.0, $this->pixel($this->cutout($other), 90, 355)[3], 'Other files keep their mask.');
    }

    public function test_the_synthetic_shadow_is_a_soft_ellipse_under_the_footprint_behind_the_object(): void
    {
        $source = $this->art('glory/raised-arm.png', function (GdImage $image) {
            // A body with an arm sticking out to the right at the top: the footprint is the body only.
            imagefilledrectangle($image, 150, 100, 250, 300, imagecolorallocate($image, 200, 60, 40));
            imagefilledrectangle($image, 250, 100, 350, 130, imagecolorallocate($image, 200, 60, 40));
        });
        config(['stamps.shadow.enabled' => true]);

        $framed = app(StampArt::class)->frame($source);
        [$left, , $right, $bottom] = app(StampArt::class)->contentBox(app(StampArt::class)->frame($source));
        $opacityAt = fn (int $x, int $y) => (127 - ((imagecolorat($framed, $x, $y) >> 24) & 0x7F)) / 127.0;
        config(['stamps.shadow.enabled' => false]);
        [$bodyLeft, , , $bodyBottom] = app(StampArt::class)->contentBox(app(StampArt::class)->frame($source));
        $bodyRight = $bodyLeft + (int) round(($right - $left) * 100 / 200);

        $this->assertGreaterThan(0.15, $opacityAt(intdiv($bodyLeft + $bodyRight, 2), $bodyBottom + 2), 'Shadow right under the body.');
        $this->assertLessThanOrEqual(0.29, $opacityAt(intdiv($bodyLeft + $bodyRight, 2), $bodyBottom + 2));
        $this->assertSame(0.0, $opacityAt($right - 2, $bodyBottom + 2), 'Not under the arm: it follows the footprint.');
        $this->assertSame(0.0, $opacityAt(intdiv($bodyLeft + $bodyRight, 2), $bodyBottom + 60), 'Low and flat.');
        $this->assertSame(1.0, $opacityAt(intdiv($bodyLeft + $bodyRight, 2), $bodyBottom - 5), 'Behind the object, which stays opaque.');
    }

    public function test_the_preview_command_reports_the_enclosed_regions_with_their_seeds(): void
    {
        $this->doughnut('glory/fastest-food.png');

        $this->artisan('oof:stamps:preview', ['--out' => "{$this->root}/preview"])
            ->expectsOutputToContain('1 enclosed background regions')
            ->expectsOutputToContain('removed: mean distance 0 up to 6')
            ->assertSuccessful();
    }
}
