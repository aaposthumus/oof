<?php

namespace Database\Seeders;

use App\Models\TeamLineage;
use Illuminate\Database\Seeder;

/**
 * The lasting team behind each renamed Jolpica constructor. A lasting team
 * takes the slug of its latest name. Create-only: a rule edited in the CMS
 * is kept. Run with: php artisan db:seed --class=TeamLineageSeeder
 */
class TeamLineageSeeder extends Seeder
{
    /**
     * @var list<array{constructor: string, team: string, from?: int, to?: int, note?: string}>
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

        ['constructor' => 'march', 'team' => 'march'],
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
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::RULES as $rule) {
            TeamLineage::firstOrCreate(
                ['jolpica_constructor_id' => $rule['constructor'], 'from_year' => $rule['from'] ?? null, 'to_year' => $rule['to'] ?? null],
                ['team_slug' => $rule['team'], 'note' => $rule['note'] ?? null],
            );
        }
    }
}
