<?php

/*
|--------------------------------------------------------------------------
| Curated Clean Sweeps before the lap data
|--------------------------------------------------------------------------
|
| Clean Sweep (pole, win, fastest lap and every lap led) is computed from
| Jolpica's lap times from 1996. Earlier ones come from this list: the
| published Grand Chelems (Wikipedia, "Grand Slam (Formula One)", read
| 19 November 2026), each checked against our data: the driver won and
| started from pole (qualifying P1, else grid 1). The fastest lap and the
| laps led cannot be checked before 1996 and are taken from the source.
|
| Nothing from the list is awarded until the owner has reviewed it and set
| approved to true (Phase 11b, decision Q5); then run oof:achievements for
| the seasons of 1950 to 1995 again.
|
| Keyed by season, round number and Jolpica driver ID.
|
*/

return [

    'approved' => false,

    'before' => 1996,

    'races' => [
        ['season' => 1950, 'round' => 2, 'driver' => 'fangio'], // Monaco Grand Prix
        ['season' => 1952, 'round' => 4, 'driver' => 'ascari'], // French Grand Prix
        ['season' => 1952, 'round' => 6, 'driver' => 'ascari'], // German Grand Prix
        ['season' => 1952, 'round' => 7, 'driver' => 'ascari'], // Dutch Grand Prix
        ['season' => 1953, 'round' => 1, 'driver' => 'ascari'], // Argentine Grand Prix
        ['season' => 1953, 'round' => 6, 'driver' => 'ascari'], // British Grand Prix
        ['season' => 1956, 'round' => 7, 'driver' => 'fangio'], // German Grand Prix
        ['season' => 1958, 'round' => 6, 'driver' => 'hawthorn'], // French Grand Prix
        ['season' => 1959, 'round' => 7, 'driver' => 'moss'], // Portuguese Grand Prix
        ['season' => 1960, 'round' => 5, 'driver' => 'jack_brabham'], // Belgian Grand Prix
        ['season' => 1962, 'round' => 5, 'driver' => 'clark'], // British Grand Prix
        ['season' => 1963, 'round' => 3, 'driver' => 'clark'], // Dutch Grand Prix
        ['season' => 1963, 'round' => 4, 'driver' => 'clark'], // French Grand Prix
        ['season' => 1963, 'round' => 9, 'driver' => 'clark'], // Mexican Grand Prix
        ['season' => 1964, 'round' => 5, 'driver' => 'clark'], // British Grand Prix
        ['season' => 1965, 'round' => 1, 'driver' => 'clark'], // South African Grand Prix
        ['season' => 1965, 'round' => 4, 'driver' => 'clark'], // French Grand Prix
        ['season' => 1965, 'round' => 7, 'driver' => 'clark'], // German Grand Prix
        ['season' => 1966, 'round' => 4, 'driver' => 'jack_brabham'], // British Grand Prix
        ['season' => 1969, 'round' => 5, 'driver' => 'stewart'], // French Grand Prix
        ['season' => 1971, 'round' => 3, 'driver' => 'stewart'], // Monaco Grand Prix
        ['season' => 1971, 'round' => 5, 'driver' => 'stewart'], // French Grand Prix
        ['season' => 1971, 'round' => 8, 'driver' => 'siffert'], // Austrian Grand Prix
        ['season' => 1972, 'round' => 8, 'driver' => 'ickx'], // German Grand Prix
        ['season' => 1972, 'round' => 12, 'driver' => 'stewart'], // United States Grand Prix
        ['season' => 1976, 'round' => 3, 'driver' => 'regazzoni'], // United States Grand Prix West
        ['season' => 1976, 'round' => 5, 'driver' => 'lauda'], // Belgian Grand Prix
        ['season' => 1979, 'round' => 2, 'driver' => 'laffite'], // Brazilian Grand Prix
        ['season' => 1979, 'round' => 4, 'driver' => 'gilles_villeneuve'], // United States Grand Prix West
        ['season' => 1980, 'round' => 4, 'driver' => 'piquet'], // United States Grand Prix West
        ['season' => 1981, 'round' => 3, 'driver' => 'piquet'], // Argentine Grand Prix
        ['season' => 1984, 'round' => 7, 'driver' => 'piquet'], // Canadian Grand Prix
        ['season' => 1985, 'round' => 2, 'driver' => 'senna'], // Portuguese Grand Prix
        ['season' => 1987, 'round' => 16, 'driver' => 'berger'], // Australian Grand Prix
        ['season' => 1989, 'round' => 14, 'driver' => 'senna'], // Spanish Grand Prix
        ['season' => 1990, 'round' => 4, 'driver' => 'senna'], // Monaco Grand Prix
        ['season' => 1990, 'round' => 12, 'driver' => 'senna'], // Italian Grand Prix
        ['season' => 1991, 'round' => 8, 'driver' => 'mansell'], // British Grand Prix
        ['season' => 1992, 'round' => 1, 'driver' => 'mansell'], // South African Grand Prix
        ['season' => 1992, 'round' => 4, 'driver' => 'mansell'], // Spanish Grand Prix
        ['season' => 1992, 'round' => 9, 'driver' => 'mansell'], // British Grand Prix
        ['season' => 1994, 'round' => 4, 'driver' => 'michael_schumacher'], // Monaco Grand Prix
        ['season' => 1994, 'round' => 6, 'driver' => 'michael_schumacher'], // Canadian Grand Prix
        ['season' => 1995, 'round' => 10, 'driver' => 'damon_hill'], // Hungarian Grand Prix
    ],

];
