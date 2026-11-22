<?php

/*
|--------------------------------------------------------------------------
| Bare "FALSE START" messages the stewards ruled out of position
|--------------------------------------------------------------------------
|
| In 2024 race control worded both a jump start (moving before the start
| signal, Article 48.1 a) and a car out of position on the grid (Article
| 48.1 c) as "FALSE START", with nothing after it. From 2025 the second
| carries "- OUT OF POSITION" and routes to the starting procedure by its
| text. For the bare 2024 messages only the stewards' decision document
| tells them apart, so this list names the ones ruled out of position.
| Every other bare "FALSE START" stays a jump start (Itchy Feet).
|
| A listed entry makes the driver's "FALSE START" messages in that session
| count as a starting procedure offence. It is part of the catalogue
| version, so an edit makes oof:achievements --stale re-run.
|
| Keyed by season, round number, session type and Jolpica driver ID.
| The FIA decision document is named per entry.
|
*/

return [

    'rulings' => [
        [
            'season' => 2024, 'round' => 20, 'session' => 'race', 'driver' => 'perez',
            'document' => 'FIA Document 42, 27 October 2024, "2024 Mexico City Grand Prix - Infringement - Car 11 - False Start": Article 48.1 c), front tyres ahead of the grid box',
        ],
        [
            'season' => 2024, 'round' => 22, 'session' => 'race', 'driver' => 'piastri',
            'document' => 'FIA Document 36, 23 November 2024, "2024 Las Vegas Grand Prix - Infringement - Car 81 - Incorrect starting position": Article 48.1 c)',
        ],
    ],

];
