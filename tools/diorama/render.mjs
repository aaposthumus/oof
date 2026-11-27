// Renders track dioramas to PNG with a headless browser.
// Usage:
//   node render.mjs villeneuve            -> ../../resources/images/origami/dioramas/villeneuve.png
//   node render.mjs --all                 -> every circuit in circuits.json
//   node render.mjs --all --out=<folder>  -> write the PNGs to another folder (relative to where you run it)
//   node render.mjs --preview suzuka villeneuve  -> build/preview.html to look at in a browser
// The circuit data comes from prep.mjs: render.mjs calls it when build/<circuitId>.json is missing or --fresh is given.
import { readFileSync, writeFileSync, existsSync, mkdirSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { chromium } from 'playwright';
import { prep } from './prep.mjs';

const HERE = dirname(fileURLToPath(import.meta.url));
const DEFAULT_OUT = resolve(HERE, '../../resources/images/origami/dioramas');
const THREE = pathToFileURL(join(HERE, 'node_modules/three/build/three.min.js')).href;
const cfg = JSON.parse(readFileSync(join(HERE, 'circuits.json'), 'utf8'));
const W = 1080, H = 1350;   // carousel slide size (4:5)

const args = process.argv.slice(2);
const preview = args.includes('--preview');
// A mistyped --out must never fall back to the default folder and overwrite the real files.
const outArg = args.find(a => a === '--out' || a.startsWith('--out='));
if (outArg === '--out' || outArg === '--out=') { console.error('Use --out=<folder>.'); process.exit(1); }
const OUT = outArg ? resolve(outArg.slice('--out='.length)) : DEFAULT_OUT;
let keys = args.filter(a => !a.startsWith('--'));
if (args.includes('--all')) keys = Object.keys(cfg).filter(k => !k.startsWith('_'));
if (!keys.length) { console.error('Give one or more circuitIds, or --all.'); process.exit(1); }

mkdirSync(join(HERE, 'build'), { recursive: true });
const data = keys.map(k => {
  if (!cfg[k]) throw new Error(`Unknown circuitId "${k}" (not in circuits.json)`);
  const f = join(HERE, 'build', `${k}.json`);
  if (!existsSync(f) || args.includes('--fresh')) return prep(k);
  return JSON.parse(readFileSync(f, 'utf8'));
});
const page = readFileSync(join(HERE, 'page.html'), 'utf8').replace('__THREE__', THREE);
const html = d => page.replace('__DATA__', JSON.stringify(d));

if (preview) {
  const f = join(HERE, 'build', 'preview.html');
  writeFileSync(f, html(data));
  console.log('Open in a browser:', f);
  process.exit(0);
}

mkdirSync(OUT, { recursive: true });
const browser = await chromium.launch({
  executablePath: process.env.CHROME_PATH || undefined,
  args: ['--use-gl=angle', '--enable-unsafe-swiftshader', '--ignore-gpu-blocklist'],
});
const tab = await browser.newPage({ viewport: { width: W, height: H }, deviceScaleFactor: 1 });
tab.on('pageerror', e => console.error('page error:', e.message));
for (const d of data) {
  const f = join(HERE, 'build', `${d.key}.html`);
  writeFileSync(f, html([d]));
  await tab.goto(pathToFileURL(f).href + '#render');
  await tab.waitForFunction(() => window.DIORAMA_READY === true, null, { timeout: 30000 });
  const out = join(OUT, `${cfg[d.key].file}.png`);
  await tab.locator('#view').screenshot({ path: out });
  console.log('wrote', out);
}
await browser.close();
