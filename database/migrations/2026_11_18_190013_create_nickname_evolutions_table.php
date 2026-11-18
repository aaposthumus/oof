<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Evolutions of a nickname to its planned next stage: proposed by the
 * engine after a breakthrough, approved by a person with an off-season
 * effective date, applied on that date. One per target nickname, so re-runs
 * and backfills never propose twice.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('nickname_evolutions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_nickname_id')->constrained('driver_nicknames')->cascadeOnDelete();
            $table->foreignId('to_nickname_id')->unique()->constrained('driver_nicknames')->cascadeOnDelete();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trigger_award_id')->nullable()->constrained('achievement_awards')->nullOnDelete();
            $table->string('reason', 32);
            $table->json('evidence');
            $table->string('status', 16)->default('proposed');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->date('effective_on')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'effective_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nickname_evolutions');
    }
};
