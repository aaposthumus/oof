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
        Schema::create('constructor_standings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('round_id')->constrained()->cascadeOnDelete();
            $table->foreignId('season_team_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->nullable();
            $table->string('position_text', 4);
            $table->decimal('points', 6, 2);
            $table->unsignedSmallInteger('wins');
            $table->timestamps();

            $table->unique(['round_id', 'season_team_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('constructor_standings');
    }
};
