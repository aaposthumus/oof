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
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('race_session_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('driver_number');
            $table->foreignId('driver_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('recorded_at', 3);
            $table->unsignedTinyInteger('position');
            $table->timestamps();

            $table->unique(['race_session_id', 'driver_number', 'recorded_at']);
            $table->index(['driver_entry_id', 'recorded_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('positions');
    }
};
