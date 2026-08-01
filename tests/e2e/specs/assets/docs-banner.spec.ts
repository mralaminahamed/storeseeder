import { test } from '@playwright/test';
import { join } from 'node:path';
import { BRAND, glassField, markSvg } from '../../brand';

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

const OUT = join(__dirname, '..', '..', '..', '..', 'docs', 'website', 'src', 'assets');

/**
 * Authored in these units and scaled, so the file is crisp rather than resampled.
 *
 * `SCALE` is 3 rather than 2 because the hero breaks out of the content column and displays up to
 * 1600 CSS pixels wide; at 2× that asks for 3200 device pixels and a 2400-wide source would be
 * upscaled — the exact defect that squaring the image caused before. Scaling the whole stage keeps
 * the composition identical and only adds resolution.
 */
const BASE = { width: 1200, height: 360 };
const SCALE = 3;

/**
 * Each recipe's own icon, copied verbatim from `<recipe>/icon.svg` in the storeseeder-recipes
 * archive — the same file the admin renders beside the same name.
 *
 * The cards used to carry a gradient square, which said "a recipe" three times and named none of
 * them. These are also the one place colour means something here: the hue belongs to the recipe, so
 * a reader who has seen the Recipes screen recognises the row before reading the label. Unlike the
 * page's cards, whose plates Starlight was tinting by grid position.
 *
 * `currentColor` rather than the icons' literal hex, so one declaration per card colours all four
 * strokes and the wash — see the `--icon` custom property below.
 */
const ICONS = {
  grocery: `<svg viewBox="0 0 48 48" width="30" height="30" aria-hidden="true">
    <rect x="6" y="16" width="36" height="26" rx="5" fill="currentColor" opacity=".14"/>
    <path d="M12 16 15 8h18l3 8" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linejoin="round"/>
    <path d="M6.5 16h35a1 1 0 0 1 1 1.1l-2.3 22a4 4 0 0 1-4 3.6H11.8a4 4 0 0 1-4-3.6l-2.3-22A1 1 0 0 1 6.5 16Z" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linejoin="round"/>
    <path d="M18 24v5a6 6 0 0 0 12 0v-5" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"/>
  </svg>`,
  fashion: `<svg viewBox="0 0 48 48" width="30" height="30" aria-hidden="true">
    <path d="M18 8h12l11 7-4.5 8L33 21v19H15V21l-3.5 2L7 15Z" fill="currentColor" opacity=".14"/>
    <path d="M18 8h12l11 7-4.5 8L33 21v19H15V21l-3.5 2L7 15Z" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linejoin="round"/>
    <path d="M18 8a6 6 0 0 0 12 0" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"/>
  </svg>`,
  'home-garden': `<svg viewBox="0 0 48 48" width="30" height="30" aria-hidden="true">
    <path d="M24 6 43 21v19a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V21Z" fill="currentColor" opacity=".14"/>
    <path d="M5 21 24 6l19 15" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/>
    <path d="M9 19v21a2 2 0 0 0 2 2h26a2 2 0 0 0 2-2V19" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linejoin="round"/>
    <path d="M24 42V30c-4 0-6-2.4-6-5.5S20 19 24 19s6 2.4 6 5.5S28 30 24 42Z" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linejoin="round"/>
  </svg>`,
} as const;

/** The three shipped recipes, in the order the archive lists them. Colours are the icons' own. */
const RECIPES = [
  {
    name: 'Corner grocer',
    icon: ICONS.grocery,
    colour: '#16a34a',
    rows: '1,794',
    items: ['Organic Rolled Oats', 'Salt-Cured Basmati Rice', 'Cheddar Wedge'],
  },
  {
    name: 'Fashion boutique',
    icon: ICONS.fashion,
    colour: '#7c3aed',
    rows: '3,466',
    items: ['Boucle Trench Coat', 'Cashmere Jumper', 'Chelsea Boots'],
  },
  {
    name: 'Home & garden',
    icon: ICONS['home-garden'],
    colour: '#d97706',
    rows: '1,225',
    items: ['Solid Oak Dining Table', 'Rattan Armchair', 'Cast Iron Planter'],
  },
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
    <div class="card" style="transform: rotate(${tilt}deg) translateY(${lift}px); --icon: ${recipe.colour}">
      <div class="card-head">
        <span class="tile">${recipe.icon}</span>
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
    display: flex; align-items: center; gap: 44px;
    padding: 0 52px;
  }

  /*
   * The mark and the claim as one group on the left, with the cards to their right.
   *
   * They used to be absolutely positioned in opposite corners, which worked while the banner
   * displayed around 1000px. Broken out to 1600 the field is wide enough that the space *between*
   * them becomes the subject: a cart glyph alone in one corner, a sentence alone in another, and
   * the cards clustered off-centre between them. Two columns give the width something to do.
   */
  .lede {
    flex: none; width: 268px;
    display: flex; flex-direction: column; gap: 20px;
  }

  /* Small and low-contrast: identification, not a logo lockup. The page's own header already
     carries the wordmark, and the hero prints the title directly beneath this. */
  .lede svg { display: block; opacity: .92; }

  .caption {
    color: rgba(255, 255, 255, .86);
    font-size: 20px; font-weight: 500; line-height: 1.35; letter-spacing: -.005em;
  }

  .cards {
    flex: 1;
    display: flex; align-items: center; justify-content: center; gap: 24px;
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

  /* The recipe's own icon, in the recipe's own colour. No plate behind it: the icons already carry
     a 14%-opacity wash of their stroke colour, and a tinted square under that reads as two
     backgrounds arguing. */
  .tile {
    flex: none;
    display: flex; align-items: center;
    color: var(--icon);
  }

  .tile svg { display: block; }

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
    <div class="lede">
      ${markSvg(44)}
      <div class="caption">One click. Nine resources. In dependency order.</div>
    </div>
    <div class="cards">${RECIPES.map(card).join('')}</div>
  </div>
</body>
</html>`;
}

test('regenerate the documentation hero banner', async ({ page }) => {
  await page.setViewportSize({ width: BASE.width * SCALE, height: BASE.height * SCALE });
  await page.setContent(banner());
  await page.screenshot({ path: join(OUT, 'banner.png') });
});
