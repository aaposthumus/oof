<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The side of an Oofie (Phase 12): glory or oof. It picks the art folder and
 * the stamp's theme, and when the award counts: a glory-side Oofie like the
 * glory shelf (from 1950), an OOF-side one like the Oofies (from 2023). Only
 * achievements on the Oofies shelf have one. A mechanic, written by
 * oof:achievements:sync; the three Oofies so far are on the OOF side.
 */
return new class extends Migration
{
    private const OOF_SIDE = ['most_collected', 'oofscore_champion', 'rarest_award'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('achievements', function (Blueprint $table) {
            $table->string('side', 16)->nullable()->after('shelf');
        });

        DB::table('achievements')->whereIn('key', self::OOF_SIDE)->where('shelf', 'oofies')->update(['side' => 'oof']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('achievements', function (Blueprint $table) {
            $table->dropColumn('side');
        });
    }
};
