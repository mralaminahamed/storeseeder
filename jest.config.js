/**
 * Jest configuration for the admin's TypeScript unit tests.
 *
 * Tests sit **beside the code they test** — `src/lib/locales.ts` and
 * `src/lib/locales.test.ts` in the same directory — so a module and its tests move,
 * rename and get reviewed together, and an untested module is visible by the absence of a
 * neighbour rather than by digging through a parallel tree.
 *
 * TypeScript runs through Babel, not ts-jest: `@wordpress/babel-preset-default` already
 * carries `@babel/preset-typescript`, and the repository already type-checks every file
 * including tests with `npx tsc --noEmit`. ts-jest would type-check the same files a
 * second time, slower, for no extra signal.
 *
 * Naming matters here: Jest owns `*.test.ts(x)` and Playwright owns `*.spec.ts`. They
 * would otherwise collect each other's files — Playwright's specs import
 * `@playwright/test` and would fail under Jest with a confusing error about `test.describe`.
 */
module.exports = {
	// jsdom rather than node: the modules under test read window.storeseederApi and
	// localStorage, which is exactly the behaviour worth testing rather than mocking away.
	testEnvironment: 'jsdom',

	roots: [ '<rootDir>/src' ],
	testMatch: [ '<rootDir>/src/**/*.test.[jt]s?(x)' ],

	transform: {
		'\\.[jt]sx?$': [
			'babel-jest',
			{
				presets: [
					[
						require.resolve( '@wordpress/babel-preset-default' ),
						{ isTSX: true, allExtensions: true },
					],
				],
			},
		],
	},

	moduleNameMapper: {
		// Mirrors the `@/*` path in tsconfig.json and webpack's resolve.alias. Three
		// places have to agree; this is the third.
		'^@/(.*)$': '<rootDir>/src/$1',
	},

	// At the root beside this config, not under src/: it is test harness, not a module the
	// admin app can import, and nothing in src/ should be able to reach for it by accident.
	setupFilesAfterEnv: [ '<rootDir>/jest.setup.ts' ],

	clearMocks: true,
	restoreMocks: true,

	collectCoverageFrom: [
		'src/**/*.{ts,tsx}',
		'!src/**/*.test.{ts,tsx}',
		'!src/types/**',
	],
};
