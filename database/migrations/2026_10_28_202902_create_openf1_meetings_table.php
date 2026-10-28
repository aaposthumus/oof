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
        Schema::create('openf1_meetings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('meeting_key')->unique();
            $table->unsignedSmallInteger('year');
            $table->string('name');
            $table->string('official_name')->nullable();
            $table->unsignedSmallInteger('circuit_key');
            $table->string('circuit_short_name');
            $table->string('location')->nullable();
            $table->string('country_name')->nullable();
            $table->dateTime('date_start');
            $table->dateTime('date_end')->nullable();
            $table->boolean('is_cancelled')->default(false);
            $table->foreignId('round_id')->nullable()->constrained()->nullOnDelete();
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
        Schema::dropIfExists('openf1_meetings');
    }
};
