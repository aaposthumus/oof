<?php

/*
|--------------------------------------------------------------------------
| Official poles where the starting grid is ambiguous
|--------------------------------------------------------------------------
|
| Without qualifying data (every round before 1994 and most of 1996 to
| 2002) the pole is grid 1 of the race (Phase 11b, D10). Jolpica gives a
| shared car's grid slot to every driver of the car, and once gives grid 1
| to two different cars, so some rounds have several drivers on grid 1.
| Pole is earned by one driver (decided by the owner on 21 November 2026),
| so for those rounds this list names the driver the official record
| credits with pole. A round with several drivers on grid 1 that is not
| listed gives no pole at all, rather than a guess.
|
| The poles are the published pole sitters of each race. They are part of
| the catalogue version, so an edit makes oof:achievements --stale re-run.
|
| Keyed by season, round number and Jolpica driver ID.
|
*/

return [

    'rounds' => [
        ['season' => 1951, 'round' => 4, 'driver' => 'fangio'], // French Grand Prix: car shared with Fagioli
        ['season' => 1952, 'round' => 8, 'driver' => 'ascari'], // Italian Grand Prix: Moss also listed on grid 1
        ['season' => 1953, 'round' => 7, 'driver' => 'ascari'], // German Grand Prix: car shared with Villoresi
        ['season' => 1953, 'round' => 8, 'driver' => 'fangio'], // Swiss Grand Prix: car shared with Bonetto
        ['season' => 1955, 'round' => 1, 'driver' => 'gonzalez'], // Argentine Grand Prix: car shared with Farina and Trintignant
        ['season' => 1956, 'round' => 2, 'driver' => 'fangio'], // Monaco Grand Prix: pole car later driven by Castellotti
        ['season' => 1956, 'round' => 8, 'driver' => 'fangio'], // Italian Grand Prix: pole car later driven by Castellotti
        ['season' => 1957, 'round' => 5, 'driver' => 'moss'], // British Grand Prix: car shared with Brooks
        ['season' => 1964, 'round' => 9, 'driver' => 'clark'], // United States Grand Prix: Clark in two cars, one shared with Spence
    ],

];
