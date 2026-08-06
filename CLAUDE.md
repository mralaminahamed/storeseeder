# CLAUDE.md

Guidance for Claude Code working in this repository. Facts here were verified against the
code; where a statement is a rule rather than an observation, the reason is given, because a
rule without a reason gets worked around.

[`AGENTS.md`](AGENTS.md) covers coding style and conventions and is kept deliberately free of
architecture, so the two files cannot drift into contradicting each other the way an earlier
version did.

## What this plugin is

StoreSeeder generates realistic test data for WordPress e-commerce platforms. It is not
single-platform: a **platform driver** decides where data lands, and the same generators feed
every platform. Fluent Cart and WooCommerce ship; EasyCommerce and StoreEngine are planned, and
a third party can add one from their own plugin.

## Commands

```bash
# PHP
composer test                 # PHPUnit — needs env, see below
composer phpcs                # WPCS. Scans includes/ ONLY
composer phpcbf               # autofix
composer phpstan              # level 7 over includes/ + the two root PHP files
composer makepot              # requires build/admin-app.js to exist first

# Frontend
yarn build                    # webpack via @wordpress/scripts
yarn lint:js                  # eslint (flat config)
yarn test:unit                # Jest + RTL — 215 tests, colocated as src/**/*.test.ts(x)
npx tsc --noEmit              # the only type-check the tests get; Jest uses Babel
yarn test:e2e                 # Playwright — see the warning below
```

### Running PHPUnit locally

`composer test` on its own will fail with a database error. The suite needs a dedicated
database and three environment variables:

```bash
mysql -uroot -ppassword -e "CREATE DATABASE IF NOT EXISTS wordpress_test;"
export WP_PHPUNIT__DIR="$PWD/vendor/wp-phpunit/wp-phpunit" \
       WP_DB_PASS=password \
       WP_PATH=/Users/alamin/Sites/easycommerce-shop
vendor/bin/phpunit
```

`phpunit.xml.dist` declares `WP_DB_PASS` as empty, which is right for CI and wrong for this
machine; PHPUnit does not override an already-exported variable, so exporting wins.

The suite loads real platform plugins from sibling directories. A platform is loaded only
when StoreSeeder ships a driver for it — see `tests/php/bootstrap.php`. Tests needing an
absent platform skip via `require_platform( $id )`.

**Current baselines: 700 PHP tests and 251 Jest tests (`yarn test:unit`).** For any refactor
claiming no behaviour change, the *test* count must come back identical, not merely green — a changed
count means a reference was missed.

The assertion count is deliberately not a baseline. Several generator tests assert once per generated
item, and how many items an entity carries is drawn at random, so the total moves between runs on
unchanged code: three consecutive runs of `tests/php/src/Generators` gave 5126, 5153 and 5165. Quoting
an assertion figure invites chasing a difference that means nothing.

The PHP figure is the sum of per-directory runs — `vendor/bin/phpunit tests/php/src/<dir>` for
each of `Generators`, `Rest`, `Platform`, `MCP`, `CLI`, `Generation`, `Controllers`, `Recipes`,
plus the nine files at the root of `tests/php/src/` — which have to be run one at a time, because
one invocation over several of them stalls the same way the whole tree does. One invocation over the whole tree stalls on this
machine once both platform plugins are loaded; the per-directory blocks cover the same files and
take about two minutes.

## Architecture, and the invariants that matter

```
React → REST → Controller → Generator → canonical entity → Writer → platform models
```

```
includes/
  Access.php      one capability gate: menu, REST, MCP, AJAX
  Generation/     Generator.php (abstract)   + Generators/*    21 generators
                  Ledger.php  Purge.php        what was created, and undoing it
  Rest/           Controller.php (abstract)  + Controllers/*   21 controllers
                  Registry.php               owns storeseeder_rest_controllers
  CLI/            Command.php (abstract)     + Commands/*       6 commands
                  Registry.php               owns storeseeder_cli_commands
  MCP/            MCP_Server.php
                  Registry.php               owns storeseeder_mcp_abilities
                  Settings.php               three switches, gate at registration
                  Ability.php (abstract)     + Abilities/*     21 abilities, 42 tools
  Recipes/        Recipe.php    one validated manifest
                  Registry.php  owns storeseeder_recipes; audits a download against its claims
  Platforms/      Platform_Interface.php  Platform_Driver.php  Writer.php
                  Registry.php  Resolver.php  Capability.php
                  Resource.php  Status.php  Locale.php
                  Drivers/Fluent_Cart/Platform.php + Writers/*      20 writers (tags refused)
                  Drivers/Woo_Commerce/Platform.php + Writer.php + Writers/*  18
```

