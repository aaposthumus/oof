<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('circuits', function (Blueprint $table) {
            $table->unsignedSmallInteger('openf1_circuit_key')->nullable()->unique()->after('jolpica_circuit_id');
        });

        Schema::table('rounds', function (Blueprint $table) {
            $table->unsignedInteger('openf1_meeting_key')->nullable()->unique()->after('jolpica_race_name');
        });

        Schema::table('race_sessions', function (Blueprint $table) {
            $table->unsignedInteger('openf1_session_key')->nullable()->unique()->after('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('race_sessions', function (Blueprint $table) {
            $table->dropColumn('openf1_session_key');
        });

        Schema::table('rounds', function (Blueprint $table) {
            $table->dropColumn('openf1_meeting_key');
        });

        Schema::table('circuits', function (Blueprint $table) {
            $table->dropColumn('openf1_circuit_key');
        });
    }
};
