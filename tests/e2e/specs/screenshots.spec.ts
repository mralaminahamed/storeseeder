import { test } from '@playwright/test';
import { readFileSync } from 'fs';
import { join } from 'path';

const PLUGIN_URL = '/wp-admin/admin.php?page=storeseeder';
const ASSET_DIR = join(__dirname, '..', '..', '..', '.wordpress-org');

/** Brand gradient, taken from .wordpress-org/icon.svg. */
const BRAND_FROM = '#4f46e5';
const BRAND_TO = '#7c3aed';

/** Final canvas. The plugin directory renders screenshots in a fixed carousel. */
const CANVAS = { width: 1200, height: 900 };

/** Wider viewport for the capture itself, so the real UI is not cramped. */
const CAPTURE_VIEWPORT = { width: 1720, height: 1010 };

/**
 * Hide WordPress admin chrome so only the plugin's own UI is captured.
 */
async function hideWpChrome(page: import('@playwright/test').Page) {
  await page.addStyleTag({
    content: `
      #adminmenuwrap, #adminmenuback, #adminmenu,
      #wpadminbar, .wp-toolbar,
      #wpbody-content { margin-left: 0 !important; }
      #adminmenumain { display: none !important; }
      #wpadminbar { display: none !important; }
      html.wp-toolbar { padding-top: 0 !important; }
      #wpcontent, #wpfooter { margin-left: 0 !important; }
      #wpfooter { display: none !important; }

      /* Support widgets belong to whichever site the shots were taken on,
         not to this plugin. */
      .helpwp-widget { display: none !important; }

      /* Admin notices describe the capture machine, not the plugin — a listing
         screenshot should not open with "mcp-adapter is not installed". */
      #wpbody-content > .notice,
      #wpbody-content > .updated,
      #wpbody-content > .error,
      .notice, .update-nag, .updated, .error { display: none !important; }

      /* The plugin follows the admin colour scheme, so whoever captures the
         shots would otherwise decide the listing's colour. Pin the accent to the
         brand indigo so the images match the icon and banner. */
      .fp-root {
        --wp-admin-primary: ${BRAND_FROM} !important;
        --wp-admin-secondary: #4338ca !important;
        --wp-admin-highlight: #6366f1 !important;
        --wp-admin-accent: ${BRAND_TO} !important;
      }
    `,
  });
  await page.waitForTimeout(400);
}

/**
 * Compose one listing image: the real capture inset in a branded frame.
 *
 * Gradient field, icon and wordmark, a kicker plus title, the product shot on a
 * white card, and a footer line — the same treatment as the author's other
 * plugin listings, so the directory pages read as one family rather than five
 * raw admin screenshots.
 */
