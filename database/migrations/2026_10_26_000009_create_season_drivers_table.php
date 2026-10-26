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
        Schema::create('season_drivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained()->cascadeOnDelete();
            $table->foreignId('season_team_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('usual_number')->nullable();
            $table->string('role', 16)->default('race_driver');
            $table->unsignedTinyInteger('first_round_number')->nullable();
            $table->unsignedTinyInteger('last_round_number')->nullable();
            $table->timestamps();

            $table->unique(['season_id', 'driver_id', 'season_team_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('season_drivers');
    }
};
