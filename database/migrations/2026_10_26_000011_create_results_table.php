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
        Schema::create('results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_entry_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('grid')->nullable();
            $table->unsignedTinyInteger('position')->nullable();
            $table->string('position_text', 4);
            $table->unsignedSmallInteger('classification_order');
            $table->boolean('is_classified');
            $table->string('status', 64);
            $table->decimal('points', 5, 2);
            $table->unsignedSmallInteger('laps_completed')->nullable();
            $table->string('time_text', 20)->nullable();
            $table->unsignedInteger('time_millis')->nullable();
            $table->unsignedSmallInteger('fastest_lap_number')->nullable();
            $table->unsignedTinyInteger('fastest_lap_rank')->nullable();
            $table->unsignedInteger('fastest_lap_time_millis')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('results');
    }
};
