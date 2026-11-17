<?php

use App\Normalizers\Jolpica\Support\TeamLineageSync;
use Database\Seeders\TeamLineageSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The team lineage moves from config/f1.php into a table that can be edited
 * in the CMS, with the historic renames that were missing. Existing season
 * teams are re-pointed to their lasting team straight away.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('team_lineages', function (Blueprint $table) {
            $table->id();
            $table->string('jolpica_constructor_id', 64)->index();
            $table->unsignedSmallInteger('from_year')->nullable();
            $table->unsignedSmallInteger('to_year')->nullable();
            $table->string('team_slug', 64);
            $table->string('note')->nullable();
            $table->timestamps();
        });

        (new TeamLineageSeeder)->run();
        app(TeamLineageSync::class)->run();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('team_lineages');
    }
};
