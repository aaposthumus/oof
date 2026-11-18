<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The run log of the achievements engine: one row per context it decided
 * (a session, a weekend, a season), with the catalogue version it ran on,
 * so oof:achievements --stale can find what was decided on an older one.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('achievement_runs', function (Blueprint $table) {
            $table->id();
            $table->string('scope');
            $table->string('context_key');
            $table->foreignId('season_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('catalogue_version', 40);
            $table->json('options')->nullable();
            $table->json('stats')->nullable();
            $table->boolean('is_dry_run')->default(false);
            $table->timestamp('started_at');
            $table->timestamp('finished_at');
            $table->timestamps();

            $table->index(['context_key', 'is_dry_run', 'finished_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('achievement_runs');
    }
};
