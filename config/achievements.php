<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Seasons
    |--------------------------------------------------------------------------
    |
    | The engine runs from 1950. Each achievement still waits for the first
    | year its data supports (OpenF1's race control, laps and pit data start
    | in 2023; see oof:achievements:catalogue).
    |
    | shelf_from: the first season a shelf counts. Earlier OOF and Safety Car
    | awards are stored for OOF Classics and This week in OOF history, but
    | score 0, stay out of the OOFscore, the Oofies, Close season and the stamp
    | books, and are never published automatically. Glory counts from 1950.
    |
    | live_from: the first season the hourly run (--changed) starts by
    | itself. Earlier seasons are backfilled by hand, season by season; once
    | run, --changed and --stale keep them up to date.
    |
    | None of these are part of the catalogue version: they choose seasons and
    | points, not what the engine finds.
    |
    */

    'first_season' => 1950,

    'shelf_from' => [
        'oof' => 2023,
        'safety_car' => 2023,
        'oofies' => 2023,
        'glory' => 1950,
    ],

    'live_from' => 2023,

    /*
    |--------------------------------------------------------------------------
    | Engine version
    |--------------------------------------------------------------------------
    |
    | Part of the catalogue version in the run log. Raise it when a code
    | change alters what the engine finds, so oof:achievements --stale
    | re-runs every context decided before it.
    |
    */

    'engine_version' => 2,

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
    | In the OOFscore, pace and reliability achievements are multiplied by
    | the band of the team's form rank before the round (see TeamForm): top
    | is ranks 1 to bands.top, middle up to bands.middle, rest below that and
    | a team without a race in the window. Driver and surprise achievements
    | count ×1. The award keeps its tier and base points.
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
        'multipliers' => [
            'bands' => ['top' => 4, 'middle' => 7],
            'pace' => ['top' => 2.0, 'middle' => 1.0, 'rest' => 0.5],
            'reliability' => ['top' => 1.0, 'middle' => 1.0, 'rest' => 0.5],
        ],
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
    | Domain events
    |--------------------------------------------------------------------------
    |
    | Events such as OofScoreStandingsUpdated are dispatched only for seasons
    | from this year, so a rebuild or backfill of older seasons is quiet.
    |
    */

    'events_from' => 2027,

    /*
    |--------------------------------------------------------------------------
    | Sensitive weekends
    |--------------------------------------------------------------------------
    |
    | From propose_from, a race or sprint in which a car retired with strong
    | heavy-crash signals (a red flag with the medical car, or a red-flag
    | stoppage of at least long_stoppage_minutes) makes the engine propose a
    | sensitivity for the round. It acts as sensitive until a person confirms
    | or dismisses it. Earlier weekends rely on the seeded list.
    |
    */

    'sensitivity' => [
        'propose_from' => '2027-01-01',
        'long_stoppage_minutes' => 20,
    ],

    /*
    |--------------------------------------------------------------------------
    | Surprises
    |--------------------------------------------------------------------------
    |
    | A win or pole is a surprise when the lasting team stands outside the
    | top top_teams on form: the points its cars scored in the last
    | window_races Grands Prix before the round (across season boundaries,
    | no sprints, no Indianapolis 500), divided by the window's races it
    | entered. A team with no race in the window counts as outside the top.
    |
    */

    'surprise' => [
        'window_races' => 10,
        'top_teams' => 4,
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
