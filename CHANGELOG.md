# 📋 Changelog

All notable changes to StoreSeeder are documented in this file. **This is the complete history and
the canonical record** — [`readme.txt`](readme.txt) carries only the four most recent releases, in
the WordPress plugin directory format, and links back here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- **StoreSeeder is no longer a Fluent Cart plugin.** Where data lands is now decided by a
  *platform driver*, and the same seventeen generators feed every driver — a generator names no
  platform, so a fixed seed produces identical data wherever it is written. Fluent Cart is the
  driver shipped; `storeseeder_platforms` is the whole surface needed to add another from a
  separate plugin. The target is chosen in the topbar and in Settings, stored site-wide so two
  administrators cannot unknowingly seed different stores, and with more than one platform active
  the generator page asks rather than guessing — writing rows into the wrong store is not a
  failure anyone would notice afterwards.
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
