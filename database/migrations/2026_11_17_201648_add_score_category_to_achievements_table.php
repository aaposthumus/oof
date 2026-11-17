<?php

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
     * Score categories other than driver when this migration was written; the
     * catalogue (config/achievement_catalogue.php) holds the defaults since.
     */
    private const SCORE_CATEGORIES = [
        'lapped_dog' => 'pace', 'good_boy' => 'pace', 'blue_carpet' => 'pace',
        'holding_the_door' => 'pace', 'turned_off_the_lights' => 'pace', 'reverse_gear' => 'pace',
        'overtime' => 'pace', 'nearly_famous' => 'pace', 'mirror_match' => 'pace',
        'all_dressed_up_nowhere_to_go' => 'pace',
        'forgot_the_keys' => 'reliability', 'early_lunch' => 'reliability', 'home_before_dinner' => 'reliability',
        'sent_to_your_room' => 'reliability', 'table_for_one' => 'reliability', 'fine_dining' => 'reliability',
        'released_into_the_wild' => 'reliability', 'meatball_sub' => 'reliability',
        'economy_class' => 'surprise', 'peaked_too_soon' => 'surprise', 'led_astray' => 'surprise',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('achievements', function (Blueprint $table) {
            $table->string('score_category', 16)->default('driver')->after('tier');
        });

        foreach (self::SCORE_CATEGORIES as $key => $category) {
            DB::table('achievements')->where('key', $key)->update(['score_category' => $category]);
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
