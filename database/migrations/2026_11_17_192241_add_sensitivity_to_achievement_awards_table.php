<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An award of a sensitive weekend is flagged, with the note why. The flag
 * never clears; the publishing layer refuses flagged awards in automatic
 * and comedic formats.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('achievement_awards', function (Blueprint $table) {
            $table->boolean('is_sensitive')->default(false)->after('source');
            $table->text('sensitivity_note')->nullable()->after('is_sensitive');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('achievement_awards', function (Blueprint $table) {
            $table->dropColumn(['is_sensitive', 'sensitivity_note']);
        });
    }
};
