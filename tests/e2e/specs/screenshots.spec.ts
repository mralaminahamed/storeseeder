import { test } from '@playwright/test';
import { join } from 'path';

const PLUGIN_URL = '/wp-admin/admin.php?page=storeseeder';
const SCREENSHOT_DIR = join(__dirname, '..', '..', '..', '.wordpress-org');

/**
 * Hide WordPress admin chrome (admin bar + sidebar menu) so only the
 * plugin UI is visible in the screenshots.
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

      /* The plugin picks up the admin colour scheme through these variables,
         so whoever captures the screenshots would otherwise decide what colour
         the listing is. Pin them to the plugin's own indigo so the screenshots
         match the icon and banner. */
      .fp-root {
        --wp-admin-primary: #4f46e5 !important;
        --wp-admin-secondary: #4338ca !important;
        --wp-admin-highlight: #6366f1 !important;
        --wp-admin-accent: #7c3aed !important;
      }
    `,
  });
  // Allow layout to settle after injection
  await page.waitForTimeout(500);
}

test.describe('Screenshots', () => {
  test.beforeEach(async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
  });

  /**
   * The five shots .wordpress-org/README.md asks for, in its order. Viewport
   * captures rather than fullPage, so every file lands at exactly 1440x900 —
   * the directory renders them in a fixed-size carousel, and mismatched heights
   * jump about as the visitor pages through.
   */
  const SHOTS = [
    { file: 'screenshot-1.png', hash: '', wait: 'generator-grid' },
    { file: 'screenshot-2.png', hash: '#/generator/products', wait: 'preview-table' },
    { file: 'screenshot-3.png', hash: '#/generator/customers', wait: 'preview-table' },
    { file: 'screenshot-4.png', hash: '#/generator/orders', wait: 'preview-table' },
    { file: 'screenshot-5.png', hash: '#/settings', wait: null },
  ] as const;

  for (const [index, shot] of SHOTS.entries()) {
    test(`${index + 1}. ${shot.file}`, async ({ page }) => {
      await page.goto(`${PLUGIN_URL}${shot.hash}`, { waitUntil: 'domcontentloaded' });

      if (shot.wait) {
        await page.getByTestId(shot.wait).waitFor({ timeout: 20_000 });
      } else {
        await page.getByTestId('sidebar').waitFor({ timeout: 20_000 });
      }

      await hideWpChrome(page);
      // Let the preview request settle so no row renders mid-fetch.
      await page.waitForTimeout(1200);

      await page.screenshot({ path: join(SCREENSHOT_DIR, shot.file) });
    });
  }
});
