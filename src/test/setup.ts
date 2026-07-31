/**
 * Test environment setup, run before every test file.
 *
 * The modules under test read two browser globals that WordPress provides in production:
 * `window.storeseederApi`, inlined by `wp_localize_script`, and `localStorage`. Both are
 * reset here rather than in each file, so one test cannot leak a locale list or a stored
 * setting into the next — which is the failure mode that makes a suite pass in one order
 * and fail in another.
 *
 * Imported from `@jest/globals` rather than relied on as ambient globals, so the file
 * type-checks under the repository's own `tsc --noEmit` without an @types/jest dependency.
 */
import { afterEach, beforeEach } from "@jest/globals";
import { cleanup } from "@testing-library/react";

// DOM matchers — toBeInTheDocument, toHaveAttribute, toHaveTextContent. The
// `/jest-globals` entry point rather than the bare package: it both registers the matchers
// and augments the types of `expect` **as imported from @jest/globals**, which is how these
// tests import it. The bare entry only augments the ambient `jest.Matchers`, so
// `tsc --noEmit` would reject every matcher while the tests themselves passed.
import "@testing-library/jest-dom/jest-globals";

/** A minimal, realistic payload: the shape `class-storeseeder.php` actually inlines. */
export const TEST_LOCALES: Record<string, string> = {
	en_US: 'English (United States)',
	de_DE: 'German (Germany)',
	fr_FR: 'French (France)',
	ja_JP: 'Japanese (Japan)',
	bn_BD: 'Bangla (Bangladesh)',
};

beforeEach( () => {
	window.storeseederApi = {
		restUrl: 'https://example.test/wp-json/storeseeder/v1/',
		restNonce: 'test-nonce',
		version: '1.1.0',
		locale: {
			wordpress: 'en_US',
			faker: 'en_US',
			label: 'English (United States)',
			allLocales: { ...TEST_LOCALES },
			default: 'en_US',
		},
	};

	localStorage.clear();
} );

afterEach( () => {
	// React 18 keeps mounted trees between tests otherwise, so a query in one test can
	// find an element rendered by the previous one and match on nothing meaningful.
	cleanup();

	delete window.storeseederApi;
} );
