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
        Schema::create('historic_moments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('moment_candidate_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('round_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('title');
            $table->text('story');
            $table->json('tags');
            $table->string('era', 32);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('historic_moments');
    }
};
