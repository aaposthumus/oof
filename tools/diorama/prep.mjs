// Turns a circuit outline into diorama data: track, island, kerbs, bridge, trees, and optional water and a landmark islet.
// Usage as a script:  node prep.mjs <jolpica circuitId>   ->  build/<circuitId>.json
// Usage as a module:  import { prep } from './prep.mjs'; prep('suzuka')  ->  data object (also written to build/)
import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import 'jsts/org/locationtech/jts/monkey.js';
import GeometryFactory from 'jsts/org/locationtech/jts/geom/GeometryFactory.js';
import Coordinate from 'jsts/org/locationtech/jts/geom/Coordinate.js';
import BufferOp from 'jsts/org/locationtech/jts/operation/buffer/BufferOp.js';
import BufferParameters from 'jsts/org/locationtech/jts/operation/buffer/BufferParameters.js';
import DouglasPeuckerSimplifier from 'jsts/org/locationtech/jts/simplify/DouglasPeuckerSimplifier.js';

const HERE = dirname(fileURLToPath(import.meta.url));
const GF = new GeometryFactory();

// --- small helpers -------------------------------------------------------
const C = ([x, y]) => new Coordinate(x, y);
const line = pts => GF.createLineString(pts.map(C));
const point = (x, y) => GF.createPoint(new Coordinate(x, y));
function buffer(g, d) { // round joins like the prototype
  const p = new BufferParameters(); p.setQuadrantSegments(8); p.setJoinStyle(BufferParameters.JOIN_ROUND); p.setEndCapStyle(BufferParameters.CAP_ROUND);
  return BufferOp.bufferOp(g, d, p);
}
const coords = ring => ring.getCoordinates().map(c => [c.x, c.y]);
function polygons(g) { const out = []; for (let i = 0; i < g.getNumGeometries(); i++) { const p = g.getGeometryN(i); if (p.getGeometryType() === 'Polygon') out.push(p); } return out; }
function interpolate(pts, d) { // point at distance d along a polyline
  for (let i = 0; i < pts.length - 1; i++) {
    const [ax, ay] = pts[i], [bx, by] = pts[i + 1], l = Math.hypot(bx - ax, by - ay);
    if (d <= l || i === pts.length - 2) { const t = l ? Math.min(1, d / l) : 0; return [ax + (bx - ax) * t, ay + (by - ay) * t]; }
    d -= l;
  }
}
const polyLength = pts => pts.slice(1).reduce((s, p, i) => s + Math.hypot(p[0] - pts[i][0], p[1] - pts[i][1]), 0);
// seeded random numbers, so every render of a circuit is identical
function rng(seed) { let a = seed >>> 0; const next = () => { a = (a + 0x6D2B79F5) >>> 0; let t = a; t = Math.imul(t ^ (t >>> 15), t | 1); t ^= t + Math.imul(t ^ (t >>> 7), t | 61); return ((t ^ (t >>> 14)) >>> 0) / 4294967296; };
  return { uniform: (lo, hi) => lo + (hi - lo) * next(), normal: () => Math.sqrt(-2 * Math.log(next() || 1e-12)) * Math.cos(2 * Math.PI * next()), int: n => Math.floor(next() * n) }; }
function segIntersect(p, q, r, s) {
  const d = (q[0] - p[0]) * (s[1] - r[1]) - (q[1] - p[1]) * (s[0] - r[0]); if (Math.abs(d) < 1e-12) return false;
  const t = ((r[0] - p[0]) * (s[1] - r[1]) - (r[1] - p[1]) * (s[0] - r[0])) / d, u = ((r[0] - p[0]) * (q[1] - p[1]) - (r[1] - p[1]) * (q[0] - p[0])) / d;
  return t >= 0 && t <= 1 && u >= 0 && u <= 1;
}
const round4 = v => Math.round(v * 1e4) / 1e4;

