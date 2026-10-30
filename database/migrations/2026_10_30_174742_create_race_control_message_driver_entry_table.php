<?php

use App\Normalizers\OpenF1\Support\SessionEntryLinker;
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
        Schema::create('race_control_message_driver_entry', function (Blueprint $table) {
            $table->id();
            $table->foreignId('race_control_message_id')->constrained(indexName: 'rcm_driver_entry_message_foreign')->cascadeOnDelete();
            $table->foreignId('driver_entry_id')->constrained(indexName: 'rcm_driver_entry_entry_foreign')->cascadeOnDelete();
            $table->unsignedSmallInteger('car_number');

            $table->unique(['race_control_message_id', 'driver_entry_id'], 'rcm_driver_entry_unique');
            $table->index('driver_entry_id');
        });

        app(SessionEntryLinker::class)->linkMessageEntries();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('race_control_message_driver_entry');
    }
};
