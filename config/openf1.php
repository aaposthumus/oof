<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OpenF1 API
    |--------------------------------------------------------------------------
    |
    | Source of what happened during sessions, from 2023 onwards. Historical
    | data needs no key. The free tier allows 3 requests per second and 30 per
    | minute and sends no rate limit headers, so the importer waits at least
    | `min_request_interval_ms` between requests.
    |
    */

    'base_url' => env('OPENF1_BASE_URL', 'https://api.openf1.org/v1'),

    'user_agent' => env('OPENF1_USER_AGENT', 'OOF/0.1'),

    'min_request_interval_ms' => (int) env('OPENF1_MIN_REQUEST_INTERVAL_MS', 2100),

    'connect_timeout' => 5,

    'timeout' => 60,

    /*
    | Waits in seconds before each retry of a throttled (429) or failed request.
    | A Retry-After header takes precedence.
    */
    'retry_backoff_seconds' => [15, 60, 120, 300],

    'first_season' => 2023,

    /*
    | A session's responses are fetched again once each of these many minutes
    | have passed since the session ended, to pick up late data and
    | corrections. They are final after the last one.
    */
    'refetch_after_minutes' => [30, 180, 1440, 4320],

    /*
    | The meetings and sessions listings of a season that is not over are
    | fetched again when older than this. OpenF1 updates them daily.
    */
    'listing_max_age_hours' => 6,

    /*
    | A season's listings are final once fetched this many days after the year.
    */
    'listing_final_after_days' => 3,

    /*
    | Endpoints fetched for every session, in order. starting_grid is fetched
    | for qualifying sessions only, as OpenF1 keys the grid by them.
    | car_data, location and intervals are deliberately left out.
    */
    'session_endpoints' => [
        'drivers', 'session_result', 'race_control', 'laps', 'stints', 'pit', 'weather', 'team_radio', 'position',
    ],

    'grid_session_names' => ['Qualifying', 'Sprint Qualifying', 'Sprint Shootout'],

    /*
    | Endpoints stored in the raw layer only, with no normalizer (yet).
    */
    'raw_only_endpoints' => ['session_result', 'starting_grid', 'position'],

    /*
    |--------------------------------------------------------------------------
    | Identity matching
    |--------------------------------------------------------------------------
    |
    | OpenF1 session names mapped to our session types.
    |
    */

    'session_types' => [
        'Practice 1' => 'practice_1',
        'Practice 2' => 'practice_2',
        'Practice 3' => 'practice_3',
        'Qualifying' => 'qualifying',
        'Sprint Qualifying' => 'sprint_qualifying',
        'Sprint Shootout' => 'sprint_qualifying',
        'Sprint' => 'sprint',
        'Race' => 'race',
    ],

    /*
    | A meeting or session matches ours when their start times are this close.
    | Jolpica dates some night races by their local date (Las Vegas).
    */
    'date_tolerance_hours' => 36,

    /*
    | OpenF1 circuit keys mapped to Jolpica circuit IDs. A meeting matches a
    | round only when the circuits agree, never by its Grand Prix name (the
    | 2026 Bahrain Grand Prix was held at Sepang).
    */
    'circuits' => [
        2 => 'silverstone',
        4 => 'hungaroring',
        6 => 'imola',
        7 => 'spa',
        9 => 'americas',
        10 => 'albert_park',
        12 => 'sepang',
        14 => 'interlagos',
        15 => 'catalunya',
        19 => 'red_bull_ring',
        22 => 'monaco',
        23 => 'villeneuve',
        39 => 'monza',
        46 => 'suzuka',
        49 => 'shanghai',
        55 => 'zandvoort',
        61 => 'marina_bay',
        63 => 'bahrain',
        65 => 'rodriguez',
        70 => 'yas_marina',
        144 => 'baku',
        149 => 'jeddah',
        150 => 'losail',
        151 => 'miami',
        152 => 'vegas',
        153 => 'madring',
    ],

    /*
    | OpenF1 team names mapped to Jolpica constructor IDs, used for practice
    | entries. "from" and "to" limit a rule to a range of seasons; the first
    | matching rule wins.
    */
    'teams' => [
        ['name' => 'Red Bull Racing', 'constructor' => 'red_bull'],
        ['name' => 'McLaren', 'constructor' => 'mclaren'],
        ['name' => 'Ferrari', 'constructor' => 'ferrari'],
        ['name' => 'Mercedes', 'constructor' => 'mercedes'],
        ['name' => 'Aston Martin', 'constructor' => 'aston_martin'],
        ['name' => 'Alpine', 'constructor' => 'alpine'],
        ['name' => 'Williams', 'constructor' => 'williams'],
        ['name' => 'Haas F1 Team', 'constructor' => 'haas'],
        ['name' => 'AlphaTauri', 'constructor' => 'alphatauri'],
        ['name' => 'RB', 'constructor' => 'rb'],
        ['name' => 'Racing Bulls', 'constructor' => 'rb'],
        ['name' => 'Alfa Romeo', 'constructor' => 'alfa'],
        ['name' => 'Kick Sauber', 'constructor' => 'sauber'],
        ['name' => 'Audi', 'constructor' => 'audi'],
        ['name' => 'Cadillac', 'constructor' => 'cadillac'],
    ],

    /*
    | Drivers matched by hand, keyed by "year:driver_number", mapped to a
    | Jolpica driver ID. Use it when the automatic match fails or is wrong.
    */
    'driver_overrides' => [
        // '2024:61' => 'doohan',
    ],

    /*
    | Credit line for the website and posts.
    */
    'attribution' => 'Data: OpenF1',

];
