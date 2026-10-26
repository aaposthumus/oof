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
        Schema::create('pit_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_entry_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('stop');
            $table->unsignedSmallInteger('lap');
            $table->time('time_of_day')->nullable();
            $table->unsignedInteger('duration_millis')->nullable();
            $table->timestamps();

            $table->unique(['driver_entry_id', 'stop']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pit_stops');
    }
};
