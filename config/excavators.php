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
    | Fewest starters
    |--------------------------------------------------------------------------
    |
    | A race is a hit when its starters are at most max_ratio of the season's
    | median field, or at most max_starters cars. Score: 100 × (1 − ratio).
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
    | and at least min_starters cars started. Score: 100 × (1 − ratio).
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
    | last. Score: 100 × that relative position.
    |
    */

    'grid_to_win_climb' => [
        'min_grid' => 10,
        'min_relative' => 0.4,
    ],

];
