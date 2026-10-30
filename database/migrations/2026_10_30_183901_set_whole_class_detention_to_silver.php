<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Whole Class Detention is Silver now that red flags in practice and
 * qualifying need a clear car. The seeder never overwrites the catalogue, so
 * the existing row is changed here.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('achievements')->where('key', 'whole_class_detention')->update(['tier' => 'silver', 'points' => 25, 'updated_at' => now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('achievements')->where('key', 'whole_class_detention')->update(['tier' => 'bronze', 'points' => 10, 'updated_at' => now()]);
    }
};
