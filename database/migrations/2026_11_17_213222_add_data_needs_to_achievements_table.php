<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The data an achievement reads and the first year it supports, derived
 * from its trigger's facts and predicates and written by
 * oof:achievements:sync, for the CMS.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('achievements', function (Blueprint $table) {
            $table->json('data_sources')->nullable()->after('withheld_on_sensitive_weekends');
            $table->unsignedSmallInteger('available_from')->nullable()->after('data_sources');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('achievements', function (Blueprint $table) {
            $table->dropColumn(['data_sources', 'available_from']);
        });
    }
};
