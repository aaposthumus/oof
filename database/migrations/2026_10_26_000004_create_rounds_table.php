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
        Schema::create('rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('round_number');
            $table->foreignId('circuit_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('short_name');
            $table->string('country_name')->nullable();
            $table->char('country_code', 3)->nullable();
            $table->string('jolpica_race_name');
            $table->date('date');
            $table->time('time')->nullable();
            $table->string('wikipedia_url')->nullable();
            $table->timestamps();

            $table->unique(['season_id', 'round_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rounds');
    }
};
