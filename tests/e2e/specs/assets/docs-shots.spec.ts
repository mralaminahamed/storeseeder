import { test, expect, type Locator, type Page } from '@playwright/test';

/**
 * Raw screenshots for the documentation site.
 *
 * Deliberately not `wporg-shots.spec.ts`. That one composites each capture onto a glass card with a
 * kicker and a title, because the WordPress.org carousel renders images at a fixed size and needs
 * them to read at a glance. Those decorations are wrong on a documentation page, where the caption
 * is the prose beside the image and a branded plate is just a thing to scroll past.
 *
 * So: no plate, no rounding, no lighting — the admin as it actually looks.
 *
 *   yarn shots:docs
 *
 * Output goes straight into the site's own assets, so Astro's image pipeline can optimise and
 * fingerprint it rather than serving a loose PNG.
 *
 * ## Two ways this file used to lie, both of which it now fails on instead
 *
 * **It waited for containers rather than content.** `preview-table` is the table *element*, which
 * renders immediately with a skeleton inside it, so the products and orders screenshots shipped ten
 * grey bars where the preview should have been — the one thing those two images exist to show. The
 * live preview takes a few seconds because it round-trips the REST API. `previewReady()` waits for
 * the skeleton to go and for real rows to arrive. Same class of mistake as waiting on `.fp-recipe`
 * instead of `.fp-recipe[data-recipe]`, which had already caught this file once.
 *
 * **It captured elements taller than their scroll container.** The admin's pages live inside
 * `.fp-scroll`, so `.fp-page` on Settings is 4,400px inside a 900px scroller. Playwright scrolls and
 * stitches, and everything past the scroller's own height came back blank — three quarters of the
 * settings screenshot was empty grey, and the test passed. `shot()` now refuses that outright, so a
 * clipped capture is a red test rather than a plausible-looking image nobody checks. Which is why
 * Settings is four focused captures of its cards instead of one of the whole page: a documentation
 * page wants the section its prose is about, not a 4,400px strip of everything.
 *
 * ## This spec writes data
 *
 * The deletion screenshots need something to delete. An empty danger zone says "No generated data to
 * delete", which documents nothing — that is what shipped before. So the recipe run here is real: one
 * `home-garden` at Small, the smallest of the nine, around 300 rows. It is what produces the progress
 * shot, the completion shot, and the counts in the danger zone. Every row goes through the ledger, so
 * the run is undoable from the same screen it is documenting.
 *
 * It follows that this file is not for a store whose contents matter. Neither is the config it runs
 * under, which is why neither one calls `tests/e2e/setup.sh`.
 *
 * ## And it sets the target platform, then puts it back
 *
 * On a machine with several stores installed, `Auto` deliberately refuses to guess — and everything
 * that writes is disabled while it cannot resolve, `Create the store` and `Add to batch` included. So
 * two of these captures were impossible until a target was chosen, which is the correct behaviour
 * getting in the way of documenting itself.
 *
 * `chooseTarget()` picks one through the topbar, the way a reader would, and `test.afterAll` puts the
 * site-wide setting back to `Auto`. The pair matters: the target applies to every user on the site, so
 * generating documentation must not be a thing that silently repoints somebody's store.
 */

const PLUGIN_URL = '/wp-admin/admin.php?page=storeseeder';
const OUT = 'docs/website/src/assets/screenshots';

/** The smallest shipped recipe, at the smallest size. See the note above about writing data. */
const RECIPE = 'home-garden';

/** The store the write captures target. Restored to `Auto` in `afterAll`. */
const TARGET = 'WooCommerce';

/** A recipe run is chunked into one REST call per hundred rows, so it is minutes, not seconds. */
const RUN_TIMEOUT = 240_000;

/**
 * Wait for a test id to be visible.
 *
 * `domcontentloaded` is not enough: this is a hash-routed React app, so the page exists long before
 * the route's data does. Each caller names the id it is actually waiting for — and where that id is a
 * container that renders before its content, it waits for the content instead. See `previewReady()`.
 */
async function ready(page: Page, testId: string): Promise<void> {
  await expect(page.getByTestId(testId)).toBeVisible({ timeout: 20_000 });

  // Two frames after the element appears, so an enter transition has finished and the capture is not
  // caught mid-fade.
  await page.waitForTimeout(400);
}

