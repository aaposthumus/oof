<?php

use Database\Seeders\AchievementSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Each achievement's score category (driver, pace, reliability, surprise),
 * which decides whether the OOFscore weighs it by team form. Editable in
 * the CMS; everything not listed is the driver's own doing.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('achievements', function (Blueprint $table) {
            $table->string('score_category', 16)->default('driver')->after('tier');
        });

        foreach (AchievementSeeder::SCORE_CATEGORIES as $key => $category) {
            DB::table('achievements')->where('key', $key)->update(['score_category' => $category->value]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('achievements', function (Blueprint $table) {
            $table->dropColumn('score_category');
        });
    }
};
