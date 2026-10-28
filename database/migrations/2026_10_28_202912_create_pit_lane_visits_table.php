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
        Schema::create('pit_lane_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('race_session_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('driver_number');
            $table->foreignId('driver_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('pit_stop_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('lap_number')->nullable();
            $table->dateTime('entered_at', 3);
            $table->unsignedInteger('lane_duration_millis')->nullable();
            $table->unsignedInteger('stop_duration_millis')->nullable();
            $table->timestamps();

            $table->unique(['race_session_id', 'driver_number', 'entered_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pit_lane_visits');
    }
};
