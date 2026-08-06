import { defineConfig } from '@playwright/test';
import { readFileSync, existsSync } from 'fs';

/**
 * Config for everything that renders an image rather than asserting something.
 *
 *   npm run shots:wporg         listing screenshots, composited onto a card
 *   npm run shots:banners       listing icon and banners, from markup
 *   npm run shots:docs          documentation screenshots, admin as it looks
 *   npm run shots:docs-banner   the documentation site's banner
 *
 * ## One config, four projects
 *
 * This was two config files — one for the WordPress.org assets, one for the
 * documentation site's — each spreading `playwright.config.ts` and each
 * redeclaring `setup`, the auth path, the browser channel and the retry policy.
 * Nothing about the two outputs needs a separate file: what differs between
 * them is viewport, scale factor and which spec runs, and all three of those
 * are per-project settings. Two files meant two places to change the login
 * path, and one of them would eventually be missed.
 *
 * ## Not spreading the e2e config
 *
 * The end-to-end suite has its own environment, its own reporter and its own
 * idea of what a failure means. Asset rendering shares none of that and should
 * neither break it nor be broken by it — so this stands alone, and the specs
 * live in tests/assets/ rather than beside the e2e ones. When they were in
 * tests/e2e/specs/assets/ the base config needed a `testIgnore` to stop an
 * ordinary `playwright test` from overwriting the shipped PNGs with whatever
 * Faker had generated that minute. Moving them out is what removes that trap,
 * rather than a rule somebody has to remember to extend.
 *
 * `channel: 'chrome'` drives an already-installed Chrome rather than requiring
 * `playwright install`.
 */

/*
 * The same .env.test the e2e suite reads, since it holds the site URL and the
 * admin credentials. Its own copy in tests/assets/ wins when present, so an
 * asset run can point at a different site without disturbing the test one.
 */
for ( const envPath of [ 'tests/assets/.env.test', 'tests/e2e/.env.test' ] ) {
	if ( ! existsSync( envPath ) ) {
		continue;
	}

	for ( const line of readFileSync( envPath, 'utf-8' ).split( '\n' ) ) {
		const trimmed = line.trim();

		if ( ! trimmed || trimmed.startsWith( '#' ) ) {
			continue;
		}

		const idx = trimmed.indexOf( '=' );

		if ( idx === -1 ) {
			continue;
		}

		const key = trimmed.slice( 0, idx ).trim();
		const val = trimmed.slice( idx + 1 ).trim();

		if ( key && ! ( key in process.env ) ) {
			process.env[ key ] = val;
		}
	}
}

const storageState = 'tests/assets/.auth/admin.json';

export default defineConfig( {
	testDir: 'tests/assets',
	fullyParallel: false,

	/*
	 * One at a time. Several browsers writing PNGs into the same directory is a
	 * race for no gain, and the longest run here is seven captures.
	 */
	workers: 1,
	timeout: 60_000,
	retries: process.env.CI ? 2 : 0,
	reporter: [ [ 'list' ] ],
	use: {
		baseURL: process.env.WP_BASE_URL ?? 'http://localhost:8889',
		ignoreHTTPSErrors: true,
		screenshot: 'off',
		video: 'off',
		trace: 'off',
	},
	projects: [
		{ name: 'setup', testMatch: 'auth.setup.ts', use: { channel: 'chrome' } },

		/*
		 * Markup only — no WordPress behind them, so no auth dependency and no
		 * running site required.
		 */
		{
			name: 'banners',
			testMatch: 'wporg-banners.spec.ts',
			use: { channel: 'chrome' },
		},
		{
			name: 'docs-banner',
			testMatch: 'docs-banner.spec.ts',
			use: { channel: 'chrome' },
		},

		/*
		 * 1440x900 so every image in .wordpress-org/ comes out the same size.
		 */
		{
			name: 'shots',
			testMatch: 'wporg-shots.spec.ts',
			dependencies: [ 'setup' ],
			use: {
				channel: 'chrome',
				viewport: { width: 1440, height: 900 },
				storageState,
			},
		},

		/*
		 * Wider than the listing's 1440: the Recipes grid is three across at
		 * this measure and two below 1100, and a documentation screenshot
		 * showing two cards would not show the grid.
		 *
		 * Shell-level captures are viewport-height by definition, so the height
		 * stays ordinary — a tall one here would put a screen of empty page
		 * below the content in every single image. The two captures that
		 * genuinely need more raise it themselves with `test.use` in the spec.
		 *
		 * 2× so the images stay sharp on the displays most people read
		 * documentation on. Astro downscales and fingerprints them at build
		 * time, so a large source costs nothing at request time.
		 */
		{
			name: 'docs',
			testMatch: 'docs-shots.spec.ts',
			dependencies: [ 'setup' ],
			use: {
				channel: 'chrome',
				viewport: { width: 1600, height: 1000 },
				deviceScaleFactor: 2,
				storageState,
			},
		},
	],
} );
