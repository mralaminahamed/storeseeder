<div align="center">

<img src=".wordpress-org/icon-256x256.png" alt="StoreSeeder icon" width="96" height="96">

# StoreSeeder — Developer Guide

**Realistic test data for WordPress e-commerce — twenty-one generators writing through a platform driver, so the same recipe seeds any supported store.**

[![Version](https://img.shields.io/badge/version-1.2.0-21759b.svg)](https://github.com/mralaminahamed/storeseeder)
[![WordPress](https://img.shields.io/badge/WordPress-6.5%2B-21759b.svg?logo=wordpress&logoColor=white)](https://wordpress.org/)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-HPOS%20ready-96588A.svg)](https://woocommerce.com/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4.svg)](https://php.net/)
[![PHPStan](https://img.shields.io/badge/PHPStan-Level%207-brightgreen.svg)](https://phpstan.org/)
[![License: GPL-2.0-or-later](https://img.shields.io/badge/License-GPL--2.0--or--later-green.svg)](LICENSE)

</div>

> This is the **contributor / technical** guide. For the public plugin listing — features, screenshots, changelog, upgrade notices — see [`readme.txt`](readme.txt).

| Requirement     | Minimum | Tested up to |
|-----------------|---------|--------------|
| **WordPress**   | 6.5     | 7.1          |
| **PHP**         | 7.4     | —            |

Current version **1.2.0** · License **GPL-2.0-or-later** · Tooling **Yarn** + Composer · Delivered free on WordPress.org

---

## What it is

Testing a store needs a store: products with variations, customers who have ordered, coupons
that have been redeemed, refunds against real orders. Building that by hand is slow, and
building it with a throwaway script means rebuilding it on the next machine.

StoreSeeder generates it — and generates it **through a driver**, not against one plugin's
API. A generator says "make an order"; the driver for the active platform decides what that
means. WooCommerce and Fluent Cart ship, and a third platform is a driver away rather than a
fork.

Everything it creates is recorded in a **ledger**, so a seeded store can be un-seeded exactly:
cleanup removes what StoreSeeder made and leaves what you made.

---

## What ships

| Surface        | Provided                                                                     |
|----------------|-------------------------------------------------------------------------------|
| **Generators** | 21 — products, variations, categories, tags, brands, attributes, downloads, customers, orders, refunds, coupons, subscriptions, transactions, carts, licences, labels, logs, tax classes and rates, shipping classes and plans |
| **Platforms**  | WooCommerce and Fluent Cart drivers; others register through `Platforms\Registry` |
| **Recipes**    | A whole shop from one definition, rather than generator-by-generator          |
| **REST**       | `storeseeder/v1` — the admin SPA talks to nothing else                       |
| **WP-CLI**     | `generate`, `recipe`, `preview`, `sample-data`, `cleanup`, `platforms`        |
| **MCP**        | Abilities exposed to an MCP client, so an agent can seed a store              |
| **Ledger**     | Records every generated object so cleanup is exact, not a guess               |

---

## Architecture

### PHP — `includes/` (PSR-4 `StoreSeeder\`)

| Dir            | Responsibility                                                  | Start here              |
|----------------|-----------------------------------------------------------------|-------------------------|
| `Generation/`  | The 21 generators, the batch runner, the ledger, and purge       | `Generator.php`         |
| `Platforms/`   | Driver contract, capability probing, resolver, per-store drivers | `Platform_Interface.php`|
| `Recipes/`     | Whole-shop definitions built from generators                     | `Recipe.php`            |
| `Rest/`        | REST controllers (`storeseeder/v1`) behind one registry          | `Registry.php`          |
| `CLI/`         | WP-CLI commands sharing the same generators as the UI            | `Commands/`             |
| `MCP/`         | Abilities and server wiring for MCP clients                      | `MCP_Server.php`        |
| `Access.php`   | Capability checks — seeding is destructive, so it is gated       | —                       |
| `Storage.php`  | Options and schema migration                                     | —                       |

The seam worth knowing is `Platforms\Platform_Interface`. Generators never call WooCommerce
or Fluent Cart directly; they describe an object and the resolved driver writes it. That is
why the same recipe produces a comparable store on either platform, and why adding a third
does not touch the generators.

**HPOS:** orders are written through `WC_Order` CRUD and read with `wc_get_orders()`, and the
admin link branches on `OrderUtil::custom_orders_table_usage_is_enabled()`. Compatibility is
declared from the plugin file, before the autoloader guard, so it still happens if the
autoloader is missing.

### JavaScript / TypeScript — `src/` (React + Tailwind)

| Dir           | Responsibility                            | Built by     |
|---------------|-------------------------------------------|--------------|
| `components/` | Admin SPA screens and shared UI           | `yarn build` |
| `providers/`  | React context providers                   | `yarn build` |
| `lib/`        | API client and helpers                    | `yarn build` |
| `theme/`      | Design tokens                             | `yarn build` |
| `types/`      | Shared TypeScript types                   | `yarn build` |

### Repo map

```
storeseeder.php        Bootstrap: constants, HPOS declaration, autoloader guard
includes/              PHP (PSR-4 StoreSeeder\)
src/                   Admin SPA sources
build/                 Compiled assets — generated, do not edit
tests/php/             PHPUnit
tests/e2e/             Playwright specs
tests/assets/          Screenshot + banner generation
docs/                  Longer-form documentation
.wordpress-org/        Directory assets: icon, banners, screenshots, blueprints
```

---

## Getting started

```bash
composer install     # PHP dependencies + dev tooling
yarn install         # JS dependencies
yarn build           # compile the admin SPA
```

The plugin will not run without `vendor/autoload.php`. If it is missing, it says so in the
admin rather than activating and doing nothing.

---

## Build

```bash
yarn build           # production build
yarn start           # watch mode
yarn lint:js         # lint JS
```

---

## Testing

```bash
composer test              # PHPUnit
composer test:coverage     # with coverage
yarn test:unit             # JS unit tests
yarn test:e2e              # Playwright
yarn test:e2e:ui           # Playwright, headed
```

Some suites skip when a platform is not installed — Fluent Cart tests, for example, skip on a
site that only has WooCommerce. A skip is not a pass; check what it says before assuming
coverage.

---

## Code quality

```bash
composer phpcs                 # WordPress Coding Standards
composer phpcbf                # auto-fix
composer phpstan               # static analysis, level 7
composer phpcs:plugin-review   # the stricter directory-review ruleset
```

---

## Internationalization

```bash
composer makepot
```

Text domain `storeseeder`. Translations live in `languages/`.

---

## Release

```bash
composer release     # build, generate assets, package
composer zip:dev     # development zip
```

Directory assets are generated rather than hand-drawn:

```bash
yarn shots:wporg     # WordPress.org screenshots
yarn shots:banners   # banners
```

---

## Links

- [WordPress.org listing](https://wordpress.org/plugins/storeseeder/)
- [Documentation site](https://mralaminahamed.github.io/storeseeder)
- [Public readme](readme.txt) — features, screenshots, changelog
- [`docs/`](docs/) — longer-form documentation

---

## Contributing · Security · License

Issues and pull requests are welcome. Please run `composer phpcs`, `composer phpstan` and
`composer test` before opening one.

StoreSeeder writes and deletes store data by design. Report security issues privately rather
than in a public issue.

GPL-2.0-or-later. See [`LICENSE`](LICENSE).
