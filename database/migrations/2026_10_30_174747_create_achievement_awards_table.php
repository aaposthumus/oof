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
        Schema::create('achievement_awards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('race_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('achievement_id')->constrained()->cascadeOnDelete();
            $table->string('instance_key', 96);
            $table->string('event_key', 96);
            $table->string('detail')->nullable();
            $table->string('subtitle')->nullable();
            $table->string('variant_name')->nullable();
            $table->json('evidence');
            $table->string('confidence', 8);
            $table->string('status', 16);
            $table->string('review_reason', 32)->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamps();

            $table->unique(['race_session_id', 'achievement_id', 'instance_key'], 'achievement_awards_instance_unique');
            $table->index(['status', 'confidence']);
            $table->index(['driver_id', 'achievement_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('achievement_awards');
    }
};
