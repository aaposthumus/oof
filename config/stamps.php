<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Stamp art framing
    |--------------------------------------------------------------------------
    |
    | Where the achievement art lives (one folder per shelf: glory, oof,
    | safety-car; an Oofie by its side), and how StampArt frames it for the
    | stamp window: trimmed to its visible content (anything not near-white)
    | and scaled to sit centred with an even empty margin on all sides, on
    | white. The sources are never changed; the framed copies are cached.
    |
    | Backgrounds are not made transparent yet (decision 8 of Phase 12). When
    | that comes, it must flood fill from the image edges, never apply a
    | global near-white threshold, so cream and white parts inside the
    | characters survive.
    |
    | Kept apart from config/achievements.php, so the catalogue version does
    | not change with it.
    |
    */

    'art_path' => resource_path('images/origami/achievements'),

    'cache_path' => storage_path('app/private/stamps/framed'),

    // The stamp window is slightly portrait, about 4:5 (OOF! Concept, 11 November 2026).
    'window' => ['width' => 800, 'height' => 1000],

    // The empty margin on every side, as a share of the window.
    'margin' => 0.15,

    // A pixel with every channel at or above this counts as background.
    'white_threshold' => 245,

    // A row or column holds content only with at least this many non-background pixels, so stray specks are ignored.
    'min_content_pixels' => 3,

];
