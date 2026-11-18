<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether an achievement is only ever awarded by hand in the CMS (Built
 * Different), a mechanic from the catalogue (a manual trigger) that
 * oof:achievements:sync writes. The engine never detects it.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('achievements', function (Blueprint $table) {
            $table->boolean('is_manual')->default(false)->after('withheld_on_sensitive_weekends');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('achievements', function (Blueprint $table) {
            $table->dropColumn('is_manual');
        });
    }
};
