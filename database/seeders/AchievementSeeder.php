<?php

namespace Database\Seeders;

use App\Achievements\Catalogue;
use App\Models\Achievement;
use Illuminate\Database\Seeder;

/**
 * The achievements of config/achievement_catalogue.php. Only missing keys are
 * created: a row edited in the CMS keeps its edits. The mechanics of an
 * existing row are written by oof:achievements:sync.
 */
class AchievementSeeder extends Seeder
{
    public function run(Catalogue $catalogue): void
    {
        foreach ($catalogue->keys() as $key) {
            Achievement::firstOrCreate(['key' => $key], [...$catalogue->editorial($key), ...$catalogue->mechanics($key)]);
        }
    }
}