/**
 * Wait for a generator's live preview to hold real rows.
 *
 * Three conditions, because any one alone has already been enough to ship a screenshot of a
 * skeleton: the skeleton gone, the table present, and at least one row in its body.
 */
async function previewReady(page: Page): Promise<void> {
  await expect(page.getByTestId('preview-skeleton')).toHaveCount(0, { timeout: 30_000 });
  await expect(page.getByTestId('preview-table')).toBeVisible();
  await expect(page.locator('[data-testid="preview-table"] tbody tr').first()).toBeVisible({
    timeout: 30_000,
  });
  await page.waitForTimeout(400);
}

/**
 * Refuse to capture anything taller than the scroll container it sits in.
 *
 * Playwright stitches such a capture and the part past the scroller comes back blank, which looks
 * like a real screenshot with a lot of empty page at the bottom — indistinguishable from a design
 * problem, and silent. Better a failing test naming both heights.
 */
async function assertNotClipped(target: Locator, file: string): Promise<void> {
  const clipped = await target.evaluate((el) => {
    for (let parent = el.parentElement; parent; parent = parent.parentElement) {
      if (!/(auto|scroll)/.test(getComputedStyle(parent).overflowY)) continue;

      const height = el.getBoundingClientRect().height;

      return height > parent.clientHeight + 1
        ? { height: Math.round(height), scroller: parent.className, limit: parent.clientHeight }
        : null;
    }

    return null;
  });

  if (clipped) {
    throw new Error(
      `${file}: the target is ${clipped.height}px inside a ${clipped.limit}px scroller ` +
        `(.${clipped.scroller.split(' ').join('.')}), so everything past ${clipped.limit}px would ` +
        `capture blank. Raise the viewport in playwright.docs-shots.config.ts, or capture a section.`,
    );
  }
}

/** Capture one element, having first checked it can be captured whole. */
async function shot(page: Page, file: string, selector: string): Promise<void> {
  const target = page.locator(selector);

  await assertNotClipped(target, file);
  await target.screenshot({ path: `${OUT}/${file}`, scale: 'css' });
}

/**
 * Capture the region covering several selectors, plus a margin.
 *
 * For an open dropdown. `.fp-select-pop` is absolutely positioned, so its height does not count
 * towards its wrapper's box and an element capture of the control cuts the list off — while a capture
 * of the whole shell is a 1600px screenshot of a 300px control. The union of the two boxes is the
 * thing the prose is actually pointing at.
 */
async function shotRegion(
  page: Page,
  file: string,
  selectors: string[],
  margin = 16,
): Promise<void> {
  const boxes = await Promise.all(selectors.map((selector) => page.locator(selector).boundingBox()));
  const found = boxes.filter((box): box is NonNullable<typeof box> => box !== null);

  if (found.length !== selectors.length) {
    throw new Error(`${file}: ${selectors.length - found.length} of its selectors matched nothing.`);
  }

  const left = Math.min(...found.map((b) => b.x));
  const top = Math.min(...found.map((b) => b.y));
  const right = Math.max(...found.map((b) => b.x + b.width));
  const bottom = Math.max(...found.map((b) => b.y + b.height));

  await page.screenshot({
    path: `${OUT}/${file}`,
    scale: 'css',
    clip: {
      x: Math.max(0, left - margin),
      y: Math.max(0, top - margin),
      width: right - left + margin * 2,
      height: bottom - top + margin * 2,
    },
  });
}

/*
 * There is no `settingsCard( heading )` helper any more. It existed because the Sample data card was the
 * only one of the four without a test id, found by its heading text instead; it has one now, so every
 * card is addressed the same way and a heading rewrite cannot break a capture.
 */

/**
 * Choose the site-wide target platform through the topbar.
 *
 * Site-wide, so `test.afterAll` puts it back. Named `label` rather than an id because this drives the
 * control a reader drives, and the control lists platforms by name.
 */
async function chooseTarget(page: Page, label: string): Promise<void> {
  await ready(page, 'platform-select');

  await page.locator('[data-testid="platform-select"] .fp-select-btn').click();
  await expect(page.locator('[data-testid="platform-select"] [role="option"]').first()).toBeVisible();

  await page
    .locator('[data-testid="platform-select"] [role="option"]', { hasText: label })
    .first()
    .click();

  // The POST is what makes it stick; the label changing is the signal it landed.
  await expect(page.locator('[data-testid="platform-select"] .fp-select-btn')).toContainText(label);
  await page.waitForTimeout(400);
}

