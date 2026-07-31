import { test, expect } from '@playwright/test';

/**
 * The Settings page.
 *
 * Covers the two cards that are more than a stored preference: Target platform
 * writes a site-wide option through REST, and Appearance drives attributes that the
 * whole stylesheet reads. Density in particular is easy to half-implement — the
 * tokens it overrides have to reach controls, not only containers — so it is
 * asserted on a button's measured height rather than on the attribute alone.
 */

const SETTINGS_URL = '/wp-admin/admin.php?page=storeseeder#/settings';

test.describe('Settings', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto(SETTINGS_URL);
    await page.getByTestId('settings-target-platform').waitFor();
  });

  test.describe('Target platform', () => {
    test('names what Auto resolves to', async ({ page }) => {
      const card = page.getByTestId('settings-target-platform');

      // With one platform active there is no ambiguity, so the card reports the
      // resolution rather than asking for a decision.
      await expect(card).toContainText(/Auto currently resolves to|not active|Activate one/);
      await expect(card).toContainText('Active:');
    });

    test('offers Auto plus each active platform', async ({ page }) => {
      await page.getByTestId('settings-target-platform').locator('button').first().click();

      const options = page.locator('.fp-select-opt');
      await expect(options.first()).toBeVisible();
      await expect(options.filter({ hasText: /Auto/ })).toHaveCount(1);
    });
  });

  test.describe('Appearance', () => {
    test('theme switches the root attribute', async ({ page }) => {
      const card = page.getByTestId('settings-appearance');

      await card.getByRole('button', { name: /Dark/i }).click();
      await expect(page.locator('.fp-root')).toHaveAttribute('data-theme', 'dark');

      await card.getByRole('button', { name: /Light/i }).click();
      await expect(page.locator('.fp-root')).toHaveAttribute('data-theme', 'light');
    });

    test('compact density tightens controls, not just containers', async ({ page }) => {
      const card = page.getByTestId('settings-appearance');
      const button = card.getByRole('button', { name: /Comfortable/i });

      const comfortable = await button.evaluate(
        (el) => el.getBoundingClientRect().height,
      );

      await card.getByRole('button', { name: /Compact/i }).click();
      await expect(page.locator('.fp-root')).toHaveAttribute('data-density', 'compact');

      const compact = await button.evaluate((el) => el.getBoundingClientRect().height);
      expect(compact).toBeLessThan(comfortable);

      // Type scales with the box. A control that only lost height was the bug.
      const fontSize = await button.evaluate(
        (el) => parseFloat(getComputedStyle(el).fontSize),
      );
      expect(fontSize).toBeLessThan(13);

      await card.getByRole('button', { name: /Comfortable/i }).click();
      await expect(page.locator('.fp-root')).toHaveAttribute('data-density', 'comfortable');
    });

    test('links to Tweaks for accent and custom colours', async ({ page }) => {
      await page
        .getByTestId('settings-appearance')
        .getByRole('button', { name: /Accent/i })
        .click();

      await expect(page.getByTestId('tweaks-panel')).toBeVisible();
    });
  });

  test('the sample data card links to the repository the server reports', async ({
    page,
  }) => {
    const reported = await page.evaluate(async () => {
      const api = window.storeseederApi;
      const res = await fetch(`${api?.restUrl}download-sample`, {
        headers: { 'X-WP-Nonce': api?.restNonce ?? '' },
      });
      const body: unknown = await res.json();
      return null !== body && 'object' === typeof body
        ? ((body as Record<string, unknown>).repo_url as string)
        : '';
    });

    expect(reported).toContain('github.com');
    await expect(page.locator(`a[href="${reported}"]`)).toHaveCount(1);
  });
});
