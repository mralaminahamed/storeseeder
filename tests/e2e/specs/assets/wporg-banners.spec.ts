import { test } from '@playwright/test';
import { join } from 'path';
import { BRAND, glassField, markSvg } from '../../brand';
import { RECIPES, recipeIcon } from '../../recipe-art';

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
 * Needs no WordPress — nothing here talks to a site, unlike `assets/wporg-shots.spec.ts`.
 */
const ASSET_DIR = join(__dirname, '..', '..', '..', '..', '.wordpress-org');

/** Base canvas. The 1544x500 banner is exactly this at 2x. */
const BASE = { width: 772, height: 250 };

const BANNERS = [
  { file: 'banner-772x250.png', scale: 1 },
  { file: 'banner-1544x500.png', scale: 2 },
] as const;

/**
 * The three claims on the banner.
 *
 * A constant rather than inline markup, because the note that belongs with it cannot live inside the
 * template literal: a comment mentioning `src/lib/generators.ts` in backticks terminates the string,
 * which is how this file briefly stopped parsing at all.
 *
 * **21 is counted, not remembered.** It read `18 generators` for three releases after the count
 * changed, on the one image every visitor to the listing sees. `src/lib/generators.ts` registers 21.
 *
 * `Recipes` replaced `WP-CLI` because it is the headline of 1.2.0 and the one feature somebody browsing
 * the directory has no other way to learn about; WP-CLI is discoverable from the description.
 */
const PILLS = ['21 generators', 'Recipes', 'Live preview'] as const;

function banner(scale: number): string {
  /*
   * Three recipe cards, where a mock product table used to be.
   *
   * The table showed four invented rows in a grid, which described a plugin that makes rows. It does,
   * but so does every other test-data plugin in the directory; what only this one does is make two
   * hundred products that look like one business. The cards name the three shops and the size of each,
   * and they carry the recipes' own icons from the archive, so the listing, the documentation hero and
   * the admin all show the same artwork.
   *
   * The icon sits *above* the name rather than beside it. Beside it, at a third of a 326px strip, the
   * name had 68px to sit in and "Fashion boutique" wrapped onto two lines.
   */
  const cards = RECIPES.map((recipe, index) => {
    // Fanned rather than aligned, as on the documentation hero: three flat cards read as a table,
    // three at slight angles read as a choice being made.
    const tilt = [-3, 0, 3][index];
    const lift = [8, 0, 8][index];

    return `
      <div class="rc" style="--c: ${recipe.colour}; transform: rotate(${tilt}deg) translateY(${lift}px)">
        <div class="rh">
          <span class="ri">${recipeIcon(recipe.slug, 20)}</span>
          <span class="rn">${recipe.name}</span>
        </div>
        <div class="rr"><b>${recipe.rows}</b> rows</div>
      </div>`;
  }).join('');

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

  /* The three recipe cards.
     Inside the canvas, unlike the table this replaced: that one deliberately bled 20px past the right
     edge, and a card whose content is a grid survives being cut where a card whose content is a name
     and a number does not. 404 + 326 ends at 730, leaving a 42px margin to match the 42px on the left. */
  .cards {
    position: absolute; left: 404px; top: 22px; width: 326px; height: 206px;
    display: flex; align-items: center; gap: 9px;
  }
  /* Exactly a third of the strip each, minus the two gaps — not a bare flex-grow.
     Growing from a zero basis with a nowrap name, the card holding the longest name (Fashion
     boutique) took more than its share and the three came out unequal, so the gaps either side of
     the middle one did not match. A stated basis makes them identical by construction.
     No backticks in this comment: the whole stylesheet is a template literal. */
  .rc {
    flex: 0 0 calc((100% - 18px) / 3); min-width: 0;
    background: #fff; border-radius: 12px; padding: 13px 10px 11px;
    box-shadow: 0 16px 34px -12px rgba(${BRAND.shadow}, .45);
  }

  /* Icon above the name, not beside it. Beside it, at a third of a 326px strip, the name had 68px to
     sit in and "Fashion boutique" wrapped onto two lines. */
  .rh {
    display: flex; flex-direction: column; align-items: flex-start; gap: 8px;
    margin-bottom: 11px;
  }

  /* No plate behind the icon: the archive's icons already carry a 14%-opacity wash of their own stroke
     colour, and a tinted square under that is two backgrounds arguing. */
  .ri { flex: none; display: flex; align-items: center; color: var(--c); }
  .ri svg { display: block; }

  .rn {
    color: #1b1830; font-size: 10px; font-weight: 600; line-height: 1.2;
    letter-spacing: -.012em; white-space: nowrap;
  }

  .rr {
    border-top: 1px solid #eceaf5; padding-top: 8px;
    font-size: 9.5px; color: #6f6a88;
  }
  .rr b { color: ${BRAND.deep}; font-weight: 700; }
</style>
</head>
<body>
  <div class="stage">
    <div class="copy">
      <div class="kicker">For WordPress e-commerce</div>
      <div class="lockup">
        ${markSvg(46)}
        <div class="word">StoreSeeder</div>
      </div>
      <div class="sub">Realistic products, orders and customers &mdash;<br>seeded into your store in seconds.</div>
      <div class="pills">
        ${PILLS.map((pill) => `<span class="pill">${pill}</span>`).join('')}
      </div>
    </div>

    <div class="cards">${cards}</div>
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
