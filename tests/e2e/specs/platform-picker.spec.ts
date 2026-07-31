import { test, expect } from '@playwright/test';
import { existsSync, lstatSync, mkdirSync, symlinkSync, unlinkSync } from 'fs';
import { join } from 'path';

/**
 * The target platform picker.
 *
 * Most of these only mean anything with more than one platform active, which a
 * plain dev install will not have. Rather than requiring a second e-commerce
 * plugin to be installed, they register a stub driver through the public
 * `storeseeder_platforms` filter — the same seam a third party would use — which
 * makes the multi-platform paths reachable anywhere and incidentally proves the
 * extension point works from outside the plugin.
 *
 * The driver lives in `tests/e2e/mu-plugins/`, a real file that `php -l` and an
 * editor can read, and is symlinked into the install for the run. It used to be an
 * escaped heredoc in this file, where the namespace separators were four
 * backslashes deep and nothing could lint it.
 *
 * Needs write access to wp-content, so the multi-platform group skips unless
 * STORESEEDER_E2E_MU_DIR names the mu-plugins directory.
 */

const PLUGIN_URL = '/wp-admin/admin.php?page=storeseeder';

/**
 * Whether a path is a symlink whose target is gone.
 *
 * `existsSync` follows links, so it answers false for a dangling one — which would leave the
 * stale link in place and the fixture unloaded, with the specs failing for a reason nowhere
 * near the cause.
 */
/**
 * Open the platform picker and return its option list.
 *
 * The picker is the app's own combobox, not a native `<select>`: it renders a listbox on
 * click, so an option only exists in the DOM once it is open. These specs drove
 * `<select>`/`option[value]` until the control changed under them — and being skipped, said
 * nothing about it for hours.
 */
async function openPicker(page: import('@playwright/test').Page) {
  const trigger = page.getByTestId('platform-select').getByRole('combobox');

  await trigger.click();

  return { trigger, options: page.getByRole('option') };
}

/**
 * Put the site-wide target back to `auto`.
 *
 * These specs assert on the state of a site option, and choosing a target writes it — so
 * without this the first run leaves `stub-cart` stored and every later run starts from a
 * premise the specs do not hold: the picker shows a choice already made, and the generator
 * page stops demanding one. The suite has to leave the install as it found it.
 */
async function resetTarget(page: import('@playwright/test').Page) {
  await page.evaluate(async () => {
    const api = window.storeseederApi;

    await fetch(`${api?.restUrl}platforms/target`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': api?.restNonce ?? '',
      },
      body: JSON.stringify({ platform: 'auto' }),
    });
  });
}

/** Choose a platform by its visible label, and wait for the write to land. */
async function chooseTarget(
  page: import('@playwright/test').Page,
  label: string,
) {
  const { trigger, options } = await openPicker(page);

  await options.filter({ hasText: label }).first().click();
  await expect(trigger).toContainText(label);
}

function isBrokenLink(path: string): boolean {
  try {
    return lstatSync(path).isSymbolicLink();
  } catch {
    return false;
  }
}
const MU_DIR = process.env.STORESEEDER_E2E_MU_DIR;

/** The fixture symlinked into the install, kept beside the specs that use it. */
const STUB_FIXTURE = join(
  __dirname,
  '..',
  'mu-plugins',
  'zz-storeseeder-e2e-stub-platform.php',
);

