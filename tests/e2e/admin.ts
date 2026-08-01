import { expect, type Browser, type Page } from '@playwright/test';

/**
 * Helpers shared by the two screenshot specs.
 *
 * `assets/wporg-shots.spec.ts` and `assets/docs-shots.spec.ts` photograph the same admin for different
 * audiences, so they need the same three things and had begun to grow their own copies: a wait that
 * distinguishes a preview from its skeleton, a way to choose the target platform, and a way to leave
 * the dashboard looking like a plugin somebody has used.
 *
 * Nothing here asserts anything about the product. These are stage directions.
 */

export const PLUGIN_URL = '/wp-admin/admin.php?page=storeseeder';

/** The stored admin session both specs authenticate with. */
const STORAGE_STATE = 'tests/e2e/.auth/admin.json';

/**
 * Wait for a generator's live preview to hold real rows.
 *
 * Three conditions, because any one alone has been enough to ship a screenshot of a skeleton.
 * `preview-table` is the table *element*: it renders immediately with a loading skeleton inside it, and
 * the preview round-trips the REST API for several seconds afterwards. A fixed delay is a coin toss —
 * the WordPress.org shots used 1.2 seconds and the documentation shots lost the toss three times.
 */
export async function previewReady(page: Page): Promise<void> {
  await expect(page.getByTestId('preview-skeleton')).toHaveCount(0, { timeout: 30_000 });
  await expect(page.getByTestId('preview-table')).toBeVisible();
  await expect(page.locator('[data-testid="preview-table"] tbody tr').first()).toBeVisible({
    timeout: 30_000,
  });
  await page.waitForTimeout(400);
}

/**
 * Choose the site-wide target platform through the topbar.
 *
 * With several stores installed, `Auto` deliberately refuses to guess and everything that writes stays
 * disabled while it cannot resolve — so a capture of a run, a batch or a recipe is impossible until a
 * target is chosen. Site-wide, so whoever sets it is responsible for putting it back.
 */
export async function chooseTarget(page: Page, label: string): Promise<void> {
  const trigger = page.locator('[data-testid="platform-select"] .fp-select-btn');

  await expect(trigger).toBeVisible({ timeout: 20_000 });
  await trigger.click();
  await expect(page.locator('[data-testid="platform-select"] [role="option"]').first()).toBeVisible();

  await page
    .locator('[data-testid="platform-select"] [role="option"]', { hasText: label })
    .first()
    .click();

  // The POST is what makes it stick; the label changing is the signal it landed.
  await expect(trigger).toContainText(label);
  await page.waitForTimeout(400);
}

/**
 * Set the target from a hook, in a context of its own.
 *
 * In `beforeAll`/`afterAll` rather than inside a test, so no capture depends on another having run
 * first. The documentation shots did depend on that once, and running a single one with `-g` failed
 * because the test that chose a platform was filtered out.
 */
export async function setTargetInOwnContext(browser: Browser, label: string): Promise<void> {
  const context = await browser.newContext({ storageState: STORAGE_STATE });
  const page = await context.newPage();

  try {
    await page.goto(PLUGIN_URL, { waitUntil: 'domcontentloaded' });
    await chooseTarget(page, label);
  } finally {
    await context.close();
  }
}

/**
 * Run a few generators so the dashboard has something to show.
 *
 * The overview capture is the first image on the WordPress.org listing, and it read "Nothing generated
 * yet" under all four tiles, "No runs yet" in the activity list, and "Not run yet" on every generator
 * card — an advertisement for a plugin nobody had used.
 *
 * The counts and the activity list come from the *browser's* local storage rather than from the ledger
 * on the server, which is why a store already holding thousands of generated rows still showed an empty
 * dashboard: every Playwright context is a fresh browser. So this drives the real Generate button in
 * the capturing context, which is also the only thing that records a run.
 *
 * Small counts on purpose. The tiles show what was created, and the point is that the numbers are real,
 * not that they are large.
 */
export async function seedRuns(
  page: Page,
  runs: ReadonlyArray<{ route: string; count: number }>,
): Promise<void> {
  for (const run of runs) {
    await page.goto(`${PLUGIN_URL}#/generator/${run.route}`, { waitUntil: 'domcontentloaded' });
    await previewReady(page);

    await page.getByTestId('count-input').fill(String(run.count));
    await page.getByTestId('count-input').blur();

    const generate = page.getByTestId('generate-btn');

    await expect(generate).toBeEnabled({ timeout: 20_000 });
    await generate.click();

    // Enabled again is the run having finished — the button disables for the duration.
    await expect(generate).toBeEnabled({ timeout: 60_000 });
    await page.waitForTimeout(300);
  }
}
