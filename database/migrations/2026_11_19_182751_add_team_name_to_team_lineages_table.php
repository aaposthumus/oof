<?php

use App\Normalizers\Jolpica\Support\TeamLineageSync;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * An optional fixed name per lineage rule: a lasting team that has one keeps
 * it, instead of taking the name of its latest season (Cooper, not
 * "Cooper-Maserati"). No rule has one yet; the historic merges set them.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('team_lineages', function (Blueprint $table) {
            $table->string('team_name')->nullable()->after('team_slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('team_lineages')->update(['team_name' => null]);
        app(TeamLineageSync::class)->run();

        Schema::table('team_lineages', function (Blueprint $table) {
            $table->dropColumn('team_name');
        });
    }
};