Directories are named for the layer, not for what is inside them: each abstract sits at the
layer root and its concrete children in a plural directory beneath it.
`class-storeseeder.php` stays at the repo root, global namespace, loaded by classmap.

### Four rules that are load-bearing

**1. A generator may not name a platform.** No models, no table names, no platform-specific
status strings, no database reads. `build_entity()` is FakerPHP and loaded sample data only.
This is what lets one generator feed every platform and lets a fixed seed produce the same
data everywhere.

**2. Money in a canonical entity is an integer in the currency's minor unit.** Never a float —
binary rounding on a price is a real bug and an invisible one. Fluent Cart stores cents so its
writers pass it through; WooCommerce, StoreEngine and EasyCommerce want decimals, so those
writers divide.

**3. Statuses use the canonical vocabulary in `Platforms/Status.php`**, mapped per writer.
The canonical names are deliberately no single platform's spelling — WooCommerce needs a
`wc-` prefix, Fluent Cart does not.

**4. Foreign keys and uniqueness belong to the writer, not the generator.** An order needs
real product variations to have line items and their real prices to have a total; a generator
proposes an SKU and the platform that owns the unique index checks it and re-rolls. So a
writer legitimately reads before it writes. What it must never do is invent a name, address,
date or quantity — those arrive on the entity, already localised.

### A driver may refuse a resource, and must say why

WooCommerce is the first driver that cannot do everything, and the shape of that answer is
load-bearing. `Capability::unsupported( $reason )` is for a concept the platform does not have —
WooCommerce records payment on the order, so there is no transaction record to create, and no
plugin changes that. `Capability::missing_extension( $slug, $label )` is for one that a plugin
would enable, like subscriptions. The two read differently to a user: one is a dead end, the
other is a link. Never use the first where the second is true.

The matrix and `writer_classes()` have to agree. A driver that claims a resource and ships no
writer reports `storeseeder_missing_writer` at generate time — after the user chose it.

### Capabilities are computed, never cached

`Platform::supports()` runs per request. Support is conditional: WooCommerce has no
subscriptions until WooCommerce Subscriptions is active, StoreEngine gates several resources
behind addons. Caching the matrix in an option would make plugin activation fail to register.
An unsupported resource reports *which plugin would enable it*, so the UI can say "install
WooCommerce Subscriptions" rather than dimming a tile in silence.

### Extension points

`storeseeder_platforms` is the whole surface needed to add a platform — append an object
implementing `Platform_Interface` (extending `Platform_Driver` is shortest) and the
generators, REST API and admin pick it up. Also: `storeseeder_platform_writers_{id}`,
`storeseeder_platform_supports_{id}`, `storeseeder_canonical_{resource}`,
`storeseeder_target_platform`, and `storeseeder_before_write_{platform}_{resource}` /
`_after_write_`. Cross-cutting ones: `storeseeder_capability` (one gate for menu, REST, MCP and
AJAX), `storeseeder_locales`, `storeseeder_canonical_entity` and `storeseeder_rest_params` (the
all-resources counterparts of the `_{resource}` / `_{base}` filters, running before them),
`storeseeder_platform_fields_{id}`, `storeseeder_mcp_ability_definition`, `storeseeder_mcp_settings`,
`storeseeder_purge_order`, `storeseeder_recipes`, `storeseeder_recipe_directories`,
`storeseeder_recipes_source`, `storeseeder_platform_admin_url_{id}`,
`storeseeder_admin_payload`,
`storeseeder_sample_data_source`. Full table in `docs/guides/architecture.md`.

### A declared parameter must change the output

The Products generator declared six parameter groups and read one. `price_range` was the worst:
the admin offered a min and a max, and every product came out between 9.99 and 999.99. Anything
declared in `generators.ts`, a controller's `get_resource_specific_params()` or an MCP ability's
`input_properties()` has to reach `build_entity()` or a writer — and all three surfaces have to
agree, because they are three declarations of one contract.