/**
 * Set the site-wide target from a hook, in a context of its own.
 *
 * In `beforeAll`/`afterAll` rather than inside a test, so no capture depends on another having run
 * first. It did once: `Add to batch` is disabled while `Auto` cannot resolve, so the batch capture
 * only passed because a test declared above it happened to have chosen a platform — and running
 * `yarn shots:docs -g "batch tray"` on its own therefore failed.
 */
async function setTargetInOwnContext(
  browser: Parameters<Parameters<typeof test.beforeAll>[0]>[0]['browser'],
  label: string,
): Promise<void> {
  const context = await browser.newContext({ storageState: 'tests/e2e/.auth/admin.json' });
  const page = await context.newPage();

  try {
    await page.goto(PLUGIN_URL, { waitUntil: 'domcontentloaded' });
    await chooseTarget(page, label);
  } finally {
    await context.close();
  }
}

test.describe('documentation screenshots', () => {
  /*
   * Chosen once, up front. `Auto` deliberately refuses to guess with several stores installed, and
   * everything that writes is disabled while it cannot resolve — `Create the store` and `Add to batch`
   * included. Restored in `afterAll`.
   */
  test.beforeAll(async ({ browser }) => {
    await setTargetInOwnContext(browser, TARGET);
  });

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

  test('target platform', async ({ page }) => {
    await page.goto(PLUGIN_URL, { waitUntil: 'domcontentloaded' });

    // Back to Auto for the capture, and to the run target afterwards. The teaching image is the
    // default state — Auto ticked, both stores offered — not the state this run happens to need.
    await chooseTarget(page, 'Auto');

    // Open, and captured open: the list is the point. Auto is first and reads "Auto" whatever it
    // currently resolves to, which is what the tooltip is for.
    await page.locator('[data-testid="platform-select"] .fp-select-btn').click();
    await expect(
      page.locator('[data-testid="platform-select"] [role="option"]').first(),
    ).toBeVisible();
    await page.waitForTimeout(400);

    // The control and its list, not the screen around them.
    await shotRegion(page, 'target-platform.png', [
      '[data-testid="platform-select"] .fp-select-btn',
      '[data-testid="platform-select"] .fp-select-pop',
    ]);

    await page.keyboard.press('Escape');
    await chooseTarget(page, TARGET);
  });

  test('locale picker', async ({ page }) => {
    await page.goto(PLUGIN_URL, { waitUntil: 'domcontentloaded' });
    await ready(page, 'topbar');

    await page.locator('.fp-locale-pill').click();
    await ready(page, 'locale-picker');

    // `.fp-cmd-box`, the panel — `locale-picker` is the full-screen overlay it sits on, so capturing
    // the test id gave a 1600x2600 image of a scrim. And not a union with the pill that opens it, the
    // way the target picker is: this one is a centred modal rather than an anchored dropdown, so the
    // box covering both spans almost the whole screen too.
    await shot(page, 'locale-picker.png', '[data-testid="locale-picker"] .fp-cmd-box');
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
    await page.locator(`.fp-recipe[data-recipe="${RECIPE}"]`).click();
    await expect(page.getByTestId('recipe-run')).toBeVisible();
    await page.waitForTimeout(400);

    await shot(page, 'recipes-selected.png', '.fp-recipes-page');
  });

  /*
   * The only two captures that set their own window, and both dimensions are measured rather than
   * guessed.
   *
   * Wider: at 1600 the preview column is 826px and the table's last two columns — Stock and Status —
   * fell off the right edge of the image, on the two screenshots whose entire subject is what the
   * preview shows. 1920 fits all seven with no overflow.
   *
   * Taller: enough to clear the tallest generator, and no more. Not 2600, which is what it was — the
   * wrap stretches to the viewport, so the extra pixels became a thousand pixels of empty panel. 1200
   * was the other mistake: Orders carries more parameters than Products at 1,248px, so it tripped
   * `assertNotClipped()` while Products passed. At 1500 every generator's column fits inside its own
   * scroller, which is the condition that has to hold rather than one page happening to.
   */
  test.describe('generator pages', () => {
    test.use({ viewport: { width: 1920, height: 1500 } });

    test('products generator', async ({ page }) => {
      await page.goto(`${PLUGIN_URL}#/generator/products`, { waitUntil: 'domcontentloaded' });
      await previewReady(page);

      await shot(page, 'generator-products.png', '.fp-gen-wrap');
    });

    test('orders generator', async ({ page }) => {
      await page.goto(`${PLUGIN_URL}#/generator/orders`, { waitUntil: 'domcontentloaded' });
      await previewReady(page);

      await shot(page, 'generator-orders.png', '.fp-gen-wrap');
    });

    /*
     * What a refusal looks like, which the platform-support page had no way to show. Transactions on
     * WooCommerce: payment is recorded on the order itself, so there is no separate record to create
     * and no plugin that changes that — the `unsupported` kind rather than `missing_extension`.
     *
     * No `previewReady()` here: a refused resource has no preview to wait for, which is the point.
     */
    test('unsupported resource', async ({ page }) => {
      await page.goto(`${PLUGIN_URL}#/generator/transactions`, { waitUntil: 'domcontentloaded' });
      await ready(page, 'unsupported-notice');

      await shot(page, 'unsupported-notice.png', '[data-testid="unsupported-notice"]');
    });
  });

  test('run bar', async ({ page }) => {
    await page.goto(`${PLUGIN_URL}#/generator/products`, { waitUntil: 'domcontentloaded' });
    await previewReady(page);

    // On its own, because the bar is what the "how do I actually run this" step is about and it is
    // 73px at the bottom of a 2,200px page.
    await shot(page, 'generator-runbar.png', '[data-testid="generator-runbar"]');
  });

  test.describe('batch tray', () => {
    /*
     * Shorter than the default. The tray is a full-height drawer, so at 1000px four queued rows sat
     * above six hundred pixels of nothing with the total pinned to the bottom — an accurate picture of
     * a window nobody uses to look at a batch. At 760 the head, the rows and the footer are the image.
     */
    test.use({ viewport: { width: 1600, height: 760 } });

    test('batch tray', async ({ page }) => {
      // Four resources rather than two: the tray's whole point is running several together, and two
      // rows read as "you may queue a second one".
      for (const generator of ['products', 'customers', 'orders', 'coupons']) {
        await page.goto(`${PLUGIN_URL}#/generator/${generator}`, { waitUntil: 'domcontentloaded' });
        await previewReady(page);
        await page.getByTestId('add-to-batch').click();
      }

      await page.getByTestId('batch-chip').click();
      await ready(page, 'batch-tray');

      // Each add raises a toast, and the toasts land on top of the queue — the first version of this
      // capture was two rows behind "Added 10 products to batch" and "Added 10 customers to batch",
      // covering the only thing it was taking a picture of. Dismissed rather than waited out, because
      // how long a toast lives is not something this file should depend on.
      const closers = page.locator('[data-testid="toasts"] button');

      for (let remaining = await closers.count(); remaining > 0; remaining--) {
        await closers.first().click();
      }

      await expect(page.locator('[data-testid="toasts"] button')).toHaveCount(0);
      await page.waitForTimeout(400);

      await shot(page, 'batch-tray.png', '[data-testid="batch-tray"]');
    });
  });

  test('settings — target platform', async ({ page }) => {
    await page.goto(`${PLUGIN_URL}#/settings`, { waitUntil: 'domcontentloaded' });
    await ready(page, 'settings-target-platform');

    await shot(page, 'settings-platform.png', '[data-testid="settings-target-platform"]');
  });

  test('settings — who can generate', async ({ page }) => {
    await page.goto(`${PLUGIN_URL}#/settings`, { waitUntil: 'domcontentloaded' });

    // The roles arrive from the server, and `settings-access-loading` is a different card. Waiting on
    // the loaded one is what keeps this from being a picture of three grey bars, which is what
    // shipped in the whole-page shot this replaced.
    await ready(page, 'settings-access');

    await shot(page, 'settings-access.png', '[data-testid="settings-access"]');
  });

  test('settings — AI tools', async ({ page }) => {
    await page.goto(`${PLUGIN_URL}#/settings`, { waitUntil: 'domcontentloaded' });
    await ready(page, 'settings-mcp');

    await shot(page, 'settings-mcp.png', '[data-testid="settings-mcp"]');
  });

  test('settings — recipes', async ({ page }) => {
    await page.goto(`${PLUGIN_URL}#/settings`, { waitUntil: 'domcontentloaded' });
    await ready(page, 'settings-recipes');

    // The status line arrives from `/recipes/status`, so the card renders a skeleton first — waiting on
    // the card alone would photograph two grey bars where the count and the date belong.
    await expect(page.locator('[data-testid="settings-recipes"] .fp-set-sync-title')).toBeVisible({
      timeout: 20_000,
    });
    await page.waitForTimeout(400);

    await shot(page, 'settings-recipes.png', '[data-testid="settings-recipes"]');
  });

  test('settings — sample data', async ({ page }) => {
    await page.goto(`${PLUGIN_URL}#/settings`, { waitUntil: 'domcontentloaded' });

    // Its own card, and its own status line. This waited on `settings-mcp` — a different card — so
    // nothing here guaranteed the sample-data status had arrived, and the capture could have been of
    // the skeleton. Same class of mistake as waiting on `preview-table` for the preview.
    await ready(page, 'settings-sample-data');
    await expect(
      page.locator('[data-testid="settings-sample-data"] .fp-set-sync-title'),
    ).toBeVisible({ timeout: 20_000 });
    await page.waitForTimeout(400);

    await shot(page, 'settings-sample-data.png', '[data-testid="settings-sample-data"]');
  });

  /*
   * From here the order matters, and `workers: 1` in the config is what guarantees it. The run
   * produces the two shots that follow it and the row counts the two after that need.
   */
  test('recipe run and completion', async ({ page }) => {
    test.setTimeout(RUN_TIMEOUT + 60_000);

    await page.goto(`${PLUGIN_URL}#/recipes`, { waitUntil: 'domcontentloaded' });
    await expect(page.locator('.fp-recipe[data-recipe]').first()).toBeVisible({ timeout: 20_000 });

    await page.locator(`.fp-recipe[data-recipe="${RECIPE}"]`).click();

    // Small — a quarter of the counts in the manifest. See the note at the top of this file.
    await page.locator('.fp-seg button', { hasText: 'Small' }).click();
    await page.getByTestId('recipe-run').click();

    // Mid-run: the progress bar with some steps done and one in flight. Waiting for a completed step
    // rather than a fixed delay, so the image shows progress rather than a bar at zero.
    await expect(page.locator('.fp-recipe-steps')).toBeVisible({ timeout: 30_000 });
    await expect(page.locator('.fp-recipe-step.done').first()).toBeVisible({ timeout: 60_000 });
    await page.waitForTimeout(400);

    await shot(page, 'recipe-running.png', '.fp-recipe-run');

    // The completion view owns its own route, so the run is finished when that route has rendered.
    await expect(page.locator('.fp-recipe-done-head')).toBeVisible({ timeout: RUN_TIMEOUT });
    await expect(page.locator('.fp-recipe-tally')).toBeVisible();
    await page.waitForTimeout(400);

    await shot(page, 'recipe-done.png', '.fp-recipe-done');
  });

  test('danger zone', async ({ page }) => {
    await page.goto(`${PLUGIN_URL}#/settings`, { waitUntil: 'domcontentloaded' });
    await ready(page, 'danger-generated');

    // The run above is what makes this worth capturing: an itemised count per resource rather than
    // "No generated data to delete".
    await expect(page.getByTestId('generated-breakdown')).toBeVisible({ timeout: 20_000 });
    await page.getByTestId('danger-generated').scrollIntoViewIfNeeded();
    await page.waitForTimeout(400);

    await shot(page, 'danger-zone.png', '[data-testid="danger-generated"]');
  });

  test('delete confirmation', async ({ page }) => {
    await page.goto(`${PLUGIN_URL}#/settings`, { waitUntil: 'domcontentloaded' });
    await ready(page, 'danger-generated');

    await expect(page.getByTestId('generated-breakdown')).toBeVisible({ timeout: 20_000 });
    await page.getByTestId('delete-generated').click();
    await ready(page, 'confirm-purge');

    // The dialog with the page behind it, so the image shows the scrim and where the dialog sits.
    // Nothing here presses Cancel or Delete: the shot is of the guard, not of using it.
    await shot(page, 'confirm-delete.png', '[data-testid="app-shell"]');

    // Escape, so the run does not leave a modal open in the browser profile's last state.
    await page.keyboard.press('Escape');
  });

  /**
   * Put the site-wide target back.
   *
   * In `afterAll` rather than at the end of the last test, so it runs even when a capture above has
   * failed. The setting applies to every user on the site; generating documentation must not be a
   * thing that silently repoints somebody's store.
   */
  test.afterAll(async ({ browser }) => {
    await setTargetInOwnContext(browser, 'Auto');
  });
});