export function prep(key) {
  const cfg = JSON.parse(readFileSync(join(HERE, 'circuits.json'), 'utf8'))[key];
  if (!cfg) throw new Error(`Unknown circuitId "${key}" (not in circuits.json)`);
  const f = JSON.parse(readFileSync(join(HERE, 'outlines', `${cfg.outline}.geojson`), 'utf8')).features[0];
  const g = f.geometry.coordinates;

  // project to metres (north up), close the loop
  const lat0 = g.reduce((s, p) => s + p[1], 0) / g.length, k = Math.cos(lat0 * Math.PI / 180);
  let P = g.map(([x, y]) => [x * k * 111320, -y * 111320]);
  if (Math.hypot(P[0][0] - P.at(-1)[0], P[0][1] - P.at(-1)[1]) > 1) P.push(P[0]);

  // resample every ~12 m, centre and scale to 10 units across
  const L = polyLength(P), n = Math.floor(L / 12);
  let pts = Array.from({ length: n }, (_, i) => interpolate(P, i * L / n));
  const cx = pts.reduce((s, p) => s + p[0], 0) / n, cy = pts.reduce((s, p) => s + p[1], 0) / n;
  pts = pts.map(([x, y]) => [x - cx, y - cy]);
  const xs = pts.map(p => p[0]), ys = pts.map(p => p[1]);
  const s = 10 / Math.max(Math.max(...xs) - Math.min(...xs), Math.max(...ys) - Math.min(...ys));
  pts = pts.map(([x, y]) => [x * s, y * s]);

  // crossings: non-adjacent segments that intersect -> the later pass becomes a bridge
  const cross = [];
  for (let i = 0; i < n; i++) for (let j = i + 3; j < n; j++) {
    if ((j + 1) % n === i || (i + 1) % n === j) continue;
    if (segIntersect(pts[i], pts[(i + 1) % n], pts[j], pts[(j + 1) % n])) cross.push([i, j]);
  }
  // where two non-neighbouring parts run closer than MINGAP, push them apart a little (map-maker's displacement)
  {
    const nearX = new Set(); for (const [i, j] of cross) for (let t = -20; t <= 20; t++) { nearX.add((i + t + n) % n); nearX.add((j + t + n) % n); }
    const MINGAP = 0.15, SL = 10 / n * 3; // rough step length
    for (let it = 0; it < 40; it++) {
      const push = pts.map(() => [0, 0]); let moved = false;
      for (let i = 0; i < n; i++) { if (nearX.has(i)) continue;
        for (let j = i + 1; j < n; j++) { const dl = Math.min(j - i, n - (j - i)); if (dl < 4 || nearX.has(j)) continue;
          const dx = pts[i][0] - pts[j][0], dy = pts[i][1] - pts[j][1], d = Math.hypot(dx, dy); if (dl * SL < 3 * d) continue;
          if (d < MINGAP && d > 1e-6) { const f = (MINGAP - d) / 2 / d; push[i][0] += dx * f; push[i][1] += dy * f; push[j][0] -= dx * f; push[j][1] -= dy * f; moved = true; } } }
      if (!moved) break;
      // smooth the push along the track so the line bends instead of kinking
      const sm = push.map((_, i) => { let x = 0, y = 0; for (let t = -6; t <= 6; t++) { const q = push[(i + t + n) % n]; x += q[0]; y += q[1]; } return [x / 13, y / 13]; });
      pts = pts.map((q, i) => [q[0] + sm[i][0] * 1.6, q[1] + sm[i][1] * 1.6]);
    }
  }
  const h = new Array(n).fill(0);
  for (const [, j] of cross) for (let t = -14; t <= 14; t++) { const idx = (j + t + n) % n; h[idx] = Math.max(h[idx], 0.34 * Math.pow(1 - Math.abs(t) / 15, 0.6)); }

  // road width per point: full width where there is room, narrower only where two parts of the track run close together
  const near = new Set(); for (const [i, j] of cross) for (let t = -20; t <= 20; t++) { near.add((i + t + n) % n); near.add((j + t + n) % n); }
  const stepLen = pts.reduce((s2, q, i) => s2 + Math.hypot(pts[(i + 1) % n][0] - q[0], pts[(i + 1) % n][1] - q[1]), 0) / n;
  const gap = pts.map((pi, i) => { if (near.has(i)) return Infinity; let m = Infinity;
    for (let j = 0; j < n; j++) { const dl = Math.min(Math.abs(i - j), n - Math.abs(i - j)); if (dl < 4 || near.has(j)) continue;
      const d = Math.hypot(pi[0] - pts[j][0], pi[1] - pts[j][1]); if (dl * stepLen < 3 * d) continue; if (d < m) m = d; }
    return m; });
  const seg = pts.map((q, i) => Math.hypot(pts[(i + 1) % n][0] - q[0], pts[(i + 1) % n][1] - q[1]));
  const radius = pts.map((_, i) => { const a = pts[(i - 3 + n) % n], b = pts[i], c = pts[(i + 3) % n];
    let t = Math.atan2(c[1] - b[1], c[0] - b[0]) - Math.atan2(b[1] - a[1], b[0] - a[0]); t = Math.abs(Math.atan2(Math.sin(t), Math.cos(t)));
    let len = 0; for (let k2 = -3; k2 < 3; k2++) len += seg[(i + k2 + n) % n]; return t > 1e-6 ? len / t : Infinity; });
  const raw = gap.map((g, i) => Math.max(0.035, Math.min(0.2, g * 0.4, radius[i] * 0.8)));
  const W8 = 8, minned = raw.map((_, i) => { let m = Infinity; for (let t = -W8; t <= W8; t++) m = Math.min(m, raw[(i + t + n) % n]); return m; });
  const hws = minned.map((_, i) => { let s2 = 0; for (let t = -W8; t <= W8; t++) s2 += minned[(i + t + n) % n]; return s2 / (2 * W8 + 1); });
  const hw = Math.min(...hws);

  // curvature -> kerbs on the outside of tight corners
  const ang = pts.map((_, i) => { const a = pts[(i - 2 + n) % n], b = pts[(i + 2) % n]; return Math.atan2(b[1] - a[1], b[0] - a[0]); });
  const kerb = ang.map((_, i) => { let t = ang[(i + 1) % n] - ang[(i - 1 + n) % n]; t = Math.atan2(Math.sin(t), Math.cos(t)); return Math.abs(t) > 0.2 ? Math.sign(t) : 0; });

  // island: a rounded paper shape around the track, with hand-cut wobble
  const cl = line([...pts, pts[0]]);
  const island = DouglasPeuckerSimplifier.simplify(buffer(buffer(cl, 0.75), -0.35), 0.02);
  const rw = rng(3);
  function wobble(ring, amp) {
    const r = coords(ring), len = polyLength(r), m = Math.max(8, Math.floor(len / 0.08));
    const q = Array.from({ length: m }, (_, i) => interpolate(r, i * len / m));
    const raw = Array.from({ length: m + 10 }, () => rw.normal());
    const nz = Array.from({ length: m }, (_, i) => (raw[i + 3] + raw[i + 4] + raw[i + 5] + raw[i + 6] + raw[i + 7]) / 5);
    const mx = q.reduce((a, p) => a + p[0], 0) / m, my = q.reduce((a, p) => a + p[1], 0) / m;
    const out = q.map(([x, y], i) => { const dx = x - mx, dy = y - my, dl = Math.hypot(dx, dy) + 1e-9; return [round4(x + dx / dl * nz[i] * amp), round4(y + dy / dl * nz[i] * amp)]; });
    return [...out, out[0]];
  }
  const pack = (geom, amp) => polygons(geom).filter(p => p.getArea() > 0.05).map(p => ({
    outer: wobble(p.getExteriorRing(), amp),
    holes: Array.from({ length: p.getNumInteriorRing() }, (_, i) => wobble(p.getInteriorRingN(i), amp * 0.6)),
  }));
  const isl = pack(island, 0.035);
  const inner = buffer(island, -0.16);
  const isl2 = inner.isEmpty() ? [] : pack(inner, 0.03);

  // trees: spread over the island, away from the track
  const rt = rng(7), keep = buffer(cl, 0.36), shrunk = buffer(island, -0.12), env = island.getEnvelopeInternal(), trees = [];
  for (let tries = 0; trees.length < 40 && tries < 20000; tries++) {
    const x = rt.uniform(env.getMinX(), env.getMaxX()), y = rt.uniform(env.getMinY(), env.getMaxY()), q = point(x, y);
    if (shrunk.contains(q) && !keep.contains(q) && trees.every(t => Math.hypot(t[0] - x, t[1] - y) > 0.42))
      trees.push([round4(x), round4(y), round4(rt.uniform(0.75, 1.25)), rt.int(2)]);
  }

  // optional water and a landmark on its own islet
  const extra = {};
  if (cfg.water || cfg.landmark) {
    const water = cfg.water ? polygons(buffer(island, 0.55)).map(q => GF.createPolygon(q.getExteriorRing())).reduce((a, b) => a.union(b)) : null; // filled: no gaps inside
    const e = (water || island).getEnvelopeInternal(); let best = null;
    for (let a = 0; a < 60; a++) for (let b = 0; b < 60; b++) {
      const gx = e.getMinX() - 1.2 + (e.getWidth() + 2.4) * a / 59, gy = e.getMinY() - 1.2 + (e.getHeight() + 2.4) * b / 59;
      const dd = island.distance(point(gx, gy));
      if (dd > 1.0 && dd < 1.3 && (!best || gx + gy < best[0])) best = [gx + gy, gx, gy];
    }
    const islet = buffer(point(best[1], best[2]), 0.78);
    extra.islet = pack(islet, 0.03);
    if (cfg.water) extra.water = pack(water.union(buffer(islet, 0.45)), 0.04);
    if (cfg.landmark) extra.landmark = { kind: cfg.landmark, x: round4(best[1]), y: round4(best[2]) };
  }
  if ('yaw' in cfg) extra.yaw = cfg.yaw;

  const data = { ...extra, key, short: f.properties.Location, name: f.properties.Name, length_m: f.properties.length,
    hws: hws.map(round4), pts: pts.map(p => p.map(round4)), h: h.map(v => Math.round(v * 1000) / 1000), kerb, island: isl, island2: isl2, trees };
  mkdirSync(join(HERE, 'build'), { recursive: true });
  writeFileSync(join(HERE, 'build', `${key}.json`), JSON.stringify(data));
  console.log(`${key}: ${n} points, road ${hw.toFixed(3)}-${Math.max(...hws).toFixed(3)}, ${cross.length} crossing(s), ${kerb.filter(Boolean).length} kerb points, ${trees.length} trees`);
  return data;
}

if (import.meta.url === pathToFileURL(process.argv[1]).href) {
  const keys = process.argv.slice(2); if (!keys.length) { console.error('Usage: node prep.mjs <circuitId> [...]'); process.exit(1); }
  for (const k of keys) prep(k);
}
