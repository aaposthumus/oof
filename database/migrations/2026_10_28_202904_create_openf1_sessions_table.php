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
        Schema::create('openf1_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('session_key')->unique();
            $table->unsignedInteger('meeting_key')->index();
            $table->unsignedSmallInteger('year');
            $table->string('name');
            $table->string('session_type', 32);
            $table->dateTime('date_start');
            $table->dateTime('date_end')->nullable();
            $table->boolean('is_cancelled')->default(false);
            $table->foreignId('race_session_id')->nullable()->constrained()->nullOnDelete();
            $table->string('match_status', 16);
            $table->string('match_note')->nullable();
            $table->timestamps();

            $table->index(['year', 'match_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('openf1_sessions');
    }
};
