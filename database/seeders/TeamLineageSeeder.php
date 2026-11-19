<?php

namespace Database\Seeders;

use App\Models\TeamLineage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * The lasting team behind each renamed Jolpica constructor. A lasting team
 * takes the slug of its latest name, or of its fixed name where a rule sets
 * one. Create-only: a rule edited in the CMS is kept.
 * Run with: php artisan db:seed --class=TeamLineageSeeder
 */
class TeamLineageSeeder extends Seeder
{
    /**
     * @var list<array{constructor: string, team: string, from?: int, to?: int, name?: string, note?: string}>
     */
    public const RULES = [
        ['constructor' => 'sauber', 'team' => 'audi'],
        ['constructor' => 'bmw_sauber', 'team' => 'audi'],
        ['constructor' => 'alfa', 'from' => 2019, 'to' => 2023, 'team' => 'audi', 'note' => 'Sauber raced as Alfa Romeo; the 1950s and 1979-1985 Alfa Romeo teams are their own.'],
        ['constructor' => 'audi', 'team' => 'audi'],

        ['constructor' => 'minardi', 'team' => 'racing_bulls'],
        ['constructor' => 'toro_rosso', 'team' => 'racing_bulls'],
        ['constructor' => 'alphatauri', 'team' => 'racing_bulls'],
        ['constructor' => 'rb', 'team' => 'racing_bulls'],

        ['constructor' => 'stewart', 'team' => 'red_bull'],
        ['constructor' => 'jaguar', 'team' => 'red_bull'],
        ['constructor' => 'red_bull', 'team' => 'red_bull'],

        ['constructor' => 'jordan', 'team' => 'aston_martin'],
        ['constructor' => 'mf1', 'team' => 'aston_martin'],
        ['constructor' => 'spyker_mf1', 'team' => 'aston_martin'],
        ['constructor' => 'spyker', 'team' => 'aston_martin'],
        ['constructor' => 'force_india', 'team' => 'aston_martin'],
        ['constructor' => 'racing_point', 'team' => 'aston_martin'],
        ['constructor' => 'aston_martin', 'from' => 2021, 'team' => 'aston_martin'],
        ['constructor' => 'aston_martin', 'to' => 1960, 'team' => 'aston_martin_1950s', 'note' => 'The 1959-1960 works team, not today\'s.'],

        ['constructor' => 'toleman', 'team' => 'alpine'],
        ['constructor' => 'benetton', 'team' => 'alpine'],
        ['constructor' => 'renault', 'from' => 2002, 'to' => 2011, 'team' => 'alpine'],
        ['constructor' => 'lotus_f1', 'team' => 'alpine'],
        ['constructor' => 'renault', 'from' => 2016, 'to' => 2020, 'team' => 'alpine'],
        ['constructor' => 'alpine', 'team' => 'alpine'],

        ['constructor' => 'tyrrell', 'team' => 'mercedes'],
        ['constructor' => 'bar', 'team' => 'mercedes'],
        ['constructor' => 'honda', 'from' => 2006, 'to' => 2008, 'team' => 'mercedes'],
        ['constructor' => 'brawn', 'team' => 'mercedes'],
        ['constructor' => 'mercedes', 'from' => 2010, 'team' => 'mercedes'],
        ['constructor' => 'mercedes', 'to' => 1955, 'team' => 'mercedes_1950s', 'note' => 'The 1954-1955 works team, not today\'s.'],

        ['constructor' => 'arrows', 'team' => 'arrows'],
        ['constructor' => 'footwork', 'team' => 'arrows', 'note' => 'Arrows raced as Footwork from 1991 to 1996.'],

        ['constructor' => 'ligier', 'team' => 'prost', 'note' => 'Alain Prost bought Ligier for 1997.'],
        ['constructor' => 'prost', 'team' => 'prost'],

        ['constructor' => 'march', 'to' => 1980, 'team' => 'march'],
        ['constructor' => 'march', 'from' => 1983, 'team' => 'march'],
        ['constructor' => 'leyton', 'team' => 'march', 'note' => 'March raced as Leyton House in 1990 and 1991.'],

        ['constructor' => 'osella', 'team' => 'fondmetal', 'note' => 'Osella was renamed Fondmetal for 1991.'],
        ['constructor' => 'fondmetal', 'team' => 'fondmetal'],

        ['constructor' => 'lotus_racing', 'team' => 'caterham', 'note' => 'Lotus Racing (2010) and Team Lotus (2011) became Caterham for 2012.'],
        ['constructor' => 'caterham', 'team' => 'caterham'],

        ['constructor' => 'coloni', 'team' => 'moda', 'note' => 'Andrea Moda bought Coloni for 1992.'],
        ['constructor' => 'moda', 'team' => 'moda'],

        ['constructor' => 'ensign', 'team' => 'theodore', 'note' => 'Ensign merged into Theodore for 1983; from then on Theodore carries Ensign\'s history.'],
        ['constructor' => 'theodore', 'from' => 1983, 'team' => 'theodore'],
        ['constructor' => 'theodore', 'to' => 1982, 'team' => 'theodore_1978', 'note' => 'Theodore\'s own entries of 1978 to 1982, separate from Ensign, which raced in the same seasons.'],

        ['constructor' => 'onyx', 'team' => 'monteverdi', 'note' => 'Peter Monteverdi took over Onyx during 1990.'],

        ['constructor' => 'virgin', 'team' => 'manor'],
        ['constructor' => 'marussia', 'team' => 'manor'],
        ['constructor' => 'manor', 'team' => 'manor'],

        ...self::CHASSIS_ENGINE_RULES,
        ...self::SAME_TEAM_RULES,
        ...self::SPLIT_RULES,
    ];

