<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Which country a driver's nationality is at home in, for Hometown Hero
 * (Phase 12): Jolpica's nationality (a demonym, "British") against the
 * circuit's country as Jolpica names it ("UK"), so Imola is Italy and every
 * race in the USA is home for an American. Editable in the CMS
 * (Nationalities); its rows are part of the catalogue version, so a change
 * makes oof:achievements --stale re-run. Seeded with every nationality in
 * the data; countries without a circuit yet keep their name for when one
 * comes.
 */
return new class extends Migration
{
    /**
     * Nationality => [country, note].
     */
    private const ROWS = [
        'American' => ['USA', null],
        'Argentine' => ['Argentina', null],
        'Australian' => ['Australia', null],
        'Austrian' => ['Austria', null],
        'Belgian' => ['Belgium', null],
        'Brazilian' => ['Brazil', null],
        'British' => ['UK', null],
        'Canadian' => ['Canada', null],
        'Chilean' => ['Chile', null],
        'Chinese' => ['China', null],
        'Colombian' => ['Colombia', null],
        'Czech' => ['Czech Republic', null],
        'Danish' => ['Denmark', null],
        'Dutch' => ['Netherlands', null],
        'East German' => ['Germany', 'No race was held in East Germany; the German circuits count as home.'],
        'Finnish' => ['Finland', null],
        'French' => ['France', null],
        'German' => ['Germany', null],
        'Hungarian' => ['Hungary', null],
        'Indian' => ['India', null],
        'Indonesian' => ['Indonesia', null],
        'Irish' => ['Ireland', null],
        'Italian' => ['Italy', null],
        'Japanese' => ['Japan', null],
        'Liechtensteiner' => ['Liechtenstein', null],
        'Malaysian' => ['Malaysia', null],
        'Mexican' => ['Mexico', null],
        'Monegasque' => ['Monaco', null],
        'New Zealander' => ['New Zealand', null],
        'Polish' => ['Poland', null],
        'Portuguese' => ['Portugal', null],
        'Rhodesian' => ['Rhodesia', 'Today Zimbabwe; no race was held there.'],
        'Russian' => ['Russia', null],
        'South African' => ['South Africa', null],
        'Spanish' => ['Spain', null],
        'Swedish' => ['Sweden', null],
        'Swiss' => ['Switzerland', null],
        'Thai' => ['Thailand', null],
        'Uruguayan' => ['Uruguay', null],
        'Venezuelan' => ['Venezuela', null],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('nationality_countries', function (Blueprint $table) {
            $table->id();
            $table->string('nationality', 64)->unique();
            $table->string('country', 64);
            $table->string('note')->nullable();
            $table->timestamps();
        });

        $now = now();

        DB::table('nationality_countries')->insert(collect(self::ROWS)
            ->map(fn (array $row, string $nationality) => ['nationality' => $nationality, 'country' => $row[0], 'note' => $row[1], 'created_at' => $now, 'updated_at' => $now])
            ->values()
            ->all());
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nationality_countries');
    }
};
