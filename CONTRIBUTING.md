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
composer makepot                # Regenerate languages/storeseeder.pot after string changes
phpunit --filter ClassName::testMethod
```

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
3. `src/admin/` — registration so the generator appears in the admin, with its parameter schema

Persist through native Fluent Cart models rather than raw SQL, so schema, relationships, and money
handling match real store data. Copy the closest existing generator as your starting point and add
tests under `tests/php/`.

## Pull Requests

- Fill in the pull request template — what changed, why, and how you verified it.
- Keep the diff scoped; unrelated cleanups belong in their own pull request.
- Update the docs you touched: [`docs/`](docs/), [`README.md`](README.md), and
  [`docs/changelog.md`](docs/changelog.md) for user-visible changes.
- Note any new outbound HTTP request in the External Services table in `README.md` and `readme.txt` —
  WordPress.org review depends on that table being complete.
- Say plainly what you did not test.

Maintainer review happens on GitHub. Once approved, the maintainer merges — there is no need to
squash or rebase unless asked.

## Reporting Bugs

Use the bug report template and include the StoreSeeder, Fluent Cart, WordPress, and PHP versions,
the generator and parameters involved, exact steps, and any errors from `debug.log` or the browser
console.

## License

Contributions are released under [GPL-2.0-or-later](LICENSE), the same license as the plugin.
