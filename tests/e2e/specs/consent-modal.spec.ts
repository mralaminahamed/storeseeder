import { test, expect } from '@playwright/test';

const PLUGIN_URL = '/wp-admin/admin.php?page=storeseeder';

test.describe('Sample-data consent modal', () => {
  test('shows the modal when consent is undecided', async ({ page }) => {
    await page.route('**/download-sample', async (route) => {
      if (route.request().method() === 'GET') {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            exists: false,
            last_synced: null,
            repo_url: '',
            consent: null,
          }),
        });
      } else {
        await route.continue();
      }
    });

    await page.goto(PLUGIN_URL);
    await expect(page.getByTestId('consent-modal')).toBeVisible();
  });

  test('declining records consent and hides the modal', async ({ page }) => {
    await page.route('**/download-sample', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          exists: false,
          last_synced: null,
          repo_url: '',
          consent: null,
        }),
      });
    });
    let declined = false;
    await page.route('**/download-sample/consent', async (route) => {
      declined = true;
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ consent: 'declined' }),
      });
    });

    await page.goto(PLUGIN_URL);
    await page.getByTestId('consent-decline').click();
    await expect(page.getByTestId('consent-modal')).toBeHidden();
    expect(declined).toBe(true);
  });

  test('a failed decline keeps the modal open rather than closing unsaved', async ({
    page,
  }) => {
    await page.route('**/download-sample', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          exists: false,
          last_synced: null,
          repo_url: '',
          consent: null,
        }),
      });
    });
    await page.route('**/download-sample/consent', async (route) => {
      await route.fulfill({
        status: 403,
        contentType: 'application/json',
        body: JSON.stringify({ message: 'nope' }),
      });
    });

    await page.goto(PLUGIN_URL);
    await page.getByTestId('consent-decline').click();

    // Nothing was stored, so the prompt must not pretend the choice was taken.
    await expect(page.getByTestId('consent-modal')).toBeVisible();
  });

  test('Sync now reopens the prompt instead of downloading when consent is revoked', async ({
    page,
  }) => {
    // Revoked, and data already present, so neither load-time gate opens the
    // modal. Any POST here would be a download that bypassed the prompt.
    let downloaded = false;
    await page.route('**/download-sample', async (route) => {
      if (route.request().method() === 'GET') {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            exists: true,
            last_synced: '2026-01-01T00:00:00+00:00',
            repo_url: '',
            consent: 'declined',
          }),
        });
        return;
      }
      downloaded = true;
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ success: true, message: 'synced' }),
      });
    });

    await page.goto(`${PLUGIN_URL}#/settings`);
    await expect(page.getByTestId('consent-modal')).toBeHidden();

    await page.getByRole('button', { name: 'Sync now' }).click();

    await expect(page.getByTestId('consent-modal')).toBeVisible();
    expect(downloaded).toBe(false);
  });

  test('Sync now downloads directly once consent is granted', async ({
    page,
  }) => {
    let downloaded = false;
    await page.route('**/download-sample', async (route) => {
      if (route.request().method() === 'GET') {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            exists: true,
            last_synced: '2026-01-01T00:00:00+00:00',
            repo_url: '',
            consent: 'granted',
          }),
        });
        return;
      }
      downloaded = true;
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ success: true, message: 'synced' }),
      });
    });

    await page.goto(`${PLUGIN_URL}#/settings`);
    await page.getByRole('button', { name: 'Sync now' }).click();

    await expect.poll(() => downloaded).toBe(true);
    await expect(page.getByTestId('consent-modal')).toBeHidden();
  });
});
