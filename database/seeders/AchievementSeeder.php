<?php

namespace Database\Seeders;

use App\Achievements\Catalogue;
use App\Achievements\CatalogueSync;
use App\Models\Achievement;
use Illuminate\Database\Seeder;

/**
 * The achievements of config/achievement_catalogue.php. Only missing keys are
 * created, as oof:achievements:sync creates them: a row edited in the CMS
 * keeps its edits. The mechanics of an existing row are written by the sync.
 */
class AchievementSeeder extends Seeder
{
    public function run(Catalogue $catalogue, CatalogueSync $sync): void
    {
        foreach ($catalogue->keys() as $key) {
            Achievement::firstOrCreate(['key' => $key], [...$catalogue->editorial($key), ...$sync->owned($key)]);
        }
    }
}
