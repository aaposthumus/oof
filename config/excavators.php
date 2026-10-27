<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Excluded rounds
    |--------------------------------------------------------------------------
    |
    | Rounds no excavator looks at, by round name. The Indianapolis 500 counted
    | for the championship from 1950 to 1960, but with its own 33-car field of
    | drivers who never raced the other rounds, so its numbers mislead.
    |
    */

    'excluded_round_names' => ['Indianapolis 500'],

    /*
    |--------------------------------------------------------------------------
    | Statuses
    |--------------------------------------------------------------------------
    |
    | Disqualified statuses mark a disqualification whatever the position text
    | says (since 2023 it can be numeric). Skipped statuses are about injury,
    | illness or a fatal accident: no excavator counts, scores or shows them.
    |
    */

    'disqualified_statuses' => ['Disqualified', 'Excluded', 'Underweight'],

    'skipped_statuses' => ['Fatal accident', 'Injury', 'Injured', 'Eye injury', 'Illness', 'Driver unwell', 'Physical'],

    /*
    |--------------------------------------------------------------------------
    | Fewest starters
    |--------------------------------------------------------------------------
    |
    | A race is a hit when its starters are at most max_ratio of the season's
    | median field, or at most max_starters cars. Raw score: 100 × (1 − ratio).
    |
    */

    'fewest_starters' => [
        'max_ratio' => 0.75,
        'max_starters' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Fewest finishers
    |--------------------------------------------------------------------------
    |
    | Counts cars running at the flag. A race is a hit when at most max_ratio
    | of its starters were running at the end, or at most max_running cars,
    | and at least min_starters cars started. Raw score: 100 × (1 − ratio).
    |
    */

    'fewest_finishers' => [
        'max_ratio' => 0.30,
        'max_running' => 5,
        'min_starters' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Grid to win climb
    |--------------------------------------------------------------------------
    |
    | A winner is a hit when they started at least min_grid and at least
    | min_relative of the way down the field (0 = pole, 1 = last), so a 5th
    | place start in a 6-car race is no climb. A pit-lane start counts as
    | last. Raw score: 100 × that relative position.
    |
    */

    'grid_to_win_climb' => [
        'min_grid' => 10,
        'min_relative' => 0.4,
    ],

    /*
    |--------------------------------------------------------------------------
    | Surprise winner
    |--------------------------------------------------------------------------
    |
    | A winner is a hit when their team stood at least min_position and at
    | least min_relative of the way down the constructors' standings before
    | the race (0 = leader, 1 = last), or when it was their only career win.
    | Constructors' standings exist from 1958; before the first round the
    | team's final position of the season before counts. Raw score:
    | 100 × relative position, plus only_win_bonus for an only career win.
    |
    */

    'surprise_winner' => [
        'min_position' => 5,
        'min_relative' => 0.4,
        'only_win_bonus' => 40,
    ],

    /*
    |--------------------------------------------------------------------------
    | First-time winner
    |--------------------------------------------------------------------------
    |
    | Every driver's first win. Raw score: one_hit_score when it stayed their
    | only win, otherwise first_win_score.
    |
    */

    'first_time_winner' => [
        'first_win_score' => 50,
        'one_hit_score' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | Droughts ended
    |--------------------------------------------------------------------------
    |
    | A driver's first win, or first podium, after at least min_starts starts.
    | Raw score: the number of starts before it.
    |
    */

    'win_drought_ended' => [
        'min_starts' => 50,
    ],

    'podium_drought_ended' => [
        'min_starts' => 50,
    ],

    /*
    |--------------------------------------------------------------------------
    | Retirements
    |--------------------------------------------------------------------------
    |
    | Most retirements: races where at least min_retirements starters retired
    | (skipped statuses not counted). Raw score: the number of retirements.
    |
    | Curious retirements: every starter whose status is on the list. Raw
    | score: how rare the status is, 100 × (1 − its count / (most common + 1)).
    | A skipped status on the list is ignored.
    |
    */

    'most_retirements' => [
        'min_retirements' => 15,
    ],

    'curious_retirement' => [
        'statuses' => [
            'Out of fuel', 'Fuel', 'Wheel', 'Wheel nut', 'Wheel rim', 'Seat', 'Driver Seat',
            'Safety belt', 'Stalled', 'Launch control', 'Debris', 'Refuelling', 'Fuel rig',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Disqualification
    |--------------------------------------------------------------------------
    |
    | Races with disqualified drivers. The road position of a disqualified
    | driver is only known when the data still has their time; otherwise a
    | driver who completed the winner's laps counts as "full distance". Raw
    | score of the best disqualified driver: road_winner_score, then
    | road_podium_score, full_distance_score or other_score, plus
    | extra_driver_score for each further disqualified driver.
    |
    */

    'disqualification' => [
        'road_winner_score' => 100,
        'road_podium_score' => 80,
        'full_distance_score' => 60,
        'other_score' => 30,
        'extra_driver_score' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Shortest race
    |--------------------------------------------------------------------------
    |
    | The data has no scheduled distance, so it is estimated as the median
    | winner's laps at the same circuit within window_years, without the race
    | itself. A race is a hit when the winner completed at most max_ratio of
    | that, or when half points were awarded (winner and runner-up both got
    | between half_points_min and half_points_max of the season's usual
    | points). Raw score: 100 × (1 − ratio); half_points_score without an
    | estimate.
    |
    | scheduled_laps overrides the estimate for a round ("year|round name")
    | where the circuit's layout differed from its other seasons. Such rounds
    | are not used to estimate their neighbours either.
    |
    */

    'shortest_race' => [
        'max_ratio' => 0.75,
        'window_years' => 5,
        'half_points_min' => 0.4,
        'half_points_max' => 0.6,
        'half_points_score' => 50,
        'scheduled_laps' => [
            // Monza with the banking: 10 km laps, 500 km (1961: 430 km) instead of 5.7 km laps.
            '1955|Italian Grand Prix' => 50,
            '1956|Italian Grand Prix' => 50,
            '1961|Italian Grand Prix' => 43,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Dominant win
    |--------------------------------------------------------------------------
    |
    | A win is a hit when the runner-up was lapped, when at least
    | min_lapped_share of the classified rivals were lapped, or when the
    | runner-up finished at least min_gap_share of the race time behind. Raw
    | score: lapped_score for a lapped runner-up plus extra_lap_score per
    | further lap, otherwise lapped_score × the larger of the lapped share
    | and the gap share relative to twice min_gap_share.
    |
    */

    'dominant_win' => [
        'min_lapped_share' => 0.9,
        'min_gap_share' => 0.02,
        'lapped_score' => 70,
        'extra_lap_score' => 15,
    ],

    /*
    |--------------------------------------------------------------------------
    | Closest finish
    |--------------------------------------------------------------------------
    |
    | Wins by at most max_gap_ms over the runner-up on the same lap. Staged
    | and safety-car finishes cannot be told apart without lap data. Raw
    | score: 100 × (1 − gap / max_gap_ms).
    |
    */

    'closest_finish' => [
        'max_gap_ms' => 500,
    ],

    /*
    |--------------------------------------------------------------------------
    | Title decider
    |--------------------------------------------------------------------------
    |
    | The final round of a completed season is a hit when the leader before
    | it did not become champion, when the title was still open before it, or
    | when the champion won by at most max_margin_wins × the points for a
    | win. Before 1991 dropped scores make "still open" an estimate. Raw
    | score: lead_change_score (also for drivers level on points before the
    | final round), open_score or close_score, plus up to margin_score for a
    | small final margin; drivers level on points always get all of it.
    |
    */

    'title_decider' => [
        'max_margin_wins' => 0.5,
        'lead_change_score' => 80,
        'open_score' => 60,
        'close_score' => 40,
        'margin_score' => 20,
        'dropped_scores_until' => 1990,
    ],

    /*
    |--------------------------------------------------------------------------
    | Age record
    |--------------------------------------------------------------------------
    |
    | The top drivers youngest at their first and oldest at their last win,
    | pole and podium. Pole is qualifying position 1 where qualifying data
    | exists, otherwise grid 1, but never on a shared car (both drivers get
    | the car's grid). Raw score: 100 × (top − rank + 1) / top, the best of
    | the records set in that race.
    |
    */

    'age_record' => [
        'top' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Start milestone
    |--------------------------------------------------------------------------
    |
    | The race in which a driver made a milestone start (starts are rounds
    | started, withdrawals not counted). Raw score: the milestone's score,
    | plus first_to_reach_bonus for the first driver ever to reach it.
    |
    */

    'start_milestone' => [
        'milestones' => [100 => 40, 200 => 70, 300 => 90, 400 => 100],
        'first_to_reach_bonus' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Slowest pit stop
    |--------------------------------------------------------------------------
    |
    | Pit stop durations are pit-lane time (entry to exit), from 2011. A stop
    | is a hit when it took at least min_seconds and min_ratio × the race's
    | typical stop (its lower quartile). Left out: red flags (at least
    | red_flag_min_stops long stops within one lap of each other), stops over
    | max_seconds (repairs in the garage) and stops after which the driver
    | retired within retire_within_laps laps. Raw score: the ratio.
    |
    */

    'slowest_pit_stop' => [
        'min_seconds' => 40,
        'max_seconds' => 180,
        'min_ratio' => 2.0,
        'red_flag_min_stops' => 5,
        'retire_within_laps' => 2,
    ],

];