When a parameter cannot be honoured, remove it. `Capability::supported_except()` is for a field one
*platform* cannot store, not for one nothing implements — and not for one whose *intent* was met.
Fluent Cart's `shipping_total` is NOT NULL, so it cannot tell an unshipped order from a free-shipped
one, but switching shipping off still charges nothing; listing the field would tell the caller their
request was dropped when it was not, and a false alarm is worse than the lost nuance.

Two parameters that survived three surfaces without ever being readable, for the shape of the
mistake: `order_value_range` asked for a total range, and a total is the sum of the catalogue prices
of the products the order points at — both writers price line items from the real product, because an
order line at $412 for a $19 product makes every revenue figure disagree with the store. And
`customer_type` / `customer_distribution` described a new-versus-existing split no writer implemented;
`customer_id` replaced them, which is the part that was useful.

### A platform-specific field is a parameter, never an entity field

`Platform_Driver::fields( $resource )` returns JSON Schema fragments only that driver understands
— WooCommerce's `tax_status`, Fluent Cart's `payment_type`. They arrive as *generation parameters*
and its writer reads them from `$this->params`; they never touch the canonical entity, because a
field only one platform stores would make a fixed seed produce different data on the others.

Routes register on `rest_api_init`, before any platform is resolved, so the endpoint accepts the
**union** of every driver's fields and the response reports what the resolved target could not use
(`ignored`). Compare against what the request *sent*, never the merged parameters — every declared
argument with a default is present regardless, so the naive check reports every other platform's
field on every run.

Declaring a field the writer does not read recreates the `include_images` bug, which is the whole
reason this seam exists. `Capability::supported_except( array $fields )` is the other half: a
platform that stores a resource but not all of its canonical fields says which, and the admin
prints it instead of dropping them silently.

### Recipes: one path, computed once

A recipe is a vocabulary — words and numeric bands — downloaded to
`uploads/storeseeder-recipes/`, never bundled. Three things in this area have already broken once
each, all in the same way: **two computations of one value that silently stopped matching.**

- `Generator::sample_data_candidates()` built the recipe path itself while `Recipes\Registry`
  built another. When recipes moved out of the plugin the registry followed and the generator did
  not, so every recipe ran to completion on default vocabulary and the audit — reading the *correct*
  path — reported no issues. Ask the registry; never derive the path.
- `Product::build_entity()` named products from vocabulary and `build_preview_row()` from
  `words( 3 )`, so the preview showed Lorem for a run that produced real names. A shown value that a
  parameter governs must come from the same helper the entity uses.
- `apply_recipe_params()` used `$params + $recipe`, which loses to any parameter with a schema
  default — and `price_range` has one. Compare against what the request **sent**, the way the
  `ignored` report does.

None of the three crashed. All produced plausible data, which is why a test that asserts the
*agreement* rather than a particular value is the only kind that catches them —
`Recipes\VocabularyPathTest` is the shape.

`WP_Filesystem::move()` defaults to `$overwrite = false`, so an archive re-sync silently kept every
stale file until this was passed explicitly. That is also why the sample data grew a "Force re-sync"
that deletes directories first.

### Deleting generated data means the ledger, never a heuristic

`Generation\Ledger` records `(platform, resource, object_id)` for every successful write — from
`Generator::write_entity()`, once, rather than in eighteen writers that could each forget — and
`Generation\Purge` walks it, handing each id to `Writer::delete()`. Nothing else is ever a
candidate: matching on "looks like test data" would eventually delete a real catalogue on a
staging site restored from production, and that is not a bug you can apologise your way out of.
`Writer::delete()` is concrete and refuses by default, because making it abstract would break
every third-party writer. A refusal keeps the ledger row — forgetting it would leave the row in
the store with nothing left that knows StoreSeeder put it there.

### Two MCP tools per generator, gated at registration

`preview-<resource>` is read-only, `generate-<resource>` writes. `MCP\Settings` holds three
switches (surface / preview / generate) and `Registry::tools()` applies them, so a withdrawn
tool is never registered rather than registered-and-refusing — the strongest form the promise
can take. Definitions carry `meta.mcp.public`, which is what makes them work through
mcp-adapter's own default server as well as StoreSeeder's endpoint; dropping it silently
breaks every client pointed at `/wp-json/mcp/mcp-adapter-default-server`.

