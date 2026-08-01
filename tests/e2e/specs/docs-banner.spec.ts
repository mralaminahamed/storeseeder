import { test } from '@playwright/test';
import { join } from 'node:path';
import { BRAND, glassField, markSvg } from '../brand';

/**
 * Regenerates the documentation site's hero banner.
 *
 *   yarn shots:docs-banner
 *
 * Not the WordPress.org banner. That one is 1544×500 with the product name and a tagline set into the
 * artwork, because the directory renders it as a card with no text of its own beside it. On a
 * documentation hero the title and tagline are already in the page, so repeating them in the image
 * says everything twice and leaves the banner nothing to do.
 *
 * So this one carries the *idea* instead: three recipe cards becoming a filled catalogue. Wider and
 * shorter than the listing banner, because it sits full-width above a hero rather than in a fixed
 * card — and with the wordmark left out for the same reason.
 *
 * Composed from markup rather than drawn, and from the same `brand.ts` the icon and the listing
 * banners use, so a palette change reaches all of them and none can drift.
 *
 * Talks to no WordPress install.
 */

const OUT = join(__dirname, '..', '..', '..', 'docs', 'website', 'src', 'assets');

/** Authored in these units and scaled, so the 2× file is crisp rather than resampled. */
const BASE = { width: 1200, height: 360 };
const SCALE = 2;

/** The three shipped recipes, in the order the archive lists them. */
const RECIPES = [
  { name: 'Corner grocer', rows: '1,794', items: ['Organic Rolled Oats', 'Salt-Cured Basmati Rice', 'Cheddar Wedge'] },
  { name: 'Fashion boutique', rows: '3,466', items: ['Boucle Trench Coat', 'Cashmere Jumper', 'Chelsea Boots'] },
  { name: 'Home & garden', rows: '1,225', items: ['Solid Oak Dining Table', 'Rattan Armchair', 'Cast Iron Planter'] },
] as const;

function card(recipe: (typeof RECIPES)[number], index: number): string {
  const items = recipe.items
    .map((item) => `<div class="line"><span class="dot"></span>${item}</div>`)
    .join('');

  // Fanned rather than aligned: three flat cards read as a table, three at slight angles read as a
  // choice being made.
  const tilt = [-3.5, 0, 3.5][index];
  const lift = [10, 0, 10][index];

  return `
    <div class="card" style="transform: rotate(${tilt}deg) translateY(${lift}px)">
      <div class="card-head">
        <span class="tile"></span>
        <span class="card-name">${recipe.name}</span>
      </div>
      <div class="lines">${items}</div>
      <div class="card-foot"><strong>${recipe.rows}</strong> rows</div>
    </div>`;
}

function banner(): string {
  return `<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
  *, *::before, *::after { box-sizing: border-box; }
  html, body { margin: 0; padding: 0; }
  body { width: ${BASE.width * SCALE}px; height: ${BASE.height * SCALE}px; overflow: hidden; }

  .stage {
    width: ${BASE.width}px; height: ${BASE.height}px;
    transform: scale(${SCALE});
    transform-origin: top left;
    position: relative; overflow: hidden;
    background: ${glassField(150)};
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", sans-serif;
    -webkit-font-smoothing: antialiased;
    display: flex; align-items: center; justify-content: center; gap: 34px;
  }

  /* The mark, small and low-contrast: identification, not a logo lockup. The page's own header
     already carries the wordmark. */
  .mark {
    position: absolute; left: 40px; top: 34px;
    opacity: .9;
  }

  .caption {
    position: absolute; left: 40px; bottom: 34px;
    color: rgba(255, 255, 255, .78);
    font-size: 15px; font-weight: 500; letter-spacing: .01em;
  }

  .card {
    width: 236px;
    background: #fff;
    border-radius: 14px;
    padding: 16px 16px 13px;
    box-shadow:
      0 18px 44px -10px rgba(${BRAND.shadow}, .42),
      0 4px 12px -4px rgba(${BRAND.shadow}, .3);
  }

  .card-head { display: flex; align-items: center; gap: 9px; margin-bottom: 12px; }

  .tile {
    width: 26px; height: 26px; border-radius: 8px; flex: none;
    background: linear-gradient(150deg, ${BRAND.tint}, ${BRAND.mid});
  }

  .card-name { font-size: 13.5px; font-weight: 600; color: #1b1830; letter-spacing: -.01em; }

  .lines { display: flex; flex-direction: column; gap: 7px; }

  .line {
    display: flex; align-items: center; gap: 7px;
    font-size: 11.5px; color: #55506e;
  }

  .dot { width: 5px; height: 5px; border-radius: 999px; flex: none; background: ${BRAND.tint}; }

  .card-foot {
    margin-top: 13px; padding-top: 10px;
    border-top: 1px solid #eceaf5;
    font-size: 11px; color: #6f6a88;
  }

  .card-foot strong { color: ${BRAND.deep}; font-weight: 600; }
</style>
</head>
<body>
  <div class="stage">
    <span class="mark">${markSvg(38)}</span>
    ${RECIPES.map(card).join('')}
    <span class="caption">One click. Nine resources. In dependency order.</span>
  </div>
</body>
</html>`;
}

test('regenerate the documentation hero banner', async ({ page }) => {
  await page.setViewportSize({ width: BASE.width * SCALE, height: BASE.height * SCALE });
  await page.setContent(banner());
  await page.screenshot({ path: join(OUT, 'banner.png') });
});
