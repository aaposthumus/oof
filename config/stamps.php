<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Stamp art framing
    |--------------------------------------------------------------------------
    |
    | Where the achievement art lives (one folder per shelf: glory, oof,
    | safety-car; an Oofie by its side), and how StampArt frames it for the
    | stamp window: the background is made transparent first (see
    | 'background' below), then the art is trimmed to its visible content (by
    | opacity) and scaled so its bounding box covers the same share of the
    | window for every image, so wide, tall and square art carry the same
    | visual weight (decided by the owner on 22 November 2026), centred, on a
    | transparent window. The minimum margin wins over the share. The sources
    | are never changed; the transparent and framed copies are cached.
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

    // A pixel counts as visible content for the trim from this opacity (0 to 1), so the faintest tail of a shadow does not widen the box.
    'trim_min_opacity' => 0.1,

    // A row or column holds content only with at least this many visible pixels, so stray specks are ignored.
    'min_content_pixels' => 3,

    /*
    |--------------------------------------------------------------------------
    | Background removal
    |--------------------------------------------------------------------------
    |
    | App\Achievements\Stamps\BackgroundRemoval. The background colour is
    | sampled from the image's edge pixels. A flood fill from every edge
    | pixel takes in what lies within the tolerance of that colour; there is
    | no global near-white threshold, so cream and white parts inside the
    | characters survive. Inside the flooded region the background turns
    | into alpha by distance (colour to alpha). Distances are the largest
    | channel difference from the background colour, 0 to 255.
    |
    | Enclosed background regions (not connected to the edge: a doughnut
    | hole, a gap inside a cage) are kept and reported by oof:stamps:preview,
    | each with a seed point. List a seed under remove_enclosed to remove
    | that region.
    |
    */

    'background' => [

        // The flood fill takes in a pixel when every channel is within this of the background colour. Higher starts to eat light fur and paper (the bunny's toes at 28).
        'tolerance' => 16,

        // Inside the flooded region a pixel is fully transparent up to this distance; its opacity then rises to 1 at the tolerance.
        'transparent_within' => 2,

        // The exponent of that rise: above 1 the faint shading near the objects becomes a darker, more transparent shadow instead of a light haze on a dark stamp, while opacity still reaches 1 at the tolerance.
        'opacity_curve' => 2.0,

        // How many pixels on either side of the object's outline are unmixed from the background (the anti-aliasing), so no light halo remains.
        'edge_width' => 2,

        // Enclosed background regions are reported from this many pixels.
        'enclosed_min_pixels' => 400,

        // Enclosed background regions to remove, per art file relative to art_path, by a point inside each: 'glory/fastest-food.png' => [[x, y]].
        'remove_enclosed' => [
        ],

    ],

];
