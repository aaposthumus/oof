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
        Schema::create('season_teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('jolpica_constructor_id', 64);
            $table->string('name');
            $table->string('nationality')->nullable();
            $table->char('primary_colour', 7)->nullable();
            $table->char('secondary_colour', 7)->nullable();
            $table->timestamps();

            $table->unique(['season_id', 'jolpica_constructor_id']);
            $table->index(['team_id', 'season_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('season_teams');
    }
};
