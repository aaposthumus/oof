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
        Schema::create('session_laps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('race_session_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('driver_number');
            $table->foreignId('driver_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('lap_number');
            $table->dateTime('started_at', 3)->nullable();
            $table->unsignedInteger('duration_millis')->nullable();
            $table->unsignedInteger('sector_1_millis')->nullable();
            $table->unsignedInteger('sector_2_millis')->nullable();
            $table->unsignedInteger('sector_3_millis')->nullable();
            $table->unsignedSmallInteger('i1_speed')->nullable();
            $table->unsignedSmallInteger('i2_speed')->nullable();
            $table->unsignedSmallInteger('st_speed')->nullable();
            $table->boolean('is_pit_out_lap')->default(false);
            $table->timestamps();

            $table->unique(['race_session_id', 'driver_number', 'lap_number']);
            $table->index(['driver_entry_id', 'lap_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('session_laps');
    }
};
