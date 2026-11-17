<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the OOFscore made of an award: the team's form rank before its
 * round (null: no race in the window) and the multiplier that gave. The
 * award's tier and base points stay as they are. Set by the OOFscore
 * rebuild of an open season and kept once the season is closed.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('achievement_awards', function (Blueprint $table) {
            $table->unsignedSmallInteger('team_form_rank')->nullable()->after('points');
            $table->decimal('score_multiplier', 5, 2)->nullable()->after('team_form_rank');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('achievement_awards', function (Blueprint $table) {
            $table->dropColumn(['team_form_rank', 'score_multiplier']);
        });
    }
};
