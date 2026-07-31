import { test, expect } from '@playwright/test';

/**
 * The locale picker.
 *
 * Worth its own spec because the control was broken in a way no unit test could
 * see: it stored the display label, so the chosen value could never be sent to the
 * REST API, and the topbar pill wrote a key nothing read — clicking a locale
 * changed the pill and nothing else. These assert the parts that regressed: the
 * choice reaches the pill, it survives a reload, and the search field narrows a
 * list of seventy-five.
 */

const PLUGIN_URL = '/wp-admin/admin.php?page=storeseeder';

test.describe('Locale picker', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto(PLUGIN_URL);
    await page.getByTestId('app-shell').waitFor();
  });

  test('locale pill opens the picker', async ({ page }) => {
    await page.getByTestId('locale-pill').click();
    await expect(page.getByTestId('locale-picker')).toBeVisible();
    // The search field takes focus on open, so typing narrows without a click first.
    await expect(page.getByTestId('locale-search')).toBeFocused();
  });

  test('every locale the server accepts is offered', async ({ page }) => {
    await page.getByTestId('locale-pill').click();
    await page.getByTestId('locale-picker').waitFor();

    const supported = await page.evaluate(
      () => Object.keys(window.storeseederApi?.locale?.allLocales ?? {}).length,
    );

    // Six was the old REST enum; the picker offering more than the API accepted is
    // exactly the mismatch this asserts against.
    expect(supported).toBeGreaterThan(6);
    await expect(page.locator('[data-testid^="locale-option-"]')).toHaveCount(supported);
  });

  test('search narrows the list', async ({ page }) => {
    await page.getByTestId('locale-pill').click();
    await page.getByTestId('locale-picker').waitFor();

    await page.getByTestId('locale-search').fill('bangla');

    await expect(page.getByTestId('locale-option-bn_BD')).toBeVisible();
    await expect(page.getByTestId('locale-option-en_US')).toBeHidden();
  });

  test('search matches on the code as well as the label', async ({ page }) => {
    await page.getByTestId('locale-pill').click();
    await page.getByTestId('locale-picker').waitFor();

    await page.getByTestId('locale-search').fill('ja_JP');

    await expect(page.getByTestId('locale-option-ja_JP')).toBeVisible();
  });

  test('a query matching nothing says so rather than showing an empty box', async ({
    page,
  }) => {
    await page.getByTestId('locale-pill').click();
    await page.getByTestId('locale-picker').waitFor();

    await page.getByTestId('locale-search').fill('zzzzz');

    await expect(page.getByTestId('locale-empty')).toBeVisible();
    await expect(page.locator('[data-testid^="locale-option-"]')).toHaveCount(0);
  });

  test('choosing a locale updates the pill and survives a reload', async ({ page }) => {
    await page.getByTestId('locale-pill').click();
    await page.getByTestId('locale-picker').waitFor();

    await page.getByTestId('locale-search').fill('ja_JP');
    await page.getByTestId('locale-option-ja_JP').click();

    await expect(page.getByTestId('locale-picker')).toBeHidden();
    await expect(page.getByTestId('locale-pill')).toContainText('Japanese');

    // Persisted through the settings store, not component state — the previous
    // version wrote a key nothing read, so the choice was gone on reload.
    await page.reload();
    await page.getByTestId('app-shell').waitFor();
    await expect(page.getByTestId('locale-pill')).toContainText('Japanese');
  });

  test('Escape closes the picker', async ({ page }) => {
    await page.getByTestId('locale-pill').click();
    await page.getByTestId('locale-picker').waitFor();

    await page.keyboard.press('Escape');

    await expect(page.getByTestId('locale-picker')).toBeHidden();
  });
});
