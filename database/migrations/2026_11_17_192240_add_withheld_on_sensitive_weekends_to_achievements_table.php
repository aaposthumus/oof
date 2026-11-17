<?php

use Database\Seeders\AchievementSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Achievements that are never awarded on a sensitive weekend: retirements,
 * the DNS, causes, incidents, collisions, the Safety Car and Nothing to See
 * Here. A safety setting, not editorial, so it is not edited in the CMS.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('achievements', function (Blueprint $table) {
            $table->boolean('withheld_on_sensitive_weekends')->default(false)->after('is_enabled');
        });

        DB::table('achievements')->whereIn('key', AchievementSeeder::WITHHELD_ON_SENSITIVE_WEEKENDS)->update(['withheld_on_sensitive_weekends' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('achievements', function (Blueprint $table) {
            $table->dropColumn('withheld_on_sensitive_weekends');
        });
    }
};
