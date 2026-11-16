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
    | late stewards' decision can still change them. Practice and qualifying
    | wait briefly, so their awards are final by Saturday evening; 24 hours
    | for the race lets race posts go out the day after. Pending awards can
    | be confirmed by hand.
    |
    */

    'final_after_hours' => [
        'practice_1' => 2,
        'practice_2' => 2,
        'practice_3' => 2,
        'sprint_qualifying' => 2,
        'qualifying' => 2,
        'sprint' => 6,
        'race' => 24,
    ],

    /*
    |--------------------------------------------------------------------------
    | Points
    |--------------------------------------------------------------------------
    |
    | OOFscore points per award. An achievement on a scoring shelf earns its
    | tier's points, unless it is listed under overrides: Nothing to See Here
    | is a slide feature ("The quiet ones"), not an OOF. Other shelves earn
    | nothing.
    |
    */

    'points' => [
        'tiers' => [
            'bronze' => 10,
            'silver' => 25,
            'gold' => 50,
            'platinum' => 100,
        ],
        'overrides' => [
            'nothing_to_see_here' => 0,
        ],
        'scoring_shelves' => ['oof'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Review
    |--------------------------------------------------------------------------
    |
    | Sessions before this date will never be posted as current race weekends,
    | so awards in them that would wait for review are suppressed with the
    | reason "Historic, not reviewed" instead. "Hand back to engine" in the CMS
    | undoes it for one award, and later runs leave it handed back.
    |
    */

    'review_from' => '2027-01-01',

    /*
    |--------------------------------------------------------------------------
    | Thresholds
    |--------------------------------------------------------------------------
    |
    | blue_flags: blue flags shown to one car in a race for Blue Carpet.
    | took_the_lead_laps: laps the safety car ran in one race.
    | second_helping: safety car deployments in one race. pit_stop_millis:
    | stationary time for Table for One. whisker_millis: the gap to the
    | cut-off for By a Whisker. scenic_route_grid: the grid slot a win must
    | come from for Took the Scenic Route. practice_deleted_laps: the fewest
    | deleted laps for Practice Makes Imperfect. Set from the 2023–2026 test run.
    |
    */

    'blue_flags' => 8,

    'took_the_lead_laps' => 8,

    'second_helping' => 2,

    'pit_stop_millis' => 5_000,

    'whisker_millis' => 15,

    'scenic_route_grid' => 10,

    'practice_deleted_laps' => 3,

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
    | Heavy crashes
    |--------------------------------------------------------------------------
    |
    | A driver who retires with a red flag, the medical car or a stopped car
    | not cleared for this many seconds has had a heavy crash. Their OOF
    | awards for the session wait for review.
    |
    */

    'heavy_crash_stopped_seconds' => 180,

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
