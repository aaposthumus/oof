<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The OOFscore standings after every round of a season: a projection of the
 * final driver awards on the scoring shelves, rebuilt from them at will.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('oofscore_standings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->foreignId('round_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('points');
            $table->unsignedSmallInteger('awards_count');
            $table->unsignedSmallInteger('position');
            $table->smallInteger('position_change')->nullable();
            $table->timestamps();

            $table->unique(['season_id', 'round_id', 'driver_id']);
            $table->index(['round_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('oofscore_standings');
    }
};
