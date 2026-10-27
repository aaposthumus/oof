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
        Schema::table('moment_sensitivities', function (Blueprint $table) {
            $table->string('severity', 16)->default('fatal')->after('level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('moment_sensitivities', function (Blueprint $table) {
            $table->dropColumn('severity');
        });
    }
};
