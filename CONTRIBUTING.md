# Contributing to StoreSeeder

Bug reports, feature requests, and pull requests are all welcome. This page covers the practical
details; the [development guide](docs/guides/development.md) goes deeper on architecture and generator
internals.

By taking part in this project you agree to the [Code of Conduct](CODE_OF_CONDUCT.md).

## Before You Start

- **Security issues do not belong in the issue tracker.** Report them privately as described in the
  [security policy](SECURITY.md).
- Search the [existing issues](https://github.com/mralaminahamed/storeseeder/issues) first — the bug
  may already be filed or fixed on `trunk`.
- For anything larger than a bug fix, open an issue and agree on the approach before writing code.

## Local Setup

Requirements: PHP 7.4+, Composer, Node.js 16+, and a WordPress install with one supported
e-commerce platform active — [WooCommerce](https://wordpress.org/plugins/woocommerce/) and
[Fluent Cart](https://wordpress.org/plugins/fluent-cart/) are the drivers shipped today.

There is no `Requires Plugins` header, deliberately: it would have made WordPress refuse activation
without one named plugin, which would put every other platform out of reach. StoreSeeder
activates either way and reports what is missing, so a driver can be developed against a store
plugin that is not installed yet.

```bash
git clone https://github.com/mralaminahamed/storeseeder.git
cd storeseeder
composer install
yarn install
yarn build
```

Work against a development or staging site only. StoreSeeder writes large volumes of test data
straight into the store tables.

## Working on a Change

```bash
yarn start          # Webpack watch mode while editing src/
```

Branch off `trunk` and keep the branch focused on one change:

```bash
git switch -c fix/orders-tax-rounding
```

Commit with [Conventional Commits](https://www.conventionalcommits.org/) — `type(scope): summary` —
matching the existing history:

```
fix(orders): round tax lines to the store currency precision
feat(generators): add product download generator
docs(readme): document the sample-data consent flow
```

## Quality Gates

Run these before opening a pull request. CI and review both expect them to pass:

```bash
composer lint       # WordPress coding standards (phpcs)
composer format     # Auto-fix what phpcbf can (phpcbf)
composer analyse    # Static analysis (PHPStan)
composer test       # PHPUnit
yarn build          # Production bundle must compile
```

Useful extras:

```bash
composer test:coverage          # HTML coverage report in tests/coverage/html
composer phpcs:plugin-review    # Stricter ruleset used for directory review
composer makepot                # Regenerate languages/storeseeder.pot (run yarn build first)
phpunit --filter ClassName::testMethod
```

The same four commands run on every push and pull request — see
[`.github/workflows/ci.yml`](.github/workflows/ci.yml), which lints, analyses, builds,
and runs PHPUnit across PHP 7.4 through 8.3.

### First-time PHPUnit setup

The suite needs the WordPress test library and a throwaway database. Install both once:

```bash
bash tests/php/bin/install-wp-tests.sh wordpress_test <db-user> <db-pass> localhost latest
```

Pass `true` as a sixth argument to skip database creation if `wordpress_test` already exists.
A platform is loaded from a sibling directory only when StoreSeeder ships a driver for it, so
Fluent Cart must be present as a sibling of this plugin — the bootstrap loads it and creates its
tables through `FluentCart\Database\DBMigrator`, because Fluent Cart's own modules query them
during `init`. Tests needing a platform that is absent skip themselves through
`require_platform( $id )` rather than failing, since no contributor will have every platform
installed.

Connection details come from environment variables declared in `phpunit.xml.dist`
(`WP_DB_NAME`, `WP_DB_USER`, `WP_DB_PASS`, `WP_DB_HOST`, `WP_TABLE_PREFIX`, `WP_PATH`). Set any
of them in your shell to override:

```bash
WP_DB_PASS=secret composer test
```

The WordPress test suite **drops every table sharing `WP_TABLE_PREFIX`** — never point it at a
database you care about.

### TypeScript unit tests (Jest)

```bash
yarn test:unit            # once
yarn test:unit:watch      # while working
yarn test:unit:coverage
```

Tests live **beside the code they test** — `src/lib/locales.test.ts` next to
`src/lib/locales.ts` — so a module and its tests move and get reviewed together. Two rules
follow from the setup: name the file `*.test.ts(x)`, because `*.spec.ts` belongs to
Playwright and each runner would try to execute the other's files; and import from
`@jest/globals` rather than relying on ambient globals, which is what keeps the tests
type-checked. Jest transpiles through Babel and does not check types, so run
`npx tsc --noEmit` alongside it.

### Browser tests (Playwright)

The e2e suite drives a real WordPress install, so it needs one running with StoreSeeder and a
supported platform active, and `yarn build` already run. The multi-platform specs register a stub
driver through the public `storeseeder_platforms` filter rather than requiring a second store
plugin — which incidentally proves the extension point works from outside:

```bash
cp tests/e2e/.env.test.example tests/e2e/.env.test   # set WP_BASE_URL and admin credentials
yarn test:e2e                                        # all specs
yarn test:e2e:ui                                     # interactive runner
yarn test:e2e:report                                 # open the last HTML report
```

`tests/e2e/auth.setup.ts` logs in once and every spec reuses that session. Values already in the
environment take precedence over `.env.test`, so CI can supply them directly.

`yarn test:e2e:setup` is an optional helper that sets a known admin password through WP-CLI. It
**overwrites that account's password** and asks for confirmation first — point it at a throwaway
install only.

### Regenerating the WordPress.org screenshots

```bash
yarn shots:wporg
```

Writes `screenshot-1.png` … `screenshot-11.png` into `.wordpress-org/`, hiding the WordPress admin
chrome and pinning the viewport to 1440×900 so every image matches. It drives an installed Chrome
(`channel: 'chrome'`), so no `playwright install` step is needed. Keep the captions in
`readme.txt` in step with what the spec captures.

## Code Style

Full detail lives in [AGENTS.md](AGENTS.md) and the phpcs/PHPStan configs. The essentials:

**PHP** — WordPress coding standards, PSR-4 under the `StoreSeeder\` namespace, PHP 7.4 compatible.
PascalCase classes, PHPDoc on every class, method, and property. Validate REST input against JSON
Schema, gate on `StoreSeeder\Access` rather than a literal capability, and prefer `WP_Error` over exceptions crossing the
REST boundary.

**TypeScript / React** — functional components with hooks, TypeScript everywhere, Tailwind CSS v4 for
styling, `@wordpress/i18n` for strings, and no `console.log` in shipped code.

**Both** — new user-facing strings must be translatable, and generated data must stay fictional.

## Adding a Generator

A generator is three coordinated pieces, all following existing patterns:

1. `includes/Generation/Generators/` — a class extending `StoreSeeder\Generation\Generator`,
   implementing `build_entity()` with FakerPHP only. It must name no platform: no models, no
   table names, no platform status strings, no database reads.
2. `includes/Platforms/Drivers/<Platform>/Writers/` — a class extending
   `StoreSeeder\Platforms\Writer` that persists the entity, plus an entry in that driver's
   `writer_classes()` and `capabilities()`
3. `includes/Rest/Controllers/` — a REST controller extending
   `StoreSeeder\Rest\Controller`, exposing `storeseeder/v1/<resource>/generate` plus
   the preview route
4. `includes/Platforms/Resource.php` — add the canonical resource name
5. `src/lib/generators.ts` — registration so it appears in the admin, with its parameter
   schema and its `resource` key

Persist through the platform's own models rather than raw SQL, so schema, relationships and money
handling match real store data. Money in a canonical entity is an integer in the currency's minor
unit; the writer converts if its platform stores decimals. Copy the closest existing generator as your starting point and add
tests under `tests/php/`.

## Pull Requests

- Fill in the pull request template — what changed, why, and how you verified it.
- Keep the diff scoped; unrelated cleanups belong in their own pull request.
- Update the docs you touched: [`docs/`](docs/), [`README.md`](README.md), and
  [`CHANGELOG.md`](CHANGELOG.md) for user-visible changes. `CHANGELOG.md` is the full history;
  `readme.txt` carries only the four most recent releases.
- Note any new outbound HTTP request in [`docs/guides/external-services.md`](docs/guides/external-services.md), and
  in the External Services summaries in `README.md` and `readme.txt` — WordPress.org review depends on
  that disclosure being complete.
- Say plainly what you did not test.

Maintainer review happens on GitHub. Once approved, the maintainer merges — there is no need to
squash or rebase unless asked.

## Reporting Bugs

Use the bug report template and include the StoreSeeder, platform (e.g. Fluent Cart), WordPress, and
PHP versions,
the generator and parameters involved, exact steps, and any errors from `debug.log` or the browser
console.

## License

Contributions are released under [GPL-2.0-or-later](LICENSE), the same license as the plugin.
