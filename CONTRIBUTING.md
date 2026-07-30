# Contributing to StoreSeeder

Bug reports, feature requests, and pull requests are all welcome. This page covers the practical
details; the [development guide](docs/development.md) goes deeper on architecture and generator
internals.

By taking part in this project you agree to the [Code of Conduct](CODE_OF_CONDUCT.md).

## Before You Start

- **Security issues do not belong in the issue tracker.** Report them privately as described in the
  [security policy](SECURITY.md).
- Search the [existing issues](https://github.com/mralaminahamed/storeseeder/issues) first — the bug
  may already be filed or fixed on `trunk`.
- For anything larger than a bug fix, open an issue and agree on the approach before writing code.

## Local Setup

Requirements: PHP 7.4+, Composer, Node.js 16+, and a WordPress install with
[Fluent Cart](https://wordpress.org/plugins/fluent-cart/) active. StoreSeeder declares Fluent Cart
through the `Requires Plugins` header, so WordPress blocks activation without it.

```bash
git clone https://github.com/mralaminahamed/storeseeder.git
cd storeseeder
composer install
yarn install
yarn build
```

Work against a development or staging site only. StoreSeeder writes large volumes of fake data
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
Fluent Cart must be present as a sibling directory of this plugin — the bootstrap loads it and
creates its tables through `FluentCart\Database\DBMigrator`, because Fluent Cart's own modules
query them during `init`.

Connection details come from environment variables declared in `phpunit.xml.dist`
(`WP_DB_NAME`, `WP_DB_USER`, `WP_DB_PASS`, `WP_DB_HOST`, `WP_TABLE_PREFIX`, `WP_PATH`). Set any
of them in your shell to override:

```bash
WP_DB_PASS=secret composer test
```

The WordPress test suite **drops every table sharing `WP_TABLE_PREFIX`** — never point it at a
database you care about.

### Browser tests (Playwright)

The e2e suite drives a real WordPress install, so it needs one running with Fluent Cart and
StoreSeeder active and `yarn build` already run:

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
yarn test:e2e:screenshots
```

Writes `screenshot-1.png` … `screenshot-11.png` into `.wordpress-org/`, hiding the WordPress admin
chrome and pinning the viewport to 1440×900 so every image matches. It drives an installed Chrome
(`channel: 'chrome'`), so no `playwright install` step is needed. Keep the captions in
`readme.txt` in step with what the spec captures.

## Code Style

Full detail lives in [AGENTS.md](AGENTS.md) and the phpcs/PHPStan configs. The essentials:

**PHP** — WordPress coding standards, PSR-4 under the `StoreSeeder\` namespace, PHP 7.4 compatible.
PascalCase classes, PHPDoc on every class, method, and property. Validate REST input against JSON
Schema, require the `manage_options` capability, and prefer `WP_Error` over exceptions crossing the
REST boundary.

**TypeScript / React** — functional components with hooks, TypeScript everywhere, Tailwind CSS v4 for
styling, `@wordpress/i18n` for strings, and no `console.log` in shipped code.

**Both** — new user-facing strings must be translatable, and generated data must stay fictional.

## Adding a Generator

A generator is three coordinated pieces, all following existing patterns:

1. `includes/Generators/` — a class extending `StoreSeeder\Abstracts\Generator`
2. `includes/Controllers/` — a REST controller extending `StoreSeeder\Abstracts\Controller`, exposing
   `storeseeder/v1/<resource>/generate` plus the preview route
3. `src/` — registration so the generator appears in the admin, with its parameter schema

Persist through native Fluent Cart models rather than raw SQL, so schema, relationships, and money
handling match real store data. Copy the closest existing generator as your starting point and add
tests under `tests/php/`.

## Pull Requests

- Fill in the pull request template — what changed, why, and how you verified it.
- Keep the diff scoped; unrelated cleanups belong in their own pull request.
- Update the docs you touched: [`docs/`](docs/), [`README.md`](README.md), and
  [`CHANGELOG.md`](CHANGELOG.md) for user-visible changes. `CHANGELOG.md` is the full history;
  `readme.txt` carries only the four most recent releases.
- Note any new outbound HTTP request in [`docs/external-services.md`](docs/external-services.md), and
  in the External Services summaries in `README.md` and `readme.txt` — WordPress.org review depends on
  that disclosure being complete.
- Say plainly what you did not test.

Maintainer review happens on GitHub. Once approved, the maintainer merges — there is no need to
squash or rebase unless asked.

## Reporting Bugs

Use the bug report template and include the StoreSeeder, Fluent Cart, WordPress, and PHP versions,
the generator and parameters involved, exact steps, and any errors from `debug.log` or the browser
console.

## License

Contributions are released under [GPL-2.0-or-later](LICENSE), the same license as the plugin.
