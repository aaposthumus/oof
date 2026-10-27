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
        Schema::create('moment_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('excavation_run_id')->constrained()->cascadeOnDelete();
            $table->string('type', 64);
            $table->foreignId('round_id')->constrained()->cascadeOnDelete();
            // MySQL forbids cascading actions on a base column of a stored generated column.
            $table->foreignId('driver_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('season_team_id')->nullable()->constrained()->nullOnDelete();
            // NULLs never collide in a unique index, so round-level candidates get driver_key 0.
            $table->unsignedBigInteger('driver_key')->storedAs('ifnull(driver_id, 0)');
            $table->decimal('score', 5, 2);
            $table->json('evidence');
            $table->string('status', 16)->default('new');
            $table->boolean('is_sensitive')->default(false);
            $table->text('sensitivity_note')->nullable();
            $table->timestamps();

            $table->unique(['type', 'round_id', 'driver_key']);
            $table->index(['status', 'score']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('moment_candidates');
    }
};
