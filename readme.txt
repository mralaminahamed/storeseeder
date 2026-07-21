=== Fluent Cart FakerPress ===
Contributors: mralaminahamed
Tags: test data, dummy data, sample data, demo content, faker
Requires at least: 6.5
Tested up to: 6.8
Requires PHP: 7.4
Requires Plugins: fluent-cart
Stable tag: 2.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generate realistic Fluent Cart test data with 17 generators, a modern React admin, schema-driven configuration, and optional MCP integration.

== Description ==

Fluent Cart FakerPress generates realistic test data for the Fluent Cart e-commerce platform. It helps developers, agencies, and store owners build sophisticated datasets for testing, demos, and performance evaluation — without hand-crafting records.

All data is created through native Fluent Cart models, so generated records respect the same validation, relationships, and business logic as real data and stay compatible with Fluent Cart updates.

**Highlights**

* **17 generators** — products, product variations, customers, orders, transactions, refunds, coupons, shipping plans, shipping classes, tax classes, order tax lines, attributes, cart sessions, labels, product downloads, subscriptions, and activity logs.
* **Modern admin** — a single-page React app (React Router v7, Tailwind CSS v4, lucide icons) that adapts to your WordPress admin color scheme.
* **Live preview** — a read-only preview of real faker rows that refreshes as you change settings, without persisting anything.
* **Schema-driven configuration** — each generator renders its own fields from a parameter schema: nested options, ranges, toggles, and intelligent defaults.
* **REST API** — every generator is exposed at `fluent-cart-fakerpress/v1/<resource>/generate` for programmatic use.
* **Optional MCP integration** — expose generators as AI tools via the WordPress Abilities API (see below).
* **Extensible** — filters and actions cover the full generation lifecycle.

**Generators**

* **Products** — pricing, inventory, and content, created as a sellable product (post, product detail, and variation rows).
* **Product Variations** — additional priced variations attached to existing products, with unique SKUs.
* **Customers** — demographics, billing and shipping addresses, purchase history, and contact preferences.
* **Orders** — line items, billing and shipping addresses, applied coupons, payment, shipping, tax, and status distribution.
* **Transactions** — payment transactions tied to real orders.
* **Refunds** — full and partial refunds created against existing charge transactions.
* **Coupons** — discount types, usage limits, validity windows, and restrictions.
* **Shipping Plans** — shipping methods and zones.
* **Shipping Classes** — groups of products with similar shipping requirements.
* **Tax Classes** — tax classes with geographic rate rows.
* **Order Tax Lines** — per-order tax lines linking orders to tax rates.
* **Attributes** — attribute groups and terms, linked to real product variations.
* **Cart Sessions** — abandoned and active cart sessions.
* **Labels** — labels (tags) attached to existing orders and customers.
* **Product Downloads** — downloadable files for products, with download permissions on existing orders.
* **Subscriptions** — subscription records against existing orders (active billing requires Fluent Cart Pro).
* **Logs** — activity log entries across orders, products, customers, and system events.

**Model Context Protocol (MCP) Integration**

The plugin can optionally expose every generator as an MCP tool so AI clients (e.g. Claude Desktop, IDE assistants) can generate data with natural language. This requires the WordPress Abilities API (bundled in WordPress 6.9+, or installable separately) and the `mcp-adapter` plugin. MCP is entirely optional and degrades gracefully — the plugin works normally when these dependencies are absent.

== Installation ==

= Automatic Installation =
1. Go to **Plugins → Add New** in your WordPress admin.
2. Search for "Fluent Cart FakerPress".
3. Click **Install Now**, then **Activate**.
4. Open the new **FC FakerPress** menu item.

= Manual Installation =
1. Download the plugin ZIP.
2. Upload it to `/wp-content/plugins/fluent-cart-fakerpress/`.
3. Activate via the **Plugins** screen.
4. Open the **FC FakerPress** menu.

= Development Setup =
1. Clone: `git clone https://github.com/mralaminahamed/fluent-cart-fakerpress.git`
2. Install dependencies: `composer install && yarn install`
3. Build assets: `yarn build`
4. Activate the plugin.

= Requirements =
* WordPress 6.5+
* PHP 7.4+ (8.0+ recommended)
* Fluent Cart plugin (active) — enforced by the `Requires Plugins` header
* 256MB memory minimum (512MB for large datasets)
* MCP integration (optional): WordPress Abilities API + `mcp-adapter` plugin

== Frequently Asked Questions ==

= How does Fluent Cart integration work? =
Generators persist through native Fluent Cart Eloquent models, preserving validation, relationships, and business logic. Raw database writes are avoided so generated data behaves like real data.

= How many generators are included? =
Seventeen: products, product variations, customers, orders, transactions, refunds, coupons, shipping plans, shipping classes, tax classes, order tax lines, attributes, cart sessions, labels, product downloads, subscriptions, and activity logs.

= Some generators need existing data. Why? =
Several generators build on others: orders need products and customers; refunds need charge transactions; order tax lines need orders and tax rates; product downloads and subscriptions need products and orders. Generate the prerequisites first, and each generator reports clearly when something is missing.

= Do subscriptions require Fluent Cart Pro? =
The subscriptions table ships in Fluent Cart core, so records are generated as fixtures without Pro. Active billing and management require Fluent Cart Pro and a subscription-capable gateway.

= Is it safe for production? =
Use only in development or staging. Always back up your database before generating, and start with small batches.

= Can I customize generation? =
Yes. The plugin fires filters and actions across the generation lifecycle — modify parameters, transform generated items, and customize REST responses.

= What is the MCP integration for? =
It exposes generators as AI tools via the WordPress Abilities API, so an MCP-capable assistant can create test data conversationally. It is optional and off unless the Abilities API and `mcp-adapter` are present.

