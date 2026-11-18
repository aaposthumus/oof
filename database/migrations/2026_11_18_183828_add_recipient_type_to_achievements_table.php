<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Who an achievement goes to (driver, team, safety_car), a mechanic from the
 * catalogue that oof:achievements:sync writes. Until now it followed from
 * the shelf: the Safety Car for its own shelf, a driver otherwise. The
 * Safety Car achievements are written out here, as the catalogue has them.
 */
return new class extends Migration
{
    private const SAFETY_CAR = ['took_the_lead', 'second_helping', 'frequent_flyer', 'virtually_there'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('achievements', function (Blueprint $table) {
            $table->string('recipient_type', 16)->default('driver')->after('shelf');
        });

        DB::table('achievements')->whereIn('key', self::SAFETY_CAR)->update(['recipient_type' => 'safety_car', 'updated_at' => now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('achievements', function (Blueprint $table) {
            $table->dropColumn('recipient_type');
        });
    }
};
