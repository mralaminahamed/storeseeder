import { test } from '@playwright/test';
import { join } from 'path';
import { BRAND, glassField, markSvg } from '../brand';

/**
 * Regenerates the two WordPress.org banners.
 *
 *   npm run test:e2e:banners
 *
 * The banners used to be hand-drawn PNGs with no source in the repo, so a palette
 * change meant redrawing them by eye. This composes them from markup instead:
 * one layout authored in 772x250 units, scaled up for the retina size, so the two
 * files cannot disagree and neither can drift from the icon's palette.
 *
 * Needs no WordPress — nothing here talks to a site, unlike screenshots.spec.ts.
 */
const ASSET_DIR = join(__dirname, '..', '..', '..', '.wordpress-org');

/** Base canvas. The 1544x500 banner is exactly this at 2x. */
const BASE = { width: 772, height: 250 };

const BANNERS = [
  { file: 'banner-772x250.png', scale: 1 },
  { file: 'banner-1544x500.png', scale: 2 },
] as const;

/** The product rows on the mock table. Names come from the products generator. */
const ROWS = [
  { name: 'Wireless Noise-Cancel<br>Headphones', type: 'Variable', price: '$199.00' },
  { name: 'Organic Cotton T-Shirt', type: 'Simple', price: '$29.95' },
  { name: 'Stainless Steel Water Bottle', type: 'Simple', price: '$34.50' },
  { name: 'Mechanical Keyboard Kit', type: 'Variable', price: '$142.80' },
] as const;

function banner(scale: number): string {
  const rows = ROWS.map(
    (row) => `
      <div class="row">
        <div class="name">${row.name}</div>
        <div><span class="chip ${'Variable' === row.type ? 'variable' : 'simple'}">${row.type}</span></div>
        <div class="price">${row.price}</div>
      </div>`,
  ).join('');

  return `<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
  *, *::before, *::after { box-sizing: border-box; }
  html, body { margin: 0; padding: 0; background: #fff; }
  body {
    width: ${BASE.width * scale}px;
    height: ${BASE.height * scale}px;
    overflow: hidden;
  }

  /* One layout in 772x250 units. Scaling the wrapper rather than the type keeps
     the 2x banner crisp: the transform applies before rasterisation. */
  .stage {
    width: ${BASE.width}px; height: ${BASE.height}px;
    transform: scale(${scale});
    transform-origin: top left;
    position: relative;
    overflow: hidden;
    background: ${glassField(160)};
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans,
      Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
    -webkit-font-smoothing: antialiased;
  }

  /* Rim light along the top edge — the same tell as the icon's stroked inset. */
  .stage::after {
    content: ''; position: absolute; inset: 0; pointer-events: none;
    border-top: 1px solid rgba(255, 255, 255, .34);
  }

  .copy { position: absolute; left: 42px; top: 40px; width: 350px; }

  .kicker {
    color: rgba(255, 255, 255, .72);
    font-size: 10px; font-weight: 700; letter-spacing: .22em; text-transform: uppercase;
  }

  /* The glyph alone, no tile. A 52px glass tile here reads as a smudge: its body
     stops are the same hue as the field behind it, so the silhouette dissolves
     and the white glyph shrinks inside the tile's own padding. The screenshot
     frames can carry the full tile because theirs is larger and sits against the
     lighter top of the field; at banner scale, beside a 40px wordmark, it can't. */
  .lockup { display: flex; align-items: center; gap: 10px; margin-top: 6px; }
  .lockup svg { display: block; flex-shrink: 0; filter: drop-shadow(0 3px 8px rgba(${BRAND.shadow}, .34)); }
  .lockup .word {
    color: #fff; font-size: 40px; font-weight: 800; letter-spacing: -.028em; line-height: 1;
  }

  .sub {
    margin-top: 16px;
    color: rgba(255, 255, 255, .88);
    font-size: 13.5px; line-height: 1.5; letter-spacing: -.005em;
  }

  .pills { display: flex; gap: 8px; margin-top: 18px; }
  .pill {
    border: 1px solid rgba(255, 255, 255, .42); border-radius: 999px;
    padding: 5px 11px;
    color: #fff; font-size: 9px; font-weight: 700; letter-spacing: .13em; text-transform: uppercase;
    /* A hint of the material inside the outline, so pills read as glass chips
       rather than as hairline rectangles. */
    background: rgba(255, 255, 255, .1);
  }

  /* The mock table. Bleeds off the right edge, as in the previous banner — so
     every column has to clear the cut: the price column ends at 756px, 16px
     short of the canvas, while the card itself runs 20px past it. */
  .card {
    position: absolute; left: 420px; top: 20px; width: 372px; height: 210px;
    background: #fff; border-radius: 12px; overflow: hidden;
    box-shadow: 0 18px 40px -14px rgba(${BRAND.shadow}, .5), 0 6px 14px -8px rgba(${BRAND.shadow}, .34);
  }
  .thead, .row {
    display: grid; grid-template-columns: 1fr 70px 70px; align-items: center;
    padding: 0 36px 0 16px; gap: 8px;
  }
  .thead {
    height: 30px; background: #f6f7f9; border-bottom: 1px solid #eceef1;
    color: #8a8f98; font-size: 7.5px; font-weight: 700; letter-spacing: .16em; text-transform: uppercase;
  }
  .thead div:last-child, .row .price { text-align: right; }
  .row { height: 38px; border-bottom: 1px solid #f1f2f4; }
  .name { color: #1f2329; font-size: 10.5px; line-height: 1.35; }
  .price { color: #11141a; font-size: 11.5px; font-weight: 700; }
  .chip {
    display: inline-block; border-radius: 999px; padding: 3px 7px;
    font-size: 8px; font-weight: 700;
  }
  .chip.variable { background: #e9e8fc; color: #4a47c4; }
  .chip.simple { background: #dcfce7; color: #157f45; }

  .pager { display: flex; align-items: center; gap: 6px; padding: 9px 36px 9px 16px; }
  .pager i { height: 5px; border-radius: 999px; background: #eceef1; }
  .pager i.wide { flex: 1; }
  .pager i.short { width: 46px; }
  .pager b { width: 13px; height: 13px; border-radius: 4px; background: ${BRAND.mid}; }
</style>
</head>
<body>
  <div class="stage">
    <div class="copy">
      <div class="kicker">For Fluent Cart</div>
      <div class="lockup">
        ${markSvg(46)}
        <div class="word">StoreSeeder</div>
      </div>
      <div class="sub">Realistic products, orders and customers &mdash;<br>seeded into your Fluent Cart store in seconds.</div>
      <div class="pills">
        <span class="pill">17 generators</span>
        <span class="pill">Live preview</span>
        <span class="pill">Seeded</span>
      </div>
    </div>

    <div class="card">
      <div class="thead"><div>Product</div><div>Type</div><div>Price</div></div>
      ${rows}
      <div class="pager"><i class="wide"></i><b></b><i class="short"></i><i class="short"></i></div>
    </div>
  </div>
</body>
</html>`;
}

test.describe('Banners', () => {
  for (const spec of BANNERS) {
    test(spec.file, async ({ page }) => {
      await page.setViewportSize({
        width: BASE.width * spec.scale,
        height: BASE.height * spec.scale,
      });
      await page.setContent(banner(spec.scale));
      await page.waitForTimeout(200);
      await page.screenshot({ path: join(ASSET_DIR, spec.file) });
    });
  }
});
