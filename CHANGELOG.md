# 📋 Changelog

All notable changes to StoreSeeder are documented in this file. **This is the complete history and
the canonical record** — [`readme.txt`](readme.txt) carries only the four most recent releases, in
the WordPress plugin directory format, and links back here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.0] - 2026-08-01

### Added

- **Recipes.** A new page below Overview builds a whole shop in one click — a corner grocer, a
  fashion boutique, a home & garden store — rather than a resource at a time. StoreSeeder could
  already generate any volume; what it could not do was make two hundred products that look like
  one business. A recipe is the *vocabulary* that fixes that: product names, a category tree, brand
  names, tag labels, a price band and variation axes, applied across nine resources in dependency
  order. It is words and numbers, never records, so every generator invariant still holds and every
  existing parameter still works on top of one.
- **Three recipes ship**, from
  [storeseeder-recipes](https://github.com/mralaminahamed/storeseeder-recipes), downloaded once
  behind the consent prompt that already governs the sample data. A separate repository so that
  adding a shop type — or translating one — needs no plugin release.
- **`storeseeder_recipes`** registers a recipe from your own plugin with no download at all, and
  **`storeseeder_recipe_directories`** says where its words live. `storeseeder_recipes_source`
  repoints the archive at a fork.
- **`wp storeseeder recipe list` and `wp storeseeder recipe run <id>`**, running the same ordered
  plan through the same endpoints as the admin.
- **Undo a whole recipe.** Every row a run writes carries a run id in the ledger, so removing one
  is a single button rather than nine separate purges. `wp storeseeder cleanup --run_id=` does the
  same from the command line.

### Fixed

- **Seventy-two of seventy-three locales were generating "Widget" and "Gadget".**
  `Generator::get_sample_data_path()` built one path and stopped, so any locale without a sample
  data file fell through to the inline literals each generator carried, with a `WP_DEBUG_LOG`
  warning as the only sign. Vocabulary now falls back to `en_US` — and when a recipe advertises a
  locale it does not actually ship, the admin says so *before* the run instead of quietly serving
  English.
- Categories, tags and brand names were hardcoded constants and could not vary by shop type. They
  read from vocabulary now, with the constants kept as the offline fallback.

### Changed

- The dashboard's four stat tiles match the generator tiles: same size, radius and accent. The
  per-card hues implied a distinction the data does not have, and the inline tint had no
  dark-theme step, so the tiles came out visibly washed out beside the generator grid.
- "Fake data" is "test data" throughout — in the docs, the npm keywords, and the permission error a
  user actually reads. Fake means counterfeit; these are real rows in real tables.

## [1.1.0] - 2026-08-01

### Added

- **StoreSeeder is no longer a Fluent Cart plugin.** Where data lands is now decided by a
  *platform driver*, and the same twenty-one generators feed every driver — a generator names no
  platform, so a fixed seed produces identical data wherever it is written. Fluent Cart and
  WooCommerce both ship; `storeseeder_platforms` is the whole surface needed to add another from a
  separate plugin. The target is chosen in the topbar and in Settings, stored site-wide so two
  administrators cannot unknowingly seed different stores, and with more than one platform active
  the generator page asks rather than guessing — writing rows into the wrong store is not a
  failure anyone would notice afterwards.
- **A WooCommerce driver**, covering eighteen of the twenty-one resources through the platform's own
  CRUD objects — `WC_Product`, `WC_Order`, `WC_Customer`, `WC_Coupon` — rather than direct writes, so
  generated rows go through the same validation real ones do. Subscriptions need WooCommerce
  Subscriptions and say so; transactions, labels and licences are reported unsupported with the
  reason, because WooCommerce has no equivalent and no plugin changes that.
- **Four new resources**: product categories (nested where asked), product tags, brands (optionally
  nested as sub-brands), and licences. Fluent Cart has categories and brands but no tag taxonomy at
  all, and refuses tags rather than registering one of its own — terms that were real and unreachable
  from any Fluent Cart screen would be worse than none.
- **A cleanup that works from a ledger, not a guess.** Every successful write records
  `(platform, resource, id)` in `{prefix}storeseeder_generated`, and Settings → Danger zone (or
  `wp storeseeder cleanup delete`) walks that list, children before parents, with a per-resource
  breakdown before anything goes. Nothing is ever matched on for *resembling* test data: on a staging
  site restored from production that guess eventually deletes a real catalogue.
- **Two MCP tools per generator instead of one** — a read-only `preview-<resource>` beside
  `generate-<resource>` — with three switches in Settings (surface, preview, generate) applied at
  *registration*, so a withdrawn tool is never offered rather than offered-and-refusing.
- **Every declared parameter now reaches the generator or a writer.** Across the eight resources
  audited, forty-nine parameters were declared on the admin, the REST schema or the MCP ability and
  read by nothing: a price range that never moved a price, an item count that never changed an order,
  a guest ratio fixed at 30%, a loyalty tier that ignored the tier asked for. Where a parameter could
  not be honoured it was removed rather than left decorative — `order_value_range` and
  `cart_value_range` cannot be met once line items are priced from the catalogue, and
  `calculation_methods` named three shipping calculations neither platform has. Where three surfaces
  had drifted into three vocabularies for one idea, they now share one; the names that shipped keep
  working as synonyms.
- Canonical vocabularies for transaction states and types and for cart stages, beside the order
  statuses already in `Platforms\Status`, each mapped per writer — Fluent Cart spells completed
  `succeeded`, disputed `dispute_lost`, and an abandoned cart `intended`.
- Capability support is **computed per request, never cached**, because it is conditional:
  WooCommerce has no subscriptions until WooCommerce Subscriptions is active, and StoreEngine
  gates resources behind addons. An unsupported resource reports which plugin would enable it
  instead of dimming a tile in silence, and the REST API answers 400 rather than reporting
  success and writing nothing.
- `GET /platforms` and `POST /platforms/target` for reading the platform matrix and setting the
  site-wide target.
- **Seventy-five locales**, every one FakerPHP ships a provider for, searchable in the picker by
  language name or locale code.
- **Settings** gains a Target platform card — the site-wide option previously reachable only from
  a topbar dropdown, naming what `Auto` resolves to — and an Appearance card for theme and
  density.
- `storeseeder_capability` sets who may use the plugin, governing the admin menu, every REST
  route, every MCP ability and the AJAX handlers together, so access cannot be widened for one
  surface and left behind on another.
- Filters for the cases where only a per-resource hook existed: `storeseeder_canonical_entity`
  and `storeseeder_rest_params` apply to all seventeen resources and run before their specific
  counterparts. Plus `storeseeder_locales`, `storeseeder_mcp_ability_definition`,
  `storeseeder_admin_payload` and `storeseeder_sample_data_source`.
- The products preview gains a **Type** column, and it reads the run's own `product_type`
  parameter rather than rolling a fresh value — choose `digital` and every previewed row says
  digital, which is the question the control beside it just asked. `mixed` is the only setting
  that varies per row, which is what mixed means.
- **Fluent Cart Pro support, and a licence generator.** The driver now detects Pro, reports it
  as an extension of the platform — `[{ slug, label, active, version }]`, a new
  `Platform_Driver::extensions()` seam that WooCommerce Subscriptions will use the same way —
  and gates the new **licences** resource on it. Pro owns `fct_licenses`, so without Pro there
  is nowhere to write one; the capability reports the plugin by name, so the admin can say
  "install Fluent Cart Pro" and the REST API answers with the same reason instead of failing
  once per item.
  The generator makes licences worth testing against: unlimited and limited tiers, some at
  their activation limit, some expired in the past, some withdrawn while still dated, and
  activation rows for the sites a licence is live on. Eighteen generators now.
- **WP-CLI.** `wp storeseeder generate <resource>` plus `preview`, `platforms`, `locales` and
  `sample-data`, each dispatching through the same REST controller the admin uses — so
  validation, platform resolution and the capability check are one implementation rather than
  three. Any parameter the endpoint accepts works as a flag, including lists
  (`--payment_methods=stripe,paypal`) and objects (`--price_range='{"min":5,"max":500}'`);
  an unknown flag is rejected with the list of parameters that endpoint takes, because
  `--lokale=de_DE` silently generating English is worse than an error. Commands that touch
  store state require `--user`: shell access is not the same as consent to write to this
  site's tables, and this keeps one answer to "who may generate?" across all four surfaces.
- **Translations resolve from the plugin's own `languages/` directory**, for PHP and for the
  React admin. `load_plugin_textdomain()` runs on `init`, and `wp_set_script_translations()`
  now receives the path — without it core looked only in `wp-content/languages/plugins`, so a
  bundled JSON translation, or one Loco Translate wrote beside the plugin, was ignored for
  every admin string. Loco Translate and WPML String Translation both work without
  configuration.
- The plugin screen clears other plugins' admin notices — setup wizards, review nags, upgrade
  prompts — so the interface starts at the top of the page instead of below a stack of messages
  about the rest of the site. Only this screen is affected, and
  `storeseeder_hide_foreign_admin_notices` turns it off.

### Changed

- PHPStan analyses `storeseeder.php` as well, and its `ignoreErrors` list went from 42 patterns
  to 9. Twenty-four were WordPress functions that the WordPress stubs have covered all along,
  and eighteen more suppressed nothing at all — each one a standing offer to swallow the first
  future error that happened to match its wording. `reportUnmatchedIgnoredErrors` is on now, so
  a pattern has to keep earning its place; the three that mask genuinely unreachable branches
  are labelled as findings to revisit rather than left looking like noise.
- The REST API, MCP schemas and admin picker all enumerate locales from one list, so what is
  offered is exactly what generates.
- `includes/` is laid out so the directories state the class hierarchy: each abstract sits at the
  root of the scope it governs, its concrete children one level beneath. Three registries —
  platforms, REST controllers, MCP abilities — replace three hardcoded lists of seventeen, each
  with its own filter.
- Density reaches controls, not only containers: buttons, chips, inputs, selects, badges, pills
  and switches read the same tokens the compact override changes, so their padding and type scale
  with the boxes around them.
- New brand mark: the icon is a single indigo lit like glass rather than a two-colour gradient
  ramp, and the WordPress.org banners and screenshots now share that palette from one source.
- Every clickable row, overlay and field caption is reachable by keyboard. Rows that navigate are
  buttons, the command palette and locale picker are dialogs that close on Escape, the custom
  select announces itself as a combobox, and field captions are bound to the controls they name.
- Font weights use the standard 400/500/600/700 scale, which a non-variable system font can render
  as drawn.

### Fixed

- **Every generated WooCommerce tax class taxed orders twice.** The writer prepended a duplicate of
  the primary rate to a list that already opened with it, and WooCommerce applies one rate per
  priority level — so a priority-1 copy sat beside the real row and both applied.
- **Generated WooCommerce customers had no billing name, company or email.** The address setter read a
  `name` key that a *customer* address has never carried; it has `first_name` and `last_name`. Those
  fields are on the profile screen and in the admin's customer list.
- **Lifetime spend reached Fluent Cart's `ltv` as a float of dollars** where the column is a BIGINT of
  cents, so $1,234.56 was truncated to 1234 and displayed as $12.34. `purchase_value` likewise
  received a bare number where Fluent Cart's own migrator writes a per-currency map.
- **WooCommerce discarded shipping on every generated order.** `calculate_totals()` sums the order's
  shipping *lines*, so a total set before it was overwritten; shipping is a line item now, and the
  discount is applied after the totals run for the same reason.
- **Fluent Cart priced order and cart lines from generated data** rather than the variation each line
  points at, so an order line could read $412 for a product the catalogue sells at $19 — and every
  revenue figure disagreed with the store it came from.
- **Fluent Cart's coupon `stackable` was hardcoded `'no'`**, so no generated coupon could ever combine
  with another, and `start_date` was never set although its own validation requires one whenever an
  end date is present.
- **`created_at` is absent from three Fluent Cart models' fillable lists**, so mass assignment dropped
  it and Eloquent stamped today: a customer "since 2021" registered this morning, a cart abandoned two
  weeks ago was abandoned today, and every transaction on an old order settled now.
- **A SKU-less product variation failed the whole run on Fluent Cart** — `fct_product_variations` has a
  unique index on `sku`, so the second empty string collided. The column is nullable and MySQL allows
  any number of NULLs under a unique index.
- **Every Fluent Cart shipping method went into one "Worldwide Shipping" zone**, so a method was
  available everywhere whatever coverage was asked for. Its zones take `all`, a bare country code, or
  a `selection` with the country list in meta; all three are used now.
- **City and postcode silently failed the whole WooCommerce tax-rate insert.** They are not columns on
  the rate row — they live in `woocommerce_tax_rate_locations` — and passing them to
  `_insert_tax_rate()` errors with "Unknown column 'tax_rate_city'" while the response still reports
  success with zero rates written.
- A converted cart pointed at no order and carried no completion date, so it was marked completed and
  appeared in no revenue figure. A tax rate's priority was a counter in generation order, so the first
  region listed outranked every other regardless of precision. A tax state was two random letters,
  producing codes like "QK" that match nothing at checkout. And
  `price_variation_range.min_percentage` was capped at a maximum of zero, so an all-positive range —
  every variation dearer than the base — was rejected as an invalid parameter.
- **Three writers offered no result filter, and one was named after a resource that no longer
  exists.** Attributes, logs and refunds returned their result with no hook at all, while the
  other fifteen resources had one — so integration code written against "every writer fires
  `storeseeder_{resource}_generation_result`" was wrong for three of eighteen. And the
  shipping-plan writer still fired `storeseeder_shipping_method_generation_result`, left over
  from before the resource was renamed. The hook name is now derived from the writer's own
  resource by `Writer::filter_result()`, so it cannot drift again; the old shipping hook keeps
  firing after the correct one, deprecated, so existing callbacks still run. Two tests fail if
  a writer stops offering the filter or starts hardcoding a name.
- Nineteen hooks were undocumented, including every per-resource result filter and the admin
  filters for notices, menu icon and permalinks. The architecture table is now checked against
  the source.
- `jest.config.js` would have shipped in the release zip; `.distignore` had not been revisited
  since Jest arrived.
- The WordPress.org screenshots and banners were regenerated, and three pieces of copy in them
  had gone stale: the frame caption and banner both said "for Fluent Cart" on a plugin that is
  no longer Fluent-Cart-only, and the banner advertised 17 generators. The banner now names
  WP-CLI, which is new. The Settings screenshot needed more than a re-shoot: the page grew from
  four cards to eight across three sections, so the old whole-page capture clipped a card in
  half — it now captures a frame-shaped viewport with the sidebar collapsed, which is what the
  settings column wants, and the frames carry the product tagline under the wordmark.
- The recorded test baselines in CLAUDE.md and AGENTS.md were two and five commits stale, which
  makes them useless for the one thing they are for — noticing that a refactor changed the
  count.
- The icon set carried `grid`, byte-identical to `dashboard`: one glyph under two names, which
  is the ambiguity the icon pass was meant to remove. Also dropped two dead exports,
  `fetchPlatforms()` and `toLabel()`.
- **The locale picker was a lie.** The admin offered seventy-three locales while the REST API
  accepted six, and FakerPHP falls back to `en_US` without complaining — so sixty-seven of them
  silently produced English. `pt_AO` was offered with no provider behind it, while `ar_EG`,
  `ar_JO` and `en_CA` shipped with faker and were never offered. A test now fails if the list and
  faker's providers drift apart.
- The picker also stored the display *label*, so the chosen value could never be sent to the API
  as-is, and the topbar globe wrote a key nothing read — clicking a locale changed the pill and
  nothing else.
- An unrecognised locale falls back within its own language, preferring that language's own
  country: a `de_LU` site gets `de_DE` rather than the Austrian German that sorted first.
- **The admin interface is translatable.** None of it was, for two independent reasons.
  `wp i18n make-pot` does not read `.tsx` sources, so every string in the React admin — "New
  generation", "Add to batch", the generator descriptions, the settings labels — was absent
  from the translation template; they are now extracted from the compiled bundle, taking it
  from 347 entries to 632. And the bundle was named `admin.js`, which WP-CLI mistakes for a
  minified file: it rewrote the reference to `build/a.js`, so translations would have been
  filed under a name WordPress never looks for. The bundle is `admin-app.js` now.
- The dependency notes on each generator page were passed to the translation function as a
  variable, so they were never extracted and could not be translated.
- Settings stored in the browser are validated field by field when read, so a value left by an
  older version can no longer reach the UI as the wrong type.

## [1.0.0] - 2026-07-22

Initial release.

### Added

- **17 generators**, each with a REST controller and an optional MCP tool, all persisting through native Fluent Cart models: Products, Product Variations, Customers, Orders, Transactions, Refunds, Coupons, Shipping Plans, Shipping Classes, Tax Classes, Order Tax Lines, Attributes, Cart Sessions, Labels, Product Downloads, Subscriptions, and Logs.
- Modern single-page React admin (React Router v7, Tailwind CSS v4) with a live preview, command palette, and batch queue.
- REST API — every generator at `storeseeder/v1/<resource>/generate`.
- Optional Model Context Protocol (MCP) integration via the WordPress Abilities API + `mcp-adapter`.
- Consent-gated sample data: locale-specific reference data downloads from GitHub only after an administrator accepts a one-time consent prompt (or clicks Sync in Settings); no site data is sent, and declining leaves generators working from built-in defaults. The decision is site-wide and changeable from Settings: **Revoke** withdraws permission, and while permission is not granted, **Sync now** reopens the consent prompt instead of downloading — so the question is always attached to the download it governs.
- Filters and actions across the generation lifecycle.

### Security

- The sample-data importer validates every archive entry before extraction, rejecting absolute paths and `..` traversal segments so a crafted ZIP cannot write outside the sample-data directory (zip-slip / path traversal).
