<?php

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
     * The withheld achievements when this migration was written; the catalogue
     * (config/achievement_catalogue.php) holds the setting since.
     */
    private const WITHHELD_ON_SENSITIVE_WEEKENDS = [
        'early_lunch', 'home_before_dinner', 'forgot_the_keys',
        'called_the_tow_truck', 'whole_class_detention',
        'it_was_like_that_when_i_got_there', 'bumper_cars',
        'took_the_lead', 'second_helping', 'frequent_flyer', 'virtually_there',
        'nothing_to_see_here', 'built_different',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('achievements', function (Blueprint $table) {
            $table->boolean('withheld_on_sensitive_weekends')->default(false)->after('is_enabled');
        });

        DB::table('achievements')->whereIn('key', self::WITHHELD_ON_SENSITIVE_WEEKENDS)->update(['withheld_on_sensitive_weekends' => true]);
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
