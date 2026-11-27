# OOF.ONE track dioramas

Renders a tilted paper diorama of each circuit, drawn in code from the real track
outline, for slide 3 of the round preview. Made once per circuit, reused every year.

Output: `resources/images/origami/dioramas/<file>.png` (1080 × 1350, the carousel size).

## How it works

1. `prep.mjs` reads the outline from `outlines/` and works out everything else:
   the paper island, kerbs on tight corners (from the curvature), a bridge where
   the track crosses itself, tree positions, and optional water and a landmark islet.
   Result: `build/<circuitId>.json`.
2. `page.html` is a three.js page that builds the 3D paper model from that JSON.
   Open it via `--preview` to turn it around in a browser.
3. `render.mjs` runs `prep.mjs` when needed, opens the page in headless Chromium (render mode: no buttons, fixed
   angle, camera fitted to the card) and saves the PNG.

## Setup (once)

Node only (no Python). In Sail:

    cd tools/diorama
    npm install && npx playwright install chromium

The geometry (island, buffers, crossings) uses JSTS, the JavaScript port of the JTS geometry engine.

## Use

    node render.mjs villeneuve              # one circuit (Jolpica circuitId)
    node render.mjs --all                   # every circuit in circuits.json
    node render.mjs --all --fresh           # also re-run prep.mjs
    node render.mjs --preview suzuka villeneuve   # build/preview.html to look at

## Per-circuit settings: circuits.json

Keys are Jolpica circuitIds. Fields:
- `outline`: the bacinger file name in `outlines/` (required)
- `file`: output name, kebab-case like the backdrops (required)
- `water`: true to float the island in paper water (Montreal)
- `landmark`: a landmark model built in `page.html` (so far: `biosphere`); placed on its own islet
- `yaw`: optional fixed turn in degrees for the render; without it the angle that fills the frame best is chosen

## Credits

Track outlines: bacinger/f1-circuits, MIT licence (see `outlines/LICENSE-bacinger-f1-circuits.md`).
A credit line for it should appear somewhere public (e.g. the website's credits page).

## Still to do

- Landmarks for the other circuits (Suzuka: the Ferris wheel)
- Sectors from FastF1 (two numbers per circuit: how far round the lap sectors 1 and 2 end)
- Optional turntable clip for a video slide

## Production

Not needed on the server. Dioramas are rendered once per circuit on a dev machine and
committed as PNGs, like the backdrops; the server only serves the files.
