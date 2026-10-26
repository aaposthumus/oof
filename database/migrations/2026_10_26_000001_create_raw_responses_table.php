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
        Schema::create('raw_responses', function (Blueprint $table) {
            $table->id();
            $table->string('source', 32);
            $table->string('endpoint', 64);
            $table->string('request_key', 191)->unique();
            $table->string('url', 500);
            $table->json('parameters');
            $table->unsignedSmallInteger('season')->nullable();
            $table->unsignedTinyInteger('round')->nullable();
            $table->unsignedSmallInteger('http_status');
            $table->unsignedInteger('total_rows')->nullable();
            $table->json('payload');
            $table->timestamp('fetched_at');
            $table->timestamp('normalized_at')->nullable();
            $table->timestamps();

            $table->index(['source', 'endpoint', 'season', 'round']);
            $table->index('normalized_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('raw_responses');
    }
};
