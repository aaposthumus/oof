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
        Schema::create('excavation_runs', function (Blueprint $table) {
            $table->id();
            $table->string('excavator', 64);
            $table->unsignedSmallInteger('excavator_version');
            $table->json('parameters');
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('candidates_found')->default(0);
            $table->timestamps();

            $table->index(['excavator', 'started_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('excavation_runs');
    }
};
