<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When a season's OOFscore was closed. From then on its awards keep their
 * tier and points whatever the catalogue says. Nothing closes a season yet.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('seasons', function (Blueprint $table) {
            $table->timestamp('oofscore_closed_at')->nullable()->after('wikipedia_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('seasons', function (Blueprint $table) {
            $table->dropColumn('oofscore_closed_at');
        });
    }
};
