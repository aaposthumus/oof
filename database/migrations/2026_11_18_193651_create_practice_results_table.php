<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Practice classifications from OpenF1's session results: a car's place,
 * its best lap and the laps it drove. Fool's Gold reads who topped a session.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('practice_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_entry_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('position')->nullable();
            $table->unsignedInteger('best_lap_millis')->nullable();
            $table->unsignedSmallInteger('laps')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('practice_results');
    }
};
