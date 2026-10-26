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
        Schema::create('lap_times', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_entry_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('lap');
            $table->unsignedTinyInteger('position')->nullable();
            $table->unsignedInteger('time_millis')->nullable();
            $table->timestamps();

            $table->unique(['driver_entry_id', 'lap']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lap_times');
    }
};
