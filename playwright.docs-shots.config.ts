import base from './playwright.config';
import { defineConfig } from '@playwright/test';

/**
 * Config for the documentation site's screenshots.
 *
 *   npx playwright test --config=playwright.docs-shots.config.ts
 *
 * Separate from `playwright.wporg-shots.config.ts` because the two want different images from the
 * same pages. That one composites onto a branded card for the WordPress.org carousel; this one wants
 * the admin as it looks, because on a documentation page the caption is the prose beside the image
 * and a branding plate is only something to scroll past.
 *
 * `channel: 'chrome'` drives an already-installed Chrome rather than requiring `playwright install`.
 *
 * The viewport is wider than the listing's 1440: the Recipes grid is three across at this measure and
 * two below 1100, and a documentation screenshot showing two cards would not show the grid.
 *
 * Nothing here runs `tests/e2e/setup.sh`. That script resets the admin password, and regenerating an
 * image is not a reason to touch anybody's credentials.
 */
export default defineConfig({
  ...base,
  // One at a time. Several browsers writing PNGs into the same directory is a race for no gain, and
  // the run is seven captures.
  workers: 1,
  projects: [
    { name: 'setup', testMatch: 'auth.setup.ts', use: { channel: 'chrome' } },
    {
      name: 'docs',
      testMatch: 'docs-shots.spec.ts',
      dependencies: ['setup'],
      use: {
        channel: 'chrome',
        viewport: { width: 1600, height: 1000 },
        // 2× so the images stay sharp on the displays most people read documentation on. Astro
        // downscales and fingerprints them at build time, so the source being large costs nothing at
        // request time.
        deviceScaleFactor: 2,
        storageState: 'tests/e2e/.auth/admin.json',
      },
    },
  ],
});
