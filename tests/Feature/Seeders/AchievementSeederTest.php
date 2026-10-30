<?php

namespace Tests\Feature\Seeders;

use App\Achievements\AchievementEngine;
use App\Enums\AchievementShelf;
use App\Models\Achievement;
use Database\Seeders\AchievementSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AchievementSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_seeds_one_achievement_per_detector(): void
    {
        $this->seed(AchievementSeeder::class);

        $this->assertEqualsCanonicalizing(array_keys(app(AchievementEngine::class)->detectors()), Achievement::pluck('key')->all());
    }

    public function test_every_superseded_key_exists_and_only_oofs_earn_points(): void
    {
        $this->seed(AchievementSeeder::class);
        $keys = Achievement::pluck('key');

        foreach (Achievement::all() as $achievement) {
            $this->assertEmpty(array_diff($achievement->supersedes, $keys->all()), $achievement->key);
            $this->assertSame($achievement->shelf === AchievementShelf::Oof && $achievement->key !== 'nothing_to_see_here', $achievement->points > 0, $achievement->key);
        }
    }

    public function test_whole_class_detention_is_silver_and_nothing_to_see_here_earns_nothing(): void
    {
        $this->seed(AchievementSeeder::class);

        $achievement = Achievement::firstWhere('key', 'whole_class_detention');

        $this->assertSame(['silver', 25], [$achievement->tier->value, $achievement->points]);
        $this->assertSame(0, Achievement::firstWhere('key', 'nothing_to_see_here')->points);
    }

    public function test_keeps_what_was_edited_in_the_cms(): void
    {
        $this->seed(AchievementSeeder::class);
        Achievement::where('key', 'lapped_dog')->update(['name' => 'Lapped Pup', 'points' => 5]);

        $this->seed(AchievementSeeder::class);

        $this->assertSame(['Lapped Pup', 5], Achievement::where('key', 'lapped_dog')->get(['name', 'points'])->map(fn ($row) => [$row->name, $row->points])->sole());
        $this->assertSame(count(AchievementSeeder::keys()), Achievement::count());
    }
}
