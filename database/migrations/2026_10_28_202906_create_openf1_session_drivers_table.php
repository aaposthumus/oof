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
        Schema::create('openf1_session_drivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('race_session_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('driver_number');
            $table->string('name_acronym', 3)->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('full_name')->nullable();
            $table->string('broadcast_name')->nullable();
            $table->string('team_name')->nullable();
            $table->char('team_colour', 6)->nullable();
            $table->string('headshot_reference', 16)->nullable();
            $table->foreignId('driver_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('season_team_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('driver_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->string('match_status', 16);
            $table->string('match_note')->nullable();
            $table->timestamps();

            $table->unique(['race_session_id', 'driver_number']);
            $table->index('match_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('openf1_session_drivers');
    }
};