## Conventions that differ from what you would guess

- **PHP indents with tabs**, per WPCS — even though `.editorconfig` says 4 spaces. The
  `.editorconfig` is wrong for PHP; follow the code.
- **PHP methods and variables are `snake_case`** (`build_entity`, `get_resource_type`), not
  camelCase.
- **PHP filenames are PascalCase with underscores** (`Order_Tax_Rate.php`,
  `Platform_Driver.php`), a PSR-4/WPCS hybrid permitted by two sniff exclusions in
  `phpcs.xml`. Do not "fix" them to `class-*.php`.
- **TypeScript indents with 2 spaces.**
- **`tsconfig.json` targets ES2020 with `moduleResolution: "bundler"`.** TypeScript 6
  deprecated `target: es5`, `moduleResolution: node` and `baseUrl`, and the old `lib: es6` had
  been hiding that the code uses `Object.entries`, `Array.includes` and `flatMap` — Babel
  transpiles without consulting those types, so only `tsc` ever knew. A side-effect CSS import
  needs `src/types/css.d.ts`; `bundler` resolution no longer allows an untyped one.
- **PHP floor is 7.4.** No union return types, `match`, enums, constructor promotion, or
  readonly. Document `array|WP_Error` in a docblock and omit the return type, as the existing
  abstracts do.
- **`curly` is `['error', 'multi-line']`,** not `all`. Single-line guards like
  `if (!open) return null;` are the house style, and no Prettier pass runs behind `--fix`, so
  `all` rewrites them to `if (!open) {return null;}` and leaves them that way.
- **Font weights are round hundreds only** — 400, 500, 600, 700. No 350/450/550.
- **REST bases and canonical resource names are different key spaces.** `cart_session` is
  served at `cart-sessions`, `tax_class` at `tax_classes`. No singularisation rule survives
  `shipping_classes`, so generators carry an explicit `resource` alongside `route`. Never
  derive one from the other.

## Traps

- **`$resource` as a parameter name fails PHPCS** — WPCS treats `resource` as reserved. Use
  `$resource_type`.
- **`phpcs.xml` scans `includes/` only.** `class-storeseeder.php` and `storeseeder.php` are
  not checked by it (only by `phpcs.plugin-review.xml`), so a violation there passes CI.
- **`wp i18n make-pot` cannot read `.tsx`.** Admin strings are extracted from the compiled
  bundle, which is why `composer makepot` guards on `build/admin-app.js` existing.
- **The webpack entry is named `admin-app`, not `admin`, on purpose.** wp-cli mangles any
  bundle whose name ends in `min.js` (`admin.js` → `a.js`), which breaks core's
  `load_script_textdomain()` md5 lookup. Do not rename it.
- **There is no `Requires Plugins` header.** It was removed deliberately: it made WordPress
  refuse activation without Fluent Cart, so no other platform could ever be reached. Do not
  add it back.
- **`tests/e2e/setup.sh` resets the admin password.** Never run it, or `yarn test:e2e`
  against a site whose credentials matter, without asking first.
- **`tests/assets/` writes image files; it asserts nothing.** Four generators live there — the
  WordPress.org listing's banners and screenshots, the documentation site's banner and
  screenshots — with their helpers (`brand.ts`, `admin.ts`, `recipe-art.ts`) beside them. They
  sit outside `tests/e2e/` deliberately: when they lived in `tests/e2e/specs/assets/` the base
  config needed a `testIgnore` to stop an ordinary `yarn test:e2e` from overwriting shipped
  artwork with whatever Faker had generated that minute, and a rule like that holds only while
  somebody remembers it. Drive them through `playwright.assets.config.ts` — one config, four
  projects — or the `yarn shots:*` scripts.
- **The capability gate is `StoreSeeder\Access`, not a literal `manage_options`.** Four
  surfaces check it — admin menu, REST, MCP, AJAX — and a site that grants the routes but not
  the page has a broken plugin. It lives at the root of `includes/` rather than in a layer
  directory because it belongs to none of them.
