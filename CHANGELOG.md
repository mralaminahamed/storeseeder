# 📋 Changelog

All notable changes to StoreSeeder are documented in this file. **This is the complete history and
the canonical record** — [`readme.txt`](readme.txt) carries only the four most recent releases, in
the WordPress plugin directory format, and links back here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- The plugin screen clears other plugins' admin notices — setup wizards, review nags, upgrade
  prompts — so the interface starts at the top of the page instead of below a stack of messages
  about the rest of the site. Only this screen is affected, and
  `storeseeder_hide_foreign_admin_notices` turns it off.

### Changed

- New brand mark: the icon is a single indigo lit like glass rather than a two-colour gradient
  ramp, and the WordPress.org banners and screenshots now share that palette from one source.
- Every clickable row, overlay and field caption is reachable by keyboard. Rows that navigate are
  buttons, the command palette and locale picker are dialogs that close on Escape, the custom
  select announces itself as a combobox, and field captions are bound to the controls they name.
- Font weights use the standard 400/500/600/700 scale, which a non-variable system font can render
  as drawn.

### Fixed

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
