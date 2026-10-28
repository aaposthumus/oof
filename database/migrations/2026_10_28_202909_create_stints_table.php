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
        Schema::create('stints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('race_session_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('driver_number');
            $table->foreignId('driver_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('stint_number');
            $table->string('compound', 16)->nullable();
            $table->unsignedSmallInteger('lap_start')->nullable();
            $table->unsignedSmallInteger('lap_end')->nullable();
            $table->unsignedSmallInteger('tyre_age_at_start')->nullable();
            $table->timestamps();

            $table->unique(['race_session_id', 'driver_number', 'stint_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stints');
    }
};
