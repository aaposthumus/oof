<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A driver's nicknames, one row per stage. Everything else (awards, edition
 * subtitles, evolutions) refers to a row or to the driver, never to the
 * text, so a correction in the CMS shows everywhere at once. An evolution
 * is a new stage (status planned) that becomes active on its effective date.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('driver_nicknames', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained()->cascadeOnDelete();
            $table->string('nickname', 64);
            $table->string('motto')->nullable();
            $table->unsignedTinyInteger('stage')->default(1);
            $table->foreignId('evolves_from_id')->nullable()->constrained('driver_nicknames')->nullOnDelete();
            $table->string('status', 16)->default('active');
            $table->boolean('is_draft')->default(true);
            $table->date('active_from')->nullable();
            $table->date('active_to')->nullable();
            $table->json('evolution_trigger')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['driver_id', 'stage']);
            $table->index(['driver_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_nicknames');
    }
};
