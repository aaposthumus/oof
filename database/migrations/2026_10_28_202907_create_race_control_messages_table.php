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
        Schema::create('race_control_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('race_session_id')->constrained()->cascadeOnDelete();
            $table->char('fingerprint', 40);
            $table->dateTime('issued_at', 3);
            $table->unsignedSmallInteger('lap_number')->nullable();
            $table->string('category', 32);
            $table->string('flag', 32)->nullable();
            $table->string('scope', 16)->nullable();
            $table->unsignedTinyInteger('sector')->nullable();
            $table->unsignedTinyInteger('qualifying_phase')->nullable();
            $table->unsignedSmallInteger('driver_number')->nullable();
            $table->unsignedSmallInteger('car_number')->nullable();
            $table->foreignId('driver_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->json('car_numbers')->nullable();
            $table->text('message');
            $table->timestamps();

            $table->unique(['race_session_id', 'fingerprint']);
            $table->index(['race_session_id', 'issued_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('race_control_messages');
    }
};
