<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Seasons
    |--------------------------------------------------------------------------
    |
    | Achievements need OpenF1's race control, laps and pit data, which start
    | in 2023. Earlier seasons are never run.
    |
    */

    'first_season' => 2023,

    /*
    |--------------------------------------------------------------------------
    | Finality
    |--------------------------------------------------------------------------
    |
    | Awards stay pending until this many hours after the session ended, so a
    | late stewards' decision can still change them. 24 hours lets race posts
    | go out the day after; pending awards can be confirmed by hand.
    |
    */

    'final_after_hours' => 24,

    /*
    |--------------------------------------------------------------------------
    | Thresholds
    |--------------------------------------------------------------------------
    |
    | blue_flags: blue flags shown to one car in a race for Mirror, Signal,
    | Manoeuvre. took_the_lead_laps: laps the safety car ran in one race.
    | called_in_again: safety car deployments in one race. pit_stop_millis:
    | stationary time for Table for One. whisker_millis: the gap to the
    | cut-off for By a Whisker. scenic_route_grid: the grid slot a win must
    | come from for Took the Scenic Route.
    |
    */

    'blue_flags' => 3,

    'took_the_lead_laps' => 10,

    'called_in_again' => 2,

    'pit_stop_millis' => 5_000,

    'whisker_millis' => 50,

    'scenic_route_grid' => 10,

    /*
    |--------------------------------------------------------------------------
    | Causes
    |--------------------------------------------------------------------------
    |
    | A red flag or safety car is linked to the single-car incident, stopped
    | car or off-track message in this many seconds before it. Without one,
    | the case goes to the CMS.
    |
    */

    'cause_window_seconds' => 90,

    /*
    |--------------------------------------------------------------------------
    | Surprises
    |--------------------------------------------------------------------------
    |
    | A win or pole is a surprise when the team stands outside the top
    | top_teams of the constructors' championship before the round (the
    | previous season's final standings in the first early_season_rounds),
    | or the driver has not started in the top top_grid earlier that season.
    |
    */

    'surprise' => [
        'top_teams' => 4,
        'top_grid' => 5,
        'early_season_rounds' => 4,
    ],

    /*
    |--------------------------------------------------------------------------
    | Championship points
    |--------------------------------------------------------------------------
    |
    | The most a driver can still score per race and per sprint, for Maths Is
    | Over. Fastest-lap points (2019–2024) are counted in the race maximum.
    |
    */

    'max_points' => [
        'race' => ['default' => 26, 2025 => 25, 2026 => 25],
        'sprint' => ['default' => 8],
    ],

];
