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

  test.describe('Layout', () => {
    test('cards are grouped by who a change affects', async ({ page }) => {
      const sections = page.locator('.fp-set-section');
      await expect(sections).toHaveCount(3);

      // Site-wide first: what it writes and who may write it, before personal taste.
      await expect(sections.nth(0)).toContainText('This site');
      await expect(sections.nth(0).getByTestId('settings-target-platform')).toBeVisible();
      await expect(sections.nth(1)).toContainText('Your preferences');
      await expect(sections.nth(1).getByTestId('settings-appearance')).toBeVisible();
    });

    test('a scope badge says which is which', async ({ page }) => {
      await expect(
        page.getByTestId('settings-target-platform').getByText('Site-wide'),
      ).toBeVisible();
      await expect(
        page.getByTestId('settings-appearance').getByText('This browser'),
      ).toBeVisible();
    });

    test('preferences save on change, with no Save button left to press', async ({
      page,
    }) => {
      await expect(page.getByRole('button', { name: /Save settings/i })).toHaveCount(0);

      const count = page.locator('#ss-default-count');
      await count.fill('42');
      await count.blur();

      await expect(page.getByTestId('settings-saved')).toBeVisible();

      // Written, not merely displayed.
      const stored = await page.evaluate(() => {
        const raw = localStorage.getItem('ec_fp_settings');
        return raw ? (JSON.parse(raw) as { defaultCount?: number }).defaultCount : null;
      });
      expect(stored).toBe(42);
    });
  });

  test.describe('Access', () => {
    test('lists the roles that can be granted, never Administrator', async ({ page }) => {
      const card = page.getByTestId('settings-access');

      await expect(card).toBeVisible();
      await expect(card.getByTestId('role-editor')).toBeVisible();
      await expect(card.getByTestId('role-administrator')).toHaveCount(0);
    });

    test('granting a role persists across a reload', async ({ page }) => {
      const toggle = page.getByTestId('settings-access').getByTestId('role-editor');

      await expect(toggle).toHaveAttribute('aria-checked', 'false');
      await toggle.click();
      await expect(toggle).toHaveAttribute('aria-checked', 'true');

      await page.reload();
      await page.getByTestId('settings-access').waitFor();
      await expect(
        page.getByTestId('settings-access').getByTestId('role-editor'),
      ).toHaveAttribute('aria-checked', 'true');

      // Leave the site as it was found — this one is stored server-side.
      await page.getByTestId('settings-access').getByTestId('role-editor').click();
      await expect(
        page.getByTestId('settings-access').getByTestId('role-editor'),
      ).toHaveAttribute('aria-checked', 'false');
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
