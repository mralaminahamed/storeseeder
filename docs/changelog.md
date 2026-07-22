# 📋 Changelog

All notable changes to StoreSeeder will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Sample data now downloads only after an administrator accepts a one-time consent prompt shown on the plugin admin page (or via Sync on Settings). The prompt explains what is fetched and that no site data is sent; declining still leaves generators working via built-in defaults. The decision is site-wide and changeable from Settings.

### Changed

- **Breaking:** the plugin no longer downloads sample data automatically when the admin page loads; the download is now consent-gated and REST-driven.

### Security

- The sample-data importer now validates every archive entry before extraction, rejecting absolute paths and `..` traversal segments so a crafted ZIP cannot write outside the sample-data directory (zip-slip / path traversal).

## [1.0.0] - 2026-07-21

Initial release.

### Added

- **17 generators**, each with a REST controller and an optional MCP tool, all persisting through native Fluent Cart models: Products, Product Variations, Customers, Orders, Transactions, Refunds, Coupons, Shipping Plans, Shipping Classes, Tax Classes, Order Tax Lines, Attributes, Cart Sessions, Labels, Product Downloads, Subscriptions, and Logs.
- Modern single-page React admin (React Router v7, Tailwind CSS v4) with a live preview, command palette, and batch queue.
- REST API — every generator at `storeseeder/v1/<resource>/generate`.
- Optional Model Context Protocol (MCP) integration via the WordPress Abilities API + `mcp-adapter`.
- Filters and actions across the generation lifecycle.