- **The platform plugins have PHPStan stubs; do not blanket-ignore their symbols.**
  `phpstan.neon` scans `mralaminahamed/{fluent-cart,fluent-cart-pro,easycommerce,storeengine}-stubs`,
  which replaced four `#.*FluentCart\\.*#` ignores that hid every symbol in the driver — a wrong
  method name in a writer included. Eloquent's `create()` types as `Builder|Model` through
  `__callStatic`, so narrow it with `instanceof` rather than a truthiness check, and use
  `Model::query()->create()` rather than the static `Model::create()`.
- **Never write a locale list by hand.** `Platforms\Locale` is the only one; PHP reads it there
  and TypeScript reads the codes the server inlines (`src/lib/locales.ts`). A second list is how
  the admin came to offer 73 while the REST enum accepted 6 — and because `Factory::create()`
  falls back to `en_US` in silence, the other 67 produced English with no error. Adding a locale
  means FakerPHP ships a provider for it; `LocaleTest::test_list_matches_fakerphp_exactly()`
  fails otherwise.
- **A CLI command's flags are limited by its synopsis.** WP-CLI rejects anything not
  declared, so resource-specific parameters need a `generic` synopsis entry — without it
  `--product_type=digital` never reaches the plugin. And `WP_CLI::error()` exits without
  PHPStan knowing, so each guard needs an explicit `return;`.
- **`Controller::get_rest_base()` and `get_resource_type()` are protected.** Use the public
  `rest_base()` / `resource_type()` counterparts from outside; calling the protected ones
  fatals, and under WP-CLI that surfaces as an exit 255 with no message at all.
- **Jest owns `*.test.ts(x)`, Playwright owns `*.spec.ts`.** Both would otherwise collect
  the other's files, and a Playwright spec under Jest fails with a confusing error about
  `test.describe`. Jest tests sit **beside their source** (`src/lib/locales.test.ts`), not
  in a parallel tree; PHP tests stay under `tests/php/`, mirroring `includes/`.
- **Four pages share one measure.** Overview, Recipes, Our Plugins and Settings all wrap in
  `.fp-page wide fp-enter`. Settings was 1180 and Recipes was full-width, so content jumped
  horizontally as you moved between them. A page-level wrapper is not optional: `.fp-scroll`
  carries no padding of its own.
- **Headings go through `<PageHead>`.** Four pages had hand-rolled
  `fp-page-head > h1.fp-h1 + p.fp-sub` and a fifth invented `fp-page-title` / `fp-page-sub`, which
  match nothing — so the heading rendered as 13px body text and no linter noticed.
- **`<Button>` with no `variant` is `outline`, not primary.** `toFpVariant(undefined)` returns
  `"outline"`, so a page's main action drew as a secondary. State the variant, the size and
  `type="button"`; pass the icon through the `icon` prop so the component sizes it.
- **A destructive action gets `<ConfirmDialog>`.** Cancel takes focus, Tab is trapped, and Escape
  is refused while the action runs. Two actions in the danger zone previously had no confirmation
  at all.
- **`docs/superpowers/` is gitignored.** Specs and plans written there are local only.
- **Sample data and recipes live in separate repos** and download only after an administrator
  accepts the consent prompt. That prompt is the only thing granting permission — see
  `docs/guides/external-services.md`, and keep readme.txt in agreement with the code. One consent record
  covers both; asking twice for the same answer trains people to click through prompts.
- **A test method may not narrow a WordPress base method.** `WP_UnitTestCase_Base` declares a
  public `rmdir()`, and `tearDown()` is final — redeclaring either is a *compile-time* fatal that
  PHPUnit reports as exit 255 with no message, no failure list and nothing in the error log.
  Override `set_up()` / `tear_down()`, and give helpers names the base does not already use.

## When changing a generator

A resource needs six pieces:

1. `includes/Generation/Generators/` — extends `Generation\Generator`, implements
   `build_entity()`
2. `includes/Platforms/Drivers/<Platform>/Writers/` — extends `Platforms\Writer`
3. That driver's `writer_classes()` and `capabilities()`
4. `includes/Platforms/Resource.php` — the canonical name
5. `includes/Rest/Controllers/` — extends `Rest\Controller`, listed in
   `Rest\Registry::default_classes()` (or added through `storeseeder_rest_controllers`)
6. `src/lib/generators.ts` — admin registration, parameter schema, and the `resource` key

A driver that declares support but ships no writer is reported as
`storeseeder_missing_writer` rather than failing once per item.
