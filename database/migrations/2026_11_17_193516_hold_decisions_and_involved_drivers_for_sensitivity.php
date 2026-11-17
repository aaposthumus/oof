<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Follow-ups to the sensitivity phase:
 * - an award the system suppressed keeps the person's decision it replaced
 *   (held_decision), so dismissing the sensitivity can restore it;
 * - an engine proposal covers the whole round and lists every driver
 *   involved (involved_driver_ids);
 * - four more achievements are withheld on sensitive weekends.
 */
return new class extends Migration
{
    private const MORE_WITHHELD = ['released_into_the_wild', 'failed_the_driving_test', 'selective_vision', 'queue_jumper'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('achievement_awards', function (Blueprint $table) {
            $table->json('held_decision')->nullable()->after('decision_note');
        });

        Schema::table('moment_sensitivities', function (Blueprint $table) {
            $table->json('involved_driver_ids')->nullable()->after('driver_id');
        });

        DB::table('achievements')->whereIn('key', self::MORE_WITHHELD)->update(['withheld_on_sensitive_weekends' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('achievements')->whereIn('key', self::MORE_WITHHELD)->update(['withheld_on_sensitive_weekends' => false]);

        Schema::table('moment_sensitivities', function (Blueprint $table) {
            $table->dropColumn('involved_driver_ids');
        });

        Schema::table('achievement_awards', function (Blueprint $table) {
            $table->dropColumn('held_decision');
        });
    }
};
