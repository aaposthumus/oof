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
        Schema::create('qualifying_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_entry_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('position')->nullable();
            $table->unsignedInteger('q1_millis')->nullable();
            $table->unsignedInteger('q2_millis')->nullable();
            $table->unsignedInteger('q3_millis')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('qualifying_results');
    }
};