    /**
     * From about 1959 to 1975 Jolpica names cars by chassis and engine
     * (Lotus-Climax, Cooper-Maserati), works and private cars alike. Each
     * family is one chassis maker, merged so its form is one figure.
     *
     * @var list<array{constructor: string, team: string, from?: int, to?: int, name?: string, note?: string}>
     */
    public const CHASSIS_ENGINE_RULES = [
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
    ];

    /**
     * One team under another constructor ID: a rename, or a season in a
     * chassis no other team used that year.
     *
     * @var list<array{constructor: string, team: string, from?: int, to?: int, name?: string, note?: string}>
     */
    public const SAME_TEAM_RULES = [
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
    ];

    /**
     * One constructor ID used by unrelated teams, kept apart by seasons.
     *
     * @var list<array{constructor: string, team: string, from?: int, to?: int, name?: string, note?: string}>
     */
    public const SPLIT_RULES = [
        ['constructor' => 'alfa', 'to' => 1951, 'team' => 'alfa_1950s', 'note' => 'The 1950-1951 works team.'],
        ['constructor' => 'alfa', 'from' => 1963, 'to' => 1965, 'team' => 'alfa_special', 'name' => 'Alfa Special', 'note' => 'Peter de Klerk\'s home-built Alfa Special.'],
        ['constructor' => 'alfa', 'from' => 1979, 'to' => 1985, 'team' => 'alfa', 'note' => 'The 1979-1985 works team.'],

        ['constructor' => 'ats', 'to' => 1963, 'team' => 'ats_1963', 'note' => 'The Italian ATS of 1963, not the German team of 1978-1984.'],
        ['constructor' => 'ats', 'from' => 1977, 'team' => 'ats'],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // The lineage table's create migration runs this seeder before team_name exists.
        $hasNames = Schema::hasColumn('team_lineages', 'team_name');

        foreach (self::RULES as $rule) {
            TeamLineage::firstOrCreate(
                ['jolpica_constructor_id' => $rule['constructor'], 'from_year' => $rule['from'] ?? null, 'to_year' => $rule['to'] ?? null],
                ['team_slug' => $rule['team'], 'note' => $rule['note'] ?? null, ...($hasNames ? ['team_name' => $rule['name'] ?? null] : [])],
            );
        }
    }
}
