<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * With multipliers such as ×0.5 an OOFscore can hold halves.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('oofscore_standings', function (Blueprint $table) {
            $table->decimal('points', 8, 2)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('oofscore_standings', function (Blueprint $table) {
            $table->unsignedInteger('points')->change();
        });
    }
};
