---
title: Contributing
description: How to set up, what the quality gates are, and the three things most people come here to add.
---

StoreSeeder is GPL-2.0 and developed in the open. Bug reports, fixes, new platform drivers and new
recipes are all welcome.

This page orients you. The process — branching, commit format, what a pull request needs — lives in
[CONTRIBUTING.md](https://github.com/mralaminahamed/storeseeder/blob/trunk/CONTRIBUTING.md), which is
the canonical version; nothing here restates it, because two copies of a process is how they start
disagreeing.

## Setting up

Requirements: PHP 7.4+, Composer, Node 16+, and a WordPress install with one supported store plugin
active — WooCommerce or Fluent Cart.

```bash
git clone https://github.com/mralaminahamed/storeseeder.git
cd storeseeder
composer install
yarn install
yarn build
```

There is deliberately no `Requires Plugins` header. It would make WordPress refuse activation without
one named plugin, which would put every other platform out of reach — so StoreSeeder activates either
way and reports what is missing. That is what lets a driver be developed against a store plugin you
have not installed yet.

:::caution
Work against a development or staging site. StoreSeeder writes large volumes of data straight into the
store's own tables.
:::

## The quality gates

Everything a pull request has to pass, and all of it runnable locally:

| Command | What it checks |
|---|---|
| `composer phpcs` | WordPress Coding Standards over `includes/` |
| `composer phpstan` | Static analysis at level 7 |
| `composer test` | PHPUnit — needs a test database, see CONTRIBUTING.md |
| `yarn lint:js` | ESLint over `src/` |
| `npx tsc --noEmit` | The only type-check the tests get; Jest transpiles through Babel |
| `yarn test:unit` | Jest and React Testing Library, colocated beside their source |
| `yarn build` | The production bundle |

Two conventions that surprise people: **PHP indents with tabs** (per WPCS, and the `.editorconfig` is
wrong about it), and **PHP methods and variables are `snake_case`**. TypeScript indents with two
spaces.

## Adding a platform

The whole surface is one filter. Append an object implementing `Platform_Interface` — extending
`Platform_Driver` is shortest — and the generators, the REST API, WP-CLI and the admin pick it up
without further changes.

```php
add_filter( 'storeseeder_platforms', function ( array $platforms ): array {
    $platforms[] = new My_Store_Driver();

    return $platforms;
} );
```

A driver answers four questions: what it is called, whether it is active, which resources it supports,
and which writer handles each one. Two rules matter more than the rest:

- **A refusal has to say which kind it is.** `Capability::unsupported( $reason )` is a concept the
  platform does not have; `Capability::missing_extension( $slug, $label )` is one a plugin would
  enable. They read differently to a user — one is a dead end, the other is a link — and reporting the
  first where the second is true sends somebody looking for a setting that does not exist.
- **Money arrives as an integer in the currency's minor unit.** Never a float. Divide in your writer
  if your platform stores decimals.

Full detail in [Extension points](/storeseeder/reference/extension-points/) and
[Architecture](/storeseeder/reference/architecture/).

## Adding a generator

Six pieces, and all six are needed or the resource is half-registered:

1. `includes/Generation/Generators/` — extends `Generation\Generator`, implements `build_entity()`
2. `includes/Platforms/Drivers/<Platform>/Writers/` — extends `Platforms\Writer`
3. That driver's `writer_classes()` and `capabilities()`
4. `includes/Platforms/Resource.php` — the canonical name
5. `includes/Rest/Controllers/` — extends `Rest\Controller`, listed in `Rest\Registry`
6. `src/lib/generators.ts` — admin registration, parameter schema, and the `resource` key

The one rule worth internalising: **a generator may not name a platform.** No models, no table names,
no platform-specific status strings, no database reads. That constraint is what lets one generator feed
every platform and lets a fixed seed produce the same data everywhere.

The other: **a declared parameter must change the output.** If it appears in the admin, the REST schema
or an MCP tool's inputs, it has to reach `build_entity()` or a writer. Where one cannot be honoured it
is removed rather than left looking functional — a control that moves, saves and changes nothing is
worse than an absent one.

## Adding a recipe

Recipes live in [their own repository](https://github.com/mralaminahamed/storeseeder-recipes), so a new
shop type or a new locale for an existing one needs no plugin release. A recipe is a *vocabulary* —
product names, a category tree, brand names, a price band, variation axes — never a dump of records.

You can also register one from your own plugin with no download at all:

```php
add_filter( 'storeseeder_recipes', function ( array $manifests ): array {
    $manifests[] = json_decode( file_get_contents( __DIR__ . '/my-recipe/recipe.json' ), true );

    return $manifests;
} );

add_filter( 'storeseeder_recipe_directories', function ( array $dirs ): array {
    $dirs['my-recipe'] = __DIR__ . '/my-recipe';

    return $dirs;
} );
```

See [Recipes](/storeseeder/guides/recipes/) for the manifest format, and the caution there about
`params` keys — all three shipped recipes once declared a coupon parameter the generator does not read,
ran to completion, and reported success while ignoring it.

## Reporting a bug

[Support & troubleshooting](/storeseeder/reference/support/) lists what to include. The short version:
the seed, the locale, the count, the target platform and its version, and which store plugins are
active. With a seed, whoever reads the report gets the data you got.

For a vulnerability, use a
[private advisory](https://github.com/mralaminahamed/storeseeder/security/advisories/new) rather than a
public issue.