test.describe('Target platform picker', () => {
  test.describe('single platform', () => {
    test.beforeEach(async ({ page }) => {
      await page.goto(PLUGIN_URL);
      await page.getByTestId('app-shell').waitFor();
    });

    /**
     * A select offering one option implies a decision the user does not have, so
     * the control is absent rather than disabled.
     */
    test('the picker is hidden when only one platform is active', async ({ page }) => {
      await expect(page.getByTestId('platform-select')).toBeHidden();
    });

    test('no target prompt blocks the generator page', async ({ page }) => {
      await page.getByTestId('gen-card-products').click();
      await page.getByTestId('generator-runbar').waitFor();

      await expect(page.getByTestId('target-prompt')).toBeHidden();
      await expect(page.getByTestId('generate-btn')).toBeEnabled();
    });
  });

  test.describe('several platforms', () => {
    let stubPath: string | null = null;

    test.beforeAll(() => {
      test.skip(!MU_DIR, 'Set STORESEEDER_E2E_MU_DIR to the wp-content/mu-plugins directory.');

      mkdirSync(MU_DIR as string, { recursive: true });
      stubPath = join(MU_DIR as string, 'zz-storeseeder-e2e-stub-platform.php');

      // A link rather than a copy: the fixture stays the one file under version control, so
      // editing it takes effect on the next run and there is no second copy to drift.
      // Anything already at the path is removed first — a link left by an interrupted run
      // would otherwise make this throw and skip the whole group.
      if (existsSync(stubPath) || isBrokenLink(stubPath)) unlinkSync(stubPath);
      symlinkSync(STUB_FIXTURE, stubPath);
    });

    test.afterAll(async ({ browser }) => {
      // Clear the stored target before the stub goes away, or the site is left pointing at
      // a platform that no longer exists.
      const page = await browser.newPage();

      try {
        await page.goto(PLUGIN_URL);
        await page.getByTestId('app-shell').waitFor();
        await resetTarget(page);
      } finally {
        await page.close();
      }

      // Unlink, never delete through the link: removing the target would delete the fixture
      // from the repository.
      if (stubPath && (existsSync(stubPath) || isBrokenLink(stubPath))) unlinkSync(stubPath);
    });

    test.beforeEach(async ({ page }) => {
      await page.goto(PLUGIN_URL);
      await page.getByTestId('app-shell').waitFor();

      // Every test in this group asserts against "no target chosen", so the option is
      // cleared first and the page reloaded to pick that up.
      await resetTarget(page);
      await page.reload();
      await page.getByTestId('app-shell').waitFor();
    });

    test('the picker appears and defaults to Auto', async ({ page }) => {
      const picker = page.getByTestId('platform-select');

      await expect(picker).toBeVisible();
      await expect(picker.getByRole('combobox')).toContainText('Auto');
      // Nothing chosen yet, and two platforms active: the control is the only thing
      // between the user and a run, so it flags itself.
      await expect(picker.getByRole('combobox')).toHaveAttribute(
        'data-needs-choice',
        'true',
      );
    });

    /**
     * Auto has to name what it resolved to. "Auto" on its own says nothing about
     * where the rows are going, which is the only question this control answers.
     */
    test('Auto names the platform it resolved to, or flags that it cannot', async ({ page }) => {
      const { options } = await openPicker(page);
      const autoLabel = await options.first().textContent();

      // With two active and nothing stored, resolution is ambiguous, so Auto stands
      // alone; once a target is chosen it names it.
      expect(autoLabel).toMatch(/^Auto( · .+)?$/);
    });

    test('both platforms are offered', async ({ page }) => {
      const { options } = await openPicker(page);

      await expect(options).toContainText(['Auto', 'Fluent Cart', 'Stub Cart']);
    });

    test('the generator page demands a target before it will run', async ({ page }) => {
      await page.getByTestId('gen-card-products').click();
      await page.getByTestId('generator-runbar').waitFor();

      await expect(page.getByTestId('target-prompt')).toBeVisible();
      await expect(page.getByTestId('generate-btn')).toBeDisabled();
      // Queueing is blocked too: a batched run would fail later, further from the
      // explanation.
      await expect(page.getByTestId('add-to-batch')).toBeDisabled();
    });

    test('choosing a target from the prompt unblocks the run', async ({ page }) => {
      await page.getByTestId('gen-card-products').click();
      await page.getByTestId('generator-runbar').waitFor();

      await page.getByTestId('target-choice-fluent-cart').click();

      await expect(page.getByTestId('target-prompt')).toBeHidden();
      await expect(page.getByTestId('generate-btn')).toBeEnabled();
    });

    test('the chosen target survives a reload', async ({ page }) => {
      // The write is a REST round-trip to a site option, and chooseTarget waits for the
      // control to show the result — a reload racing the request would prove nothing.
      await chooseTarget(page, 'Fluent Cart');

      await page.reload();
      await page.getByTestId('app-shell').waitFor();

      await expect(
        page.getByTestId('platform-select').getByRole('combobox'),
      ).toContainText('Fluent Cart');
    });

    test('a resource the target cannot generate is dimmed and explained', async ({ page }) => {
      await chooseTarget(page, 'Stub Cart');

      const card = page.getByTestId('gen-card-subscriptions');
      await expect(card).toHaveAttribute('data-unavailable', 'true');
      await expect(card).toContainText('Unavailable here');

      // Still navigable, and the reason names the plugin that would enable it.
      await card.click();
      await page.getByTestId('generator-runbar').waitFor();
      await expect(page.getByTestId('unsupported-notice')).toContainText('Stub Subscriptions');
      await expect(page.getByTestId('generate-btn')).toBeDisabled();
    });
  });
});
