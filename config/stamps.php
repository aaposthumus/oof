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
    | and scaled so its bounding box covers the same share of the window for
    | every image, so wide, tall and square art carry the same visual weight
    | (decided by the owner on 22 November 2026), centred, on white. The
    | minimum margin wins over the share. The sources are never changed; the
    | framed copies are cached.
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

    // The share of the window's area the object's bounding box covers: what a square object got with a 15% margin (560 x 560 of 800 x 1000).
    'area_share' => 0.392,

    // The object never comes closer than this to any edge, as a share of the window's width or height.
    'min_margin' => 0.06,

    // A pixel with every channel at or above this counts as background.
    'white_threshold' => 245,

    // A row or column holds content only with at least this many non-background pixels, so stray specks are ignored.
    'min_content_pixels' => 3,

];
