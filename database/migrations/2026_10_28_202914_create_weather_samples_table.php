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
        Schema::create('weather_samples', function (Blueprint $table) {
            $table->id();
            $table->foreignId('race_session_id')->constrained()->cascadeOnDelete();
            $table->dateTime('recorded_at', 3);
            $table->decimal('air_temperature', 4, 1)->nullable();
            $table->decimal('track_temperature', 4, 1)->nullable();
            $table->decimal('humidity', 4, 1)->nullable();
            $table->decimal('pressure', 6, 1)->nullable();
            $table->boolean('rainfall')->nullable();
            $table->decimal('wind_speed', 4, 1)->nullable();
            $table->unsignedSmallInteger('wind_direction')->nullable();
            $table->timestamps();

            $table->unique(['race_session_id', 'recorded_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weather_samples');
    }
};
