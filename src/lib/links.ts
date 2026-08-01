/**
 * Every outbound URL the admin links to, in one place.
 *
 * They were spread across `SettingsPage.tsx`, `ConsentModal.tsx` and the sidebar, with the
 * documentation link pointing at the GitHub README from before the site existed. A URL in three files
 * is three URLs the moment one of them moves — the same failure as a locale list written twice.
 *
 * Nothing here is inlined by the server, because none of it is site-specific: these are the project's
 * own addresses, and a site cannot have a different documentation site than the plugin it is running.
 * The repository URLs the *consent prompt* shows are separate and filterable, because those describe
 * what will actually be downloaded — see `storeseeder_recipes_source`.
 */

/** The documentation site. Also the plugin header's `Plugin URI`. */
export const DOCS_URL = "https://mralaminahamed.github.io/storeseeder/";

/** Deep links into it. Kept beside the root so a section rename is one edit. */
export const DOCS = {
  home: DOCS_URL,
  gettingStarted: `${DOCS_URL}getting-started/introduction/`,
  recipes: `${DOCS_URL}guides/recipes/`,
  generators: `${DOCS_URL}guides/generators/`,
  batch: `${DOCS_URL}guides/batch/`,
  cleanup: `${DOCS_URL}guides/cleanup/`,
  locales: `${DOCS_URL}guides/locales/`,
  mcp: `${DOCS_URL}guides/mcp/`,
  settings: `${DOCS_URL}guides/settings/`,
  platformSupport: `${DOCS_URL}reference/platform-support/`,
  externalServices: `${DOCS_URL}reference/external-services/`,
  support: `${DOCS_URL}reference/support/`,
  changelog: `${DOCS_URL}reference/changelog/`,
} as const;

export const GITHUB_URL = "https://github.com/mralaminahamed/storeseeder";
export const WPORG_URL = "https://wordpress.org/plugins/storeseeder/";

/** Where a bug goes. The documentation's support page explains what to include. */
export const ISSUES_URL = `${GITHUB_URL}/issues`;

/** The two archives the consent prompt covers. */
export const SAMPLE_DATA_REPO_URL =
  "https://github.com/mralaminahamed/storeseeder-sample-data-fluent-cart";
export const RECIPES_REPO_URL = "https://github.com/mralaminahamed/storeseeder-recipes";
