# 📋 Changelog

All notable changes to StoreSeeder are documented in this file. **This is the complete history and
the canonical record** — [`readme.txt`](readme.txt) carries only the four most recent releases, in
the WordPress plugin directory format, and links back here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

Nothing yet.

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
