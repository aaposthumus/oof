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
        Schema::create('team_radio_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('race_session_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('driver_number');
            $table->foreignId('driver_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('recorded_at', 3);
            $table->string('recording_url', 500);
            $table->timestamps();

            $table->unique(['race_session_id', 'driver_number', 'recorded_at'], 'team_radio_messages_session_driver_recorded_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('team_radio_messages');
    }
};
