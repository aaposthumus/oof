<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Where a sensitivity came from (seed, manual or engine) and, for one the
 * engine proposed, when a person confirmed or dismissed it. A proposal acts
 * as sensitive until then; a dismissed one is ignored everywhere. The rows
 * that exist were seeded.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('moment_sensitivities', function (Blueprint $table) {
            $table->string('source', 16)->default('manual')->after('severity');
            $table->timestamp('confirmed_at')->nullable()->after('source');
            $table->timestamp('dismissed_at')->nullable()->after('confirmed_at');
        });

        DB::table('moment_sensitivities')->update(['source' => 'seed']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('moment_sensitivities', function (Blueprint $table) {
            $table->dropColumn(['source', 'confirmed_at', 'dismissed_at']);
        });
    }
};