= How do I remove generated data? =
Use WordPress/Fluent Cart deletion tools or a cleanup plugin. Back up before removing.

== Screenshots ==

1. Modern React admin with a generator grid and WordPress color-scheme integration.
2. Schema-driven generator page with live configuration and preview.
3. Customer generator with demographics and address options.

== Changelog ==

= 2.1.0 =
* **New generators** — added Shipping Classes, Labels, Order Tax Lines, Product Downloads, and Subscriptions, bringing the total to 17.
* **Data-integrity overhaul** — every generator now produces valid, sellable, correctly-priced records against Fluent Cart 1.5.0: products create the full post + detail + variation rows, orders carry real addresses and applied coupons, customers get address books, tax classes get real rate rows, and attributes link to real variations.
* **Removed** — the Location generator, which had no corresponding Fluent Cart entity.
* **Fixes** — money stored in the correct units (cents), valid status and type values, real foreign keys, and a TypeError that could abort a whole customer batch.
* **Docs & tooling** — rewritten README, refreshed logo and banners, and a restructured PHPUnit suite covering every generator and controller.

= 2.0.0 =
* **New React single-page admin** — sidebar shell, dashboard, schema-driven generator pages, command palette, batch tray, and toasts (React Router v7, Tailwind CSS v4).
* **13 generators** — added Attributes, Refunds, and Logs, alongside products, variations, customers, orders, transactions, coupons, shipping plans, tax classes, cart sessions, and locations.
* **Optional MCP integration** — generators exposed as MCP tools via the WordPress Abilities API + `mcp-adapter` (graceful no-op when absent).
* **REST parity** — every generator served at `fluent-cart-fakerpress/v1/<resource>/generate`, with admin routes aligned to the REST bases.
* **Fix** — corrected the Fluent Cart dependency guard (`FLUENTCART_VERSION`) that previously prevented generators from running.

= 1.0.0 =
* Initial release: product and customer generators, React admin, REST API, hook system.

== Upgrade Notice ==

= 2.1.0 =
Adds five generators (17 total), overhauls data integrity against Fluent Cart 1.5.0, and removes the Location generator. Rebuild assets (`yarn build`) if installing from source.

= 2.0.0 =
Major release: new React admin, 13 generators (Attributes/Refunds/Logs added), and optional MCP integration. Rebuild assets (`yarn build`) if installing from source.

== External services ==

This plugin connects to two external services. Neither is contacted on activation, and no personal or store data is ever transmitted.

**1. GitHub — sample data repository**

The Settings page offers an optional "Sample Data Sync" action that downloads locale-specific reference data (product names, addresses, customer tags) used to make generated content more realistic.

Service: GitHub
Endpoint: [https://github.com/mralaminahamed/fluent-cart-fakerpress-sample-data/archive/refs/heads/trunk.zip](https://github.com/mralaminahamed/fluent-cart-fakerpress-sample-data/archive/refs/heads/trunk.zip)
When data is sent: Only when an administrator clicks "Sync Sample Data" on the plugin Settings page, or when a generator requires sample data that has not been downloaded yet.
Data sent: An unauthenticated HTTP GET request. No site, user, or store data is included — only the request itself (and the IP address and user agent inherent to any HTTP request).
Terms of Service: [https://docs.github.com/en/site-policy/github-terms/github-terms-of-service](https://docs.github.com/en/site-policy/github-terms/github-terms-of-service)
Privacy Policy: [https://docs.github.com/en/site-policy/privacy-policies/github-general-privacy-statement](https://docs.github.com/en/site-policy/privacy-policies/github-general-privacy-statement)

**2. WordPress.org — plugin directory API**

The "Our Plugins" admin page lists the plugin author's other WordPress.org plugins with live ratings and install counts.

Service: WordPress.org Plugin Directory API
Endpoint: [https://api.wordpress.org/plugins/info/1.2/](https://api.wordpress.org/plugins/info/1.2/)
When data is sent: Only when an administrator opens the "Our Plugins" page in the plugin admin. The request is made by the browser.
Data sent: A query for plugins by the author "mralaminahamed". No site, user, or store data is included — only the request itself (and the IP address and user agent inherent to any HTTP request).
Terms of Service: [https://wordpress.org/about/](https://wordpress.org/about/)
Privacy Policy: [https://wordpress.org/about/privacy/](https://wordpress.org/about/privacy/)

== Source code ==

The minified JavaScript and CSS in `build/` is compiled from the TypeScript and CSS sources in `src/`, which are not included in the distributed plugin package. The complete, human-readable source is public:

[github.com/mralaminahamed/fluent-cart-fakerpress](https://github.com/mralaminahamed/fluent-cart-fakerpress)

Build steps:

`composer install`
`yarn install`
`yarn build`

Build tooling: webpack (via @wordpress/scripts), TypeScript, and Tailwind CSS. Configuration files (`webpack.config.js`, `tsconfig.json`, `postcss.config.js`) are in the repository root.

== Other Notes ==

**Privacy**

All generated data is stored in your own WordPress database and is never transmitted anywhere. Generated content is fictional and does not represent real individuals or transactions. The plugin does not collect analytics and does not phone home.

The plugin makes two outbound requests, both administrator-initiated and both carrying no site data — see the "External services" section for the full disclosure.

**Contributing**

Development happens on [GitHub](https://github.com/mralaminahamed/fluent-cart-fakerpress). Report bugs and request features on the [issue tracker](https://github.com/mralaminahamed/fluent-cart-fakerpress/issues), and read the [development guide](https://github.com/mralaminahamed/fluent-cart-fakerpress/blob/trunk/docs/development.md) before opening a pull request.
