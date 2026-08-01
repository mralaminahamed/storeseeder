import base from './playwright.config';
import { defineConfig } from '@playwright/test';

/**
 * Config for regenerating the WordPress.org listing assets.
 *
 *   npx playwright test --config=playwright.wporg-shots.config.ts --project=shots
 *   npx playwright test --config=playwright.wporg-shots.config.ts --project=banners
 *
 * Uses `channel: 'chrome'` so it drives an already-installed Chrome rather than
 * requiring `playwright install`, and pins the viewport to 1440x900 so every
 * screenshot in .wordpress-org/ comes out the same size.
 *
 * The two projects differ in what they need: `shots` drives a logged-in WordPress
 * install, so it depends on `setup`; `banners` only renders markup, so it needs
 * neither auth nor a running site.
 */
export default defineConfig({
  ...base,
  projects: [
    { name: 'setup', testMatch: 'auth.setup.ts', use: { channel: 'chrome' } },
    {
      name: 'banners',
      testMatch: 'banners.spec.ts',
      use: { channel: 'chrome' },
    },
    {
      name: 'shots',
      testMatch: 'screenshots.spec.ts',
      dependencies: ['setup'],
      use: {
        channel: 'chrome',
        viewport: { width: 1440, height: 900 },
        storageState: 'tests/e2e/.auth/admin.json',
      },
    },
  ],
});
