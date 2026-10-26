<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Grand Prix names
    |--------------------------------------------------------------------------
    |
    | The official name of a round mapped to its short name and the country the
    | name refers to. That country can differ from where the round was held
    | (the Luxembourg Grand Prix was held in Germany), so it is never taken
    | from the circuit. A null country means the name refers to no country.
    |
    */

    'grand_prix_names' => [
        '70th Anniversary Grand Prix' => ['short_name' => '70th Anniversary', 'country_name' => null, 'country_code' => null],
        'Abu Dhabi Grand Prix' => ['short_name' => 'Abu Dhabi', 'country_name' => 'United Arab Emirates', 'country_code' => 'ARE'],
        'Argentine Grand Prix' => ['short_name' => 'Argentina', 'country_name' => 'Argentina', 'country_code' => 'ARG'],
        'Australian Grand Prix' => ['short_name' => 'Australia', 'country_name' => 'Australia', 'country_code' => 'AUS'],
        'Austrian Grand Prix' => ['short_name' => 'Austria', 'country_name' => 'Austria', 'country_code' => 'AUT'],
        'Azerbaijan Grand Prix' => ['short_name' => 'Azerbaijan', 'country_name' => 'Azerbaijan', 'country_code' => 'AZE'],
        'Bahrain Grand Prix' => ['short_name' => 'Bahrain', 'country_name' => 'Bahrain', 'country_code' => 'BHR'],
        'Barcelona Grand Prix' => ['short_name' => 'Barcelona', 'country_name' => 'Spain', 'country_code' => 'ESP'],
        'Belgian Grand Prix' => ['short_name' => 'Belgium', 'country_name' => 'Belgium', 'country_code' => 'BEL'],
        'Brazilian Grand Prix' => ['short_name' => 'Brazil', 'country_name' => 'Brazil', 'country_code' => 'BRA'],
        'British Grand Prix' => ['short_name' => 'Great Britain', 'country_name' => 'United Kingdom', 'country_code' => 'GBR'],
        'Caesars Palace Grand Prix' => ['short_name' => 'Caesars Palace', 'country_name' => 'United States', 'country_code' => 'USA'],
        'Canadian Grand Prix' => ['short_name' => 'Canada', 'country_name' => 'Canada', 'country_code' => 'CAN'],
        'Chinese Grand Prix' => ['short_name' => 'China', 'country_name' => 'China', 'country_code' => 'CHN'],
        'Dallas Grand Prix' => ['short_name' => 'Dallas', 'country_name' => 'United States', 'country_code' => 'USA'],
        'Detroit Grand Prix' => ['short_name' => 'Detroit', 'country_name' => 'United States', 'country_code' => 'USA'],
        'Dutch Grand Prix' => ['short_name' => 'Netherlands', 'country_name' => 'Netherlands', 'country_code' => 'NLD'],
        'Eifel Grand Prix' => ['short_name' => 'Eifel', 'country_name' => 'Germany', 'country_code' => 'DEU'],
        'Emilia Romagna Grand Prix' => ['short_name' => 'Emilia Romagna', 'country_name' => 'Italy', 'country_code' => 'ITA'],
        'European Grand Prix' => ['short_name' => 'Europe', 'country_name' => null, 'country_code' => null],
        'French Grand Prix' => ['short_name' => 'France', 'country_name' => 'France', 'country_code' => 'FRA'],
        'German Grand Prix' => ['short_name' => 'Germany', 'country_name' => 'Germany', 'country_code' => 'DEU'],
        'Hungarian Grand Prix' => ['short_name' => 'Hungary', 'country_name' => 'Hungary', 'country_code' => 'HUN'],
        'Indian Grand Prix' => ['short_name' => 'India', 'country_name' => 'India', 'country_code' => 'IND'],
        'Indianapolis 500' => ['short_name' => 'Indianapolis', 'country_name' => 'United States', 'country_code' => 'USA'],
        'Italian Grand Prix' => ['short_name' => 'Italy', 'country_name' => 'Italy', 'country_code' => 'ITA'],
        'Japanese Grand Prix' => ['short_name' => 'Japan', 'country_name' => 'Japan', 'country_code' => 'JPN'],
        'Korean Grand Prix' => ['short_name' => 'Korea', 'country_name' => 'South Korea', 'country_code' => 'KOR'],
        'Las Vegas Grand Prix' => ['short_name' => 'Las Vegas', 'country_name' => 'United States', 'country_code' => 'USA'],
        'Luxembourg Grand Prix' => ['short_name' => 'Luxembourg', 'country_name' => 'Luxembourg', 'country_code' => 'LUX'],
        'Malaysian Grand Prix' => ['short_name' => 'Malaysia', 'country_name' => 'Malaysia', 'country_code' => 'MYS'],
        'Mexican Grand Prix' => ['short_name' => 'Mexico', 'country_name' => 'Mexico', 'country_code' => 'MEX'],
        'Mexico City Grand Prix' => ['short_name' => 'Mexico City', 'country_name' => 'Mexico', 'country_code' => 'MEX'],
        'Miami Grand Prix' => ['short_name' => 'Miami', 'country_name' => 'United States', 'country_code' => 'USA'],
        'Monaco Grand Prix' => ['short_name' => 'Monaco', 'country_name' => 'Monaco', 'country_code' => 'MCO'],
        'Moroccan Grand Prix' => ['short_name' => 'Morocco', 'country_name' => 'Morocco', 'country_code' => 'MAR'],
        'Pacific Grand Prix' => ['short_name' => 'Pacific', 'country_name' => null, 'country_code' => null],
        'Pescara Grand Prix' => ['short_name' => 'Pescara', 'country_name' => 'Italy', 'country_code' => 'ITA'],
        'Portuguese Grand Prix' => ['short_name' => 'Portugal', 'country_name' => 'Portugal', 'country_code' => 'PRT'],
        'Qatar Grand Prix' => ['short_name' => 'Qatar', 'country_name' => 'Qatar', 'country_code' => 'QAT'],
        'Russian Grand Prix' => ['short_name' => 'Russia', 'country_name' => 'Russia', 'country_code' => 'RUS'],
        'Sakhir Grand Prix' => ['short_name' => 'Sakhir', 'country_name' => 'Bahrain', 'country_code' => 'BHR'],
        'San Marino Grand Prix' => ['short_name' => 'San Marino', 'country_name' => 'San Marino', 'country_code' => 'SMR'],
        'São Paulo Grand Prix' => ['short_name' => 'São Paulo', 'country_name' => 'Brazil', 'country_code' => 'BRA'],
        'Saudi Arabian Grand Prix' => ['short_name' => 'Saudi Arabia', 'country_name' => 'Saudi Arabia', 'country_code' => 'SAU'],
        'Singapore Grand Prix' => ['short_name' => 'Singapore', 'country_name' => 'Singapore', 'country_code' => 'SGP'],
        'South African Grand Prix' => ['short_name' => 'South Africa', 'country_name' => 'South Africa', 'country_code' => 'ZAF'],
        'Spanish Grand Prix' => ['short_name' => 'Spain', 'country_name' => 'Spain', 'country_code' => 'ESP'],
        'Styrian Grand Prix' => ['short_name' => 'Styria', 'country_name' => 'Austria', 'country_code' => 'AUT'],
        'Swedish Grand Prix' => ['short_name' => 'Sweden', 'country_name' => 'Sweden', 'country_code' => 'SWE'],
        'Swiss Grand Prix' => ['short_name' => 'Switzerland', 'country_name' => 'Switzerland', 'country_code' => 'CHE'],
        'Turkish Grand Prix' => ['short_name' => 'Turkey', 'country_name' => 'Turkey', 'country_code' => 'TUR'],
        'Tuscan Grand Prix' => ['short_name' => 'Tuscany', 'country_name' => 'Italy', 'country_code' => 'ITA'],
        'United States Grand Prix' => ['short_name' => 'United States', 'country_name' => 'United States', 'country_code' => 'USA'],
        'United States Grand Prix West' => ['short_name' => 'US West', 'country_name' => 'United States', 'country_code' => 'USA'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Round overrides
    |--------------------------------------------------------------------------
    |
    | Per-round corrections keyed by "year-round", for names the source gets
    | wrong or the map above cannot handle. Each entry holds name, short_name,
    | country_name and country_code.
    |
    */

    'round_overrides' => [
        // '2026-16' => ['name' => 'Bahrain Grand Prix', 'short_name' => 'Bahrain', 'country_name' => 'Bahrain', 'country_code' => 'BHR'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Team lineage
    |--------------------------------------------------------------------------
    |
    | Maps Jolpica constructor IDs to the lasting team they belong to. "from"
    | and "to" limit a rule to a range of seasons, because Jolpica reuses some
    | IDs (alfa was both the 1950s works team and Sauber in 2019-2023). The
    | first matching rule wins; an unmapped constructor becomes its own team,
    | which must not reuse the slug of a lineage team.
    |
    */

    'team_lineage' => [
        ['constructor' => 'sauber', 'team' => 'audi'],
        ['constructor' => 'bmw_sauber', 'team' => 'audi'],
        ['constructor' => 'alfa', 'from' => 2019, 'to' => 2023, 'team' => 'audi'],
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
        ['constructor' => 'aston_martin', 'to' => 1960, 'team' => 'aston_martin_1950s'],

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
        ['constructor' => 'mercedes', 'to' => 1955, 'team' => 'mercedes_1950s'],
    ],

];
