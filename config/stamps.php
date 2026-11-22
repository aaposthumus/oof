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

    // One synthetic shadow, the same for every stamp, in place of the art's own floor shadows (removed by the object masks): a soft ellipse under the object, behind it.
    'shadow' => [

        'enabled' => true,

        // The ellipse follows the width of the object's visible pixels in this bottom share of its box (its footprint); 1 uses the whole bounding box.
        'footprint' => 0.12,

        // The ellipse's width as a multiple of the footprint's width, and its height as a share of its own width.
        'width' => 1.15,
        'height' => 0.14,

        // How far the centre sits below the object's bottom edge, as a share of the ellipse's half height.
        'drop' => 0.0,

        // The share of the radius over which the edge fades out (blur), and the opacity at the centre.
        'blur' => 0.7,
        'opacity' => 0.28,

        'colour' => [20, 24, 32],

    ],

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
    | hole, a gap inside a cage) are removed by a rule with a keep-list
    | (decided by the owner on 22 November 2026), and reported by
    | oof:stamps:preview, each with a seed point.
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

        // Enclosed background regions are reported, and can be removed, from this many pixels; smaller ones are always kept.
        'enclosed_min_pixels' => 400,

        // An enclosed region is removed when its mean distance from the background colour is at most this; above it, it is a light surface and kept.
        'remove_enclosed_up_to' => 6,

        // Enclosed regions never to remove, whatever their mean distance: white surfaces, not background. Per art file relative to art_path, by a point inside each.
        'keep_enclosed' => [
        ],

        // Object masks from an AI segmentation model (rembg), made beforehand by bin/stamp-masks.py into path/<model>/<sha1 of the source>.png. Where a mask exists, every pixel outside it becomes transparent too (floor shadows, light rims). The mask's value (0 to 255) gives opacity 0 up to transparent_below and 1 from opaque_from. No model: no masks.
        'mask' => [
            'model' => 'birefnet-general',
            'path' => storage_path('app/private/stamps/masks'),
            'transparent_below' => 32,
            'opaque_from' => 128,

            // Per art file (relative to art_path), where the model cut off part of the art (decided by the owner on 22 November 2026): 'mask' => false uses the flood fill only; 'mask_keep' lists boxes [left, top, right, bottom] in source pixels inside which the mask removes nothing.
            'files' => [
                'glory/charging-rhino.png' => ['mask_keep' => [ // dust clouds behind the rhino, in 20 px columns down to their lowest pixel + 1
                    [60, 730, 79, 939],
                    [80, 730, 99, 955],
                    [100, 730, 119, 991],
                    [120, 730, 139, 1004],
                    [140, 730, 159, 1009],
                    [160, 730, 179, 1018],
                    [180, 730, 199, 1029],
                    [200, 730, 219, 1030],
                    [220, 730, 239, 1031],
                    [240, 730, 259, 1033],
                    [260, 730, 279, 1034],
                    [280, 730, 299, 1037],
                    [300, 730, 319, 1039],
                    [320, 730, 339, 1040],
                    [340, 730, 359, 1041],
                    [360, 730, 379, 1042],
                    [380, 730, 399, 1043],
                    [400, 730, 419, 1044],
                    [420, 730, 439, 1044],
                ]],
                'glory/encore.png' => ['mask_keep' => [ // the curtain, from the top of the image down to the stage's top edge (+ 3 px), in 20 px columns
                    [20, 0, 39, 984],
                    [40, 0, 59, 1037],
                    [60, 0, 79, 1064],
                    [80, 0, 99, 1054],
                    [100, 0, 119, 1046],
                    [120, 0, 139, 1045],
                    [140, 0, 159, 1042],
                    [160, 0, 179, 1034],
                    [180, 0, 199, 1032],
                    [200, 0, 219, 1100],
                    [220, 0, 239, 1016],
                    [240, 0, 259, 1100],
                    [260, 0, 279, 1011],
                    [280, 0, 299, 1008],
                    [300, 0, 319, 1007],
                    [320, 0, 339, 1005],
                    [340, 0, 359, 1004],
                    [360, 0, 379, 1002],
                    [380, 0, 399, 1002],
                    [400, 0, 419, 1009],
                    [420, 0, 439, 1002],
                    [440, 0, 459, 1000],
                    [460, 0, 479, 1029],
                    [480, 0, 499, 1058],
                    [500, 0, 519, 1100],
                    [520, 0, 539, 1045],
                    [540, 0, 559, 1046],
                    [560, 0, 579, 1100],
                    [580, 0, 599, 1046],
                    [600, 0, 619, 1047],
                    [620, 0, 639, 1055],
                    [640, 0, 659, 1053],
                    [660, 0, 679, 1056],
                    [680, 0, 699, 999],
                    [700, 0, 719, 999],
                    [720, 0, 739, 1000],
                    [740, 0, 759, 1001],
                    [760, 0, 779, 1002],
                    [780, 0, 799, 1003],
                    [800, 0, 819, 1005],
                    [820, 0, 839, 1006],
                    [840, 0, 859, 1008],
                    [860, 0, 879, 1011],
                    [880, 0, 899, 1079],
                    [900, 0, 919, 1080],
                    [920, 0, 939, 1066],
                    [940, 0, 959, 1029],
                    [960, 0, 979, 1043],
                    [980, 0, 999, 1045],
                    [1000, 0, 1019, 1051],
                    [1020, 0, 1039, 1056],
                    [1040, 0, 1059, 1067],
                    [1060, 0, 1079, 1018],
                    [1080, 0, 1099, 1011],
                ]],
                'oof/holding-the-door.png' => ['mask_keep' => [ // right door-frame post and its base
                    [935, 395, 1048, 1019],
                    [926, 1020, 1065, 1139],
                    [946, 1140, 1065, 1149],
                ]],
                'oof/outside-the-lines.png' => ['mask_keep' => [ // the drawing paper with the orange line, in 20 px rows out to its slanted right edge + 2
                    [52, 920, 997, 939],
                    [52, 940, 1001, 959],
                    [52, 960, 1005, 979],
                    [52, 980, 1009, 999],
                    [52, 1000, 1012, 1019],
                    [52, 1020, 1016, 1039],
                    [52, 1040, 1020, 1059],
                    [52, 1060, 1025, 1079],
                    [52, 1080, 1028, 1099],
                    [52, 1100, 1033, 1119],
                    [52, 1120, 1037, 1139],
                    [52, 1140, 1042, 1159],
                    [52, 1160, 1047, 1179],
                    [52, 1180, 1051, 1199],
                    [52, 1200, 1057, 1219],
                    [52, 1220, 1061, 1239],
                    [52, 1240, 1064, 1259],
                    [52, 1260, 1064, 1261],
                ]],
            ],
        ],

    ],

];