function frame(options: {
  shotBase64: string;
  iconBase64: string;
  kicker: string;
  title: string;
}): string {
  const { shotBase64, iconBase64, kicker, title } = options;

  return `<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
  *, *::before, *::after { box-sizing: border-box; }
  html, body { margin: 0; padding: 0; }
  body {
    width: ${CANVAS.width}px;
    height: ${CANVAS.height}px;
    overflow: hidden;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans,
      Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
    -webkit-font-smoothing: antialiased;
    background: linear-gradient(135deg, ${BRAND_FROM} 0%, ${BRAND_TO} 100%);
    position: relative;
  }

  /* Dot texture, mirrored top-right and bottom-left. */
  .dots {
    position: absolute;
    background-image: radial-gradient(rgba(255,255,255,.34) 1.6px, transparent 1.6px);
    background-size: 18px 18px;
  }
  .dots.tr { top: 38px; right: 44px; width: 168px; height: 96px; }
  .dots.bl { bottom: 54px; left: 44px; width: 96px; height: 60px; opacity: .5; }

  .brand {
    position: absolute; top: 44px; left: 52px;
    display: flex; align-items: center; gap: 16px;
  }
  /* No plate behind the icon — it sits straight on the gradient. A drop shadow
     keeps it from melting into a background of the same two stops. */
  .brand img {
    width: 58px; height: 58px; border-radius: 24%; display: block;
    filter: drop-shadow(0 6px 14px rgba(23, 16, 60, .38));
  }
  .brand span { color: #fff; font-size: 27px; font-weight: 700; letter-spacing: -.01em; }

  .head { position: absolute; top: 126px; left: 0; right: 0; text-align: center; }
  .kicker {
    color: rgba(255,255,255,.72);
    font-size: 15px; font-weight: 700; letter-spacing: .22em; text-transform: uppercase;
  }
  .title {
    color: #fff; font-size: 52px; font-weight: 800; letter-spacing: -.025em;
    margin-top: 8px; line-height: 1.05;
  }

  .card {
    position: absolute; left: 46px; right: 46px; top: 244px; height: 604px;
    background: #fff; border-radius: 22px; padding: 12px;
    box-shadow: 0 30px 70px -20px rgba(23, 16, 60, .45), 0 10px 24px -12px rgba(23, 16, 60, .3);
    overflow: hidden;
  }
  .card img {
    width: 100%; height: 100%; display: block;
    object-fit: cover; object-position: top center;
    border-radius: 12px;
  }

  .foot {
    position: absolute; left: 0; right: 0; bottom: 26px; text-align: center;
    color: rgba(255,255,255,.78); font-size: 15px;
  }
</style>
</head>
<body>
  <div class="dots tr"></div>
  <div class="dots bl"></div>

  <div class="brand">
    <img src="data:image/png;base64,${iconBase64}" alt="">
    <span>StoreSeeder</span>
  </div>

  <div class="head">
    <div class="kicker">${kicker}</div>
    <div class="title">${title}</div>
  </div>

  <div class="card">
    <img src="data:image/png;base64,${shotBase64}" alt="">
  </div>

  <div class="foot">StoreSeeder &middot; Realistic test data for Fluent Cart</div>
</body>
</html>`;
}

/**
 * The five images .wordpress-org/README.md asks for. `capture` is the element
 * whose screenshot goes on the card — the plugin's content area, without its own
 * sidebar, so the inset is not squeezed.
 */
const SHOTS = [
  {
    file: 'screenshot-1.png',
    hash: '',
    ready: 'generator-grid',
    capture: '.fp-page',
    kicker: 'Dashboard',
    title: 'Overview',
  },
  {
    file: 'screenshot-2.png',
    hash: '#/generator/products',
    ready: 'preview-table',
    capture: '.fp-gen-wrap',
    kicker: 'Generator',
    title: 'Products',
  },
  {
    file: 'screenshot-3.png',
    hash: '#/generator/customers',
    ready: 'preview-table',
    capture: '.fp-gen-wrap',
    kicker: 'Generator',
    title: 'Customers',
  },
  {
    file: 'screenshot-4.png',
    hash: '#/generator/orders',
    ready: 'preview-table',
    capture: '.fp-gen-wrap',
    kicker: 'Generator',
    title: 'Orders',
  },
  {
    file: 'screenshot-5.png',
    hash: '#/settings',
    ready: 'sidebar',
    capture: '.fp-page',
    kicker: 'Configuration',
    title: 'Settings',
    // The settings column is capped at 680px, so a wide capture leaves a dead
    // band beside it. A narrower viewport lets the column fill the frame.
    viewport: { width: 1320, height: 1050 },
  },
] as const;

test.describe('Screenshots', () => {
  const iconBase64 = readFileSync(join(ASSET_DIR, 'icon-256x256.png')).toString('base64');

  for (const [index, shot] of SHOTS.entries()) {
    test(`${index + 1}. ${shot.file}`, async ({ page }) => {
      await page.setViewportSize('viewport' in shot ? shot.viewport : CAPTURE_VIEWPORT);
      await page.goto(`${PLUGIN_URL}${shot.hash}`, { waitUntil: 'domcontentloaded' });
      await page.getByTestId(shot.ready).waitFor({ timeout: 20_000 });
      await hideWpChrome(page);
      // Let the live preview request settle so no row renders mid-fetch.
      await page.waitForTimeout(1200);

      const shotBase64 = (await page.locator(shot.capture).first().screenshot()).toString('base64');

      // Compose on a blank page so the frame's styles cannot inherit anything
      // from wp-admin.
      await page.setViewportSize(CANVAS);
      await page.setContent(
        frame({ shotBase64, iconBase64, kicker: shot.kicker, title: shot.title }),
      );
      await page.waitForTimeout(300);

      await page.screenshot({ path: join(ASSET_DIR, shot.file) });
    });
  }
});
