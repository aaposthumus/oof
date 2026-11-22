<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Achievements\Pages\EditAchievement;
use App\Filament\Resources\Achievements\Pages\ListAchievements;
use App\Models\Achievement;
use App\Models\User;
use Database\Seeders\AchievementSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The framed stamp art in the CMS (Phase 12f): a thumbnail on the
 * achievement pages, served only to people who may use the panel.
 */
class StampArtPreviewTest extends TestCase
{
    use LazilyRefreshDatabase;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AchievementSeeder::class);
        config(['admin.emails' => ['admin@example.com']]);
        $this->root = storage_path('framework/testing/stamps-'.uniqid());
        config(['stamps.art_path' => "{$this->root}/art", 'stamps.cache_path' => "{$this->root}/cache"]);

        $image = imagecreatetruecolor(300, 400);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imagefilledrectangle($image, 50, 50, 200, 300, imagecolorallocate($image, 30, 90, 160));
        File::ensureDirectoryExists("{$this->root}/art/glory");
        imagepng($image, "{$this->root}/art/glory/early-bird.png");
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);

        parent::tearDown();
    }

    private function actingAsAdmin(): void
    {
        $this->actingAs(User::factory()->create(['email' => 'admin@example.com']));
        Filament::setCurrentPanel('nimda');
    }

    public function test_an_admin_gets_the_framed_art(): void
    {
        $this->actingAsAdmin();

        $response = $this->get(route('stamps.show', Achievement::firstWhere('key', 'early_bird')));

        $response->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertSame([800, 1000], array_slice(getimagesize($response->baseResponse->getFile()->getPathname()), 0, 2));
    }

    public function test_a_guest_or_a_non_admin_gets_nothing_and_missing_art_is_a_404(): void
    {
        $earlyBird = Achievement::firstWhere('key', 'early_bird');

        $this->get(route('stamps.show', $earlyBird))->assertForbidden();

        $this->actingAs(User::factory()->create(['email' => 'someone@example.com']));
        $this->get(route('stamps.show', $earlyBird))->assertForbidden();

        $this->actingAsAdmin();
        $this->get(route('stamps.show', Achievement::firstWhere('key', 'encore')))->assertNotFound();
    }

    public function test_the_achievement_list_and_page_show_the_thumbnail(): void
    {
        $this->actingAsAdmin();
        $earlyBird = Achievement::firstWhere('key', 'early_bird');

        Livewire::test(ListAchievements::class)
            ->assertTableColumnStateSet('art', route('stamps.show', $earlyBird), $earlyBird)
            ->assertTableColumnStateNotSet('art', route('stamps.show', Achievement::firstWhere('key', 'encore')), Achievement::firstWhere('key', 'encore'));

        Livewire::test(EditAchievement::class, ['record' => $earlyBird->getRouteKey()])
            ->assertOk()
            ->assertSee(route('stamps.show', $earlyBird));
    }
}
