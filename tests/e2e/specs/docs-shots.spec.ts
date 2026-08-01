import { test, expect, type Page } from '@playwright/test';

/**
 * Raw screenshots for the documentation site.
 *
 * Deliberately not `screenshots.spec.ts`. That one composites each capture onto a glass card with a
 * kicker and a title, because the WordPress.org carousel renders images at a fixed size and needs
 * them to read at a glance. Those decorations are wrong on a documentation page, where the caption
 * is the prose beside the image and a branded plate is just a thing to scroll past.
 *
 * So: no plate, no rounding, no lighting — the admin as it actually looks.
 *
 *   npx playwright test --config=playwright.docs-shots.config.ts
 *
 * Output goes straight into the site's own assets, so Astro's image pipeline can optimise and
 * fingerprint it rather than serving a loose PNG.
 */

const PLUGIN_URL = '/wp-admin/admin.php?page=storeseeder';
const OUT = 'docs/website/src/assets/screenshots';

/**
 * Wait for the admin app to have settled.
 *
 * `domcontentloaded` is not enough: this is a hash-routed React app, so the page exists long before
 * the route's data does, and a screenshot taken too early catches a skeleton. Each shot names the
 * test id it is actually waiting for.
 */
async function ready(page: Page, testId: string): Promise<void> {
  await expect(page.getByTestId(testId)).toBeVisible({ timeout: 20_000 });

  // Two frames after the element appears, so an enter transition has finished and the capture is not
  // caught mid-fade.
  await page.waitForTimeout(400);
}

/** The content area, without the plugin's own sidebar — the sidebar is in the overview shot once. */
async function shot(page: Page, file: string, selector: string): Promise<void> {
  await page.locator(selector).screenshot({ path: `${OUT}/${file}`, scale: 'css' });
}

test.describe('documentation screenshots', () => {
  test.beforeEach(async ({ page }) => {
    // Light, and the default indigo. A documentation site is read in both themes but a screenshot
    // can only be one, and light matches how WordPress admin ships.
    await page.addInitScript(() => {
      try {
        window.localStorage.setItem(
          'ec_fp_settings',
          JSON.stringify({ theme: 'light', accent: 'indigo', density: 'comfortable' }),
        );
      } catch {
        /* a private window has no storage; the defaults are the same anyway */
      }
    });
  });

  test('overview', async ({ page }) => {
    await page.goto(PLUGIN_URL, { waitUntil: 'domcontentloaded' });
    await ready(page, 'generator-grid');

    // The whole shell here, sidebar included: this is the one image whose job is "what does the
    // plugin look like", and the nav is half the answer.
    await shot(page, 'overview.png', '[data-testid="app-shell"]');
  });

  test('recipes', async ({ page }) => {
    await page.goto(`${PLUGIN_URL}#/recipes`, { waitUntil: 'domcontentloaded' });
    await ready(page, 'nav-recipes');

    // `[data-recipe]`, not `.fp-recipe`. The skeleton borrows the card's class so the placeholder has
    // the card's geometry, so waiting on the class alone caught the skeleton and produced a
    // screenshot of six grey bars — which is what happened the first time.
    await expect(page.locator('.fp-recipe[data-recipe]').first()).toBeVisible({ timeout: 20_000 });
    await page.waitForTimeout(400);

    await shot(page, 'recipes.png', '.fp-recipes-page');
  });

  test('recipe selected', async ({ page }) => {
    await page.goto(`${PLUGIN_URL}#/recipes`, { waitUntil: 'domcontentloaded' });
    await expect(page.locator('.fp-recipe[data-recipe]').first()).toBeVisible({ timeout: 20_000 });

    // Selected, so the shot carries the run bar: the size chips, the row total, and the guard. That
    // bar is what the page is actually for and it does not exist until something is picked.
    await page.locator('.fp-recipe[data-recipe]').first().click();
    await expect(page.getByTestId('recipe-run')).toBeVisible();
    await page.waitForTimeout(400);

    await shot(page, 'recipes-selected.png', '.fp-recipes-page');
  });

  test('products generator', async ({ page }) => {
    await page.goto(`${PLUGIN_URL}#/generator/products`, { waitUntil: 'domcontentloaded' });
    await ready(page, 'preview-table');

    await shot(page, 'generator-products.png', '.fp-gen-wrap');
  });

  test('orders generator', async ({ page }) => {
    await page.goto(`${PLUGIN_URL}#/generator/orders`, { waitUntil: 'domcontentloaded' });
    await ready(page, 'preview-table');

    await shot(page, 'generator-orders.png', '.fp-gen-wrap');
  });

  test('settings', async ({ page }) => {
    await page.goto(`${PLUGIN_URL}#/settings`, { waitUntil: 'domcontentloaded' });
    await ready(page, 'sidebar');

    await shot(page, 'settings.png', '.fp-page');
  });

  test('danger zone', async ({ page }) => {
    await page.goto(`${PLUGIN_URL}#/settings`, { waitUntil: 'domcontentloaded' });
    await ready(page, 'danger-generated');

    // Scrolled to, because it is the last card on a long page and the documentation for deletion
    // needs to show what the reader will be looking at.
    await page.getByTestId('danger-generated').scrollIntoViewIfNeeded();
    await page.waitForTimeout(400);

    await shot(page, 'danger-zone.png', '[data-testid="danger-generated"]');
  });
});
