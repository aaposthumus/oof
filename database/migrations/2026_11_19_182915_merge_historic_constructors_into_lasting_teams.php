<?php

use App\Models\TeamLineage;
use App\Normalizers\Jolpica\Support\TeamLineageSync;
use Illuminate\Database\Migrations\Migration;

/**
 * Phase 11a (owner's decisions of 19 November 2026): Jolpica's chassis-engine
 * constructors of about 1959 to 1975 join their chassis maker (Lotus-Climax
 * into Team Lotus and so on), a team listed under another ID joins its
 * lasting team (Simca into Gordini, Frank Williams' first team into Wolf,
 * Embassy Hill's Lola, RAM's March, Larrousse's Lola, Scuderia Italia's
 * Lola), and two IDs used by unrelated teams are split by seasons (Alfa
 * Romeo, ATS). Imported season teams are re-pointed at once. On a fresh
 * database the lineage's create migration has seeded the rules already,
 * without their fixed names, which are filled in here.
 */
return new class extends Migration
{
    /**
     * Written out, so a later change to the seeder never changes this migration.
     *
     * @var list<array{constructor: string, team: string, from?: int, to?: int, name?: string, note?: string}>
     */
    private const RULES = [
        ['constructor' => 'march', 'to' => 1980, 'team' => 'march'],
        ['constructor' => 'march', 'from' => 1983, 'team' => 'march'],

        ['constructor' => 'team_lotus', 'team' => 'team_lotus'],
        ['constructor' => 'lotus-climax', 'team' => 'team_lotus', 'note' => 'Jolpica names Lotus cars by chassis and engine from 1961 to 1971, works and private ones alike.'],
        ['constructor' => 'lotus-brm', 'team' => 'team_lotus'],
        ['constructor' => 'lotus-ford', 'team' => 'team_lotus'],
        ['constructor' => 'lotus-maserati', 'team' => 'team_lotus'],
        ['constructor' => 'lotus-borgward', 'team' => 'team_lotus'],
        ['constructor' => 'lotus-pw', 'team' => 'team_lotus', 'note' => 'The 1971 turbine car (Pratt & Whitney).'],

        ['constructor' => 'cooper', 'team' => 'cooper', 'name' => 'Cooper'],
        ['constructor' => 'cooper-climax', 'team' => 'cooper', 'note' => 'Jolpica names Cooper cars by chassis and engine from 1959 to 1969, works and private ones alike.'],
        ['constructor' => 'cooper-maserati', 'team' => 'cooper'],
        ['constructor' => 'cooper-brm', 'team' => 'cooper'],
        ['constructor' => 'cooper-borgward', 'team' => 'cooper'],
        ['constructor' => 'cooper-osca', 'team' => 'cooper'],
        ['constructor' => 'cooper-castellotti', 'team' => 'cooper'],
        ['constructor' => 'cooper-alfa_romeo', 'team' => 'cooper'],
        ['constructor' => 'cooper-ferrari', 'team' => 'cooper'],
        ['constructor' => 'cooper-ats', 'team' => 'cooper'],

        ['constructor' => 'brabham', 'team' => 'brabham'],
        ['constructor' => 'brabham-climax', 'team' => 'brabham', 'note' => 'Jolpica names Brabham cars by chassis and engine from 1964 to 1969 and in 1976 and 1979.'],
        ['constructor' => 'brabham-repco', 'team' => 'brabham'],
        ['constructor' => 'brabham-brm', 'team' => 'brabham'],
        ['constructor' => 'brabham-ford', 'team' => 'brabham'],
        ['constructor' => 'brabham-alfa_romeo', 'team' => 'brabham'],

        ['constructor' => 'mclaren', 'team' => 'mclaren'],
        ['constructor' => 'mclaren-ford', 'team' => 'mclaren', 'note' => 'Jolpica names McLaren cars by chassis and engine from 1966 to 1970.'],
        ['constructor' => 'mclaren-brm', 'team' => 'mclaren'],
        ['constructor' => 'mclaren-seren', 'team' => 'mclaren'],
        ['constructor' => 'mclaren-alfa_romeo', 'team' => 'mclaren'],

        ['constructor' => 'march-ford', 'team' => 'march', 'note' => 'Jolpica names March cars by chassis and engine in 1971.'],
        ['constructor' => 'march-alfa_romeo', 'team' => 'march'],

        ['constructor' => 'eagle-climax', 'team' => 'eagle', 'name' => 'Eagle', 'note' => 'Dan Gurney\'s Anglo American Racers, 1966 to 1969.'],
        ['constructor' => 'eagle-weslake', 'team' => 'eagle'],

        ['constructor' => 'brm', 'team' => 'brm'],
        ['constructor' => 'brm-ford', 'team' => 'brm'],

        ['constructor' => 'shadow', 'team' => 'shadow'],
        ['constructor' => 'shadow-ford', 'team' => 'shadow', 'note' => 'Jolpica names Shadow cars by engine in 1975.'],
        ['constructor' => 'shadow-matra', 'team' => 'shadow'],

        ['constructor' => 'lds', 'team' => 'lds'],
        ['constructor' => 'lds-climax', 'team' => 'lds'],
        ['constructor' => 'lds-alfa_romeo', 'team' => 'lds'],

        ['constructor' => 'tomaso', 'team' => 'tomaso'],
        ['constructor' => 'de_tomaso-alfa_romeo', 'team' => 'tomaso'],
        ['constructor' => 'de_tomaso-osca', 'team' => 'tomaso'],

        ['constructor' => 'matra', 'team' => 'matra'],
        ['constructor' => 'matra-ford', 'team' => 'matra', 'note' => 'Matra chassis run by Ken Tyrrell\'s team in 1968 and 1969; kept with Matra, as in the record books (owner, 19 November 2026).'],

        ['constructor' => 'gordini', 'team' => 'gordini'],
        ['constructor' => 'simca', 'team' => 'gordini', 'note' => 'Amédée Gordini\'s team raced as Simca-Gordini while Simca backed it.'],

        ['constructor' => 'wolf', 'team' => 'wolf'],
        ['constructor' => 'politoys', 'team' => 'wolf', 'note' => 'Frank Williams Racing Cars, bought by Walter Wolf in 1976. Today\'s Williams starts in 1977.'],
        ['constructor' => 'iso_marlboro', 'team' => 'wolf'],
        ['constructor' => 'williams', 'to' => 1976, 'team' => 'wolf', 'note' => 'Frank Williams Racing Cars (1975) and Wolf-Williams (1976).'],
        ['constructor' => 'williams', 'from' => 1977, 'team' => 'williams'],

        ['constructor' => 'hill', 'team' => 'hill', 'name' => 'Embassy Hill'],
        ['constructor' => 'lola', 'from' => 1974, 'to' => 1975, 'team' => 'hill', 'note' => 'Embassy Hill\'s Lola cars, before its own.'],

        ['constructor' => 'ram', 'team' => 'ram'],
        ['constructor' => 'march', 'from' => 1981, 'to' => 1982, 'team' => 'ram', 'note' => 'RAM ran the March Grand Prix team in 1981 and 1982.'],

        ['constructor' => 'larrousse', 'team' => 'larrousse'],
        ['constructor' => 'lola', 'from' => 1991, 'to' => 1991, 'team' => 'larrousse', 'note' => 'Jolpica lists Larrousse\'s 1991 cars under Lola as well.'],

        ['constructor' => 'dallara', 'team' => 'scuderia_italia', 'name' => 'Scuderia Italia', 'note' => 'BMS Scuderia Italia, with Dallara chassis to 1992 and Lola in 1993.'],
        ['constructor' => 'lola', 'from' => 1993, 'to' => 1993, 'team' => 'scuderia_italia'],

        ['constructor' => 'alfa', 'to' => 1951, 'team' => 'alfa_1950s', 'note' => 'The 1950-1951 works team.'],
        ['constructor' => 'alfa', 'from' => 1963, 'to' => 1965, 'team' => 'alfa_special', 'name' => 'Alfa Special', 'note' => 'Peter de Klerk\'s home-built Alfa Special.'],
        ['constructor' => 'alfa', 'from' => 1979, 'to' => 1985, 'team' => 'alfa', 'note' => 'The 1979-1985 works team.'],

        ['constructor' => 'ats', 'to' => 1963, 'team' => 'ats_1963', 'note' => 'The Italian ATS of 1963, not the German team of 1978-1984.'],
        ['constructor' => 'ats', 'from' => 1977, 'team' => 'ats'],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // March 1981-1982 is RAM now, so the open March rule gives way to two ranges.
        TeamLineage::query()
            ->where('jolpica_constructor_id', 'march')->whereNull('from_year')->whereNull('to_year')->where('team_slug', 'march')
            ->delete();

        foreach (self::RULES as $rule) {
            $lineage = TeamLineage::firstOrCreate(
                ['jolpica_constructor_id' => $rule['constructor'], 'from_year' => $rule['from'] ?? null, 'to_year' => $rule['to'] ?? null],
                ['team_slug' => $rule['team'], 'team_name' => $rule['name'] ?? null, 'note' => $rule['note'] ?? null],
            );

            if (isset($rule['name']) && $lineage->team_name === null && $lineage->team_slug === $rule['team']) {
                $lineage->update(['team_name' => $rule['name']]);
            }
        }

        app(TeamLineageSync::class)->run();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (self::RULES as $rule) {
            TeamLineage::query()
                ->where('jolpica_constructor_id', $rule['constructor'])
                ->where('from_year', $rule['from'] ?? null)
                ->where('to_year', $rule['to'] ?? null)
                ->where('team_slug', $rule['team'])
                ->delete();
        }

        TeamLineage::firstOrCreate(
            ['jolpica_constructor_id' => 'march', 'from_year' => null, 'to_year' => null],
            ['team_slug' => 'march'],
        );

        app(TeamLineageSync::class)->run();
    }
};
