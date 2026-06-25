=== Fluent Cart FakerPress ===
Contributors: mralaminahamed
Tags: ecommerce, faker, data-generation, testing, development
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generate realistic Fluent Cart test data with 13 smart generators, a modern React admin, schema-driven configuration, and optional MCP integration.

== Description ==

Fluent Cart FakerPress generates realistic test data for the Fluent Cart e-commerce platform. It helps developers, agencies, and store owners build sophisticated datasets for testing, demos, and performance evaluation — without hand-crafting records.

All data is created through native Fluent Cart models, so generated records respect the same validation, relationships, and business logic as real data and stay compatible with Fluent Cart updates.

**Highlights**

* **13 smart generators** — products, product variations, customers, orders, transactions, refunds, coupons, shipping plans, tax classes, attributes, cart sessions, locations, and activity logs.
* **Modern admin** — a single-page React app (React Router v7, Tailwind CSS v4, lucide icons) that adapts to your WordPress admin color scheme.
* **Schema-driven configuration** — each generator renders its own fields from a parameter schema: nested options, ranges, toggles, and intelligent defaults.
* **REST API** — every generator is exposed at `fluent-cart-fakerpress/v1/<resource>/generate` for programmatic use.
* **Optional MCP integration** — expose generators as AI tools via the WordPress Abilities API (see below).
* **Extensible** — filters and actions cover the full generation lifecycle.

**Generators**

* **Products** — pricing, inventory, categories, and content options.
* **Product Variations** — variation sets for variable products.
* **Customers** — demographics, addresses, purchase history, and contact preferences.
* **Orders** — line items, payment, shipping, tax, and status distribution.
* **Transactions** — payment transactions tied to orders.
* **Refunds** — full and partial refunds created against existing successful charge transactions.
* **Coupons** — discount types, usage limits, validity windows, and restrictions.
* **Shipping Plans** — shipping methods and zones.
* **Tax Classes** — tax classes and rates.
* **Attributes** — attribute groups with taxonomy terms.
* **Cart Sessions** — abandoned and active cart sessions.
* **Locations** — geographic location data.
* **Logs** — activity log entries across orders, products, customers, coupons, and subscriptions.

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
3. Run `composer install` in the plugin directory.
4. Activate via the **Plugins** screen.
5. Open the **FC FakerPress** menu.

= Development Setup =
1. Clone: `git clone https://github.com/mralaminahamed/fluent-cart-fakerpress.git`
2. Install dependencies: `composer install && yarn install`
3. Build assets: `yarn build`
4. Activate the plugin.

= Requirements =
* WordPress 5.0+
* PHP 7.4+ (8.0+ recommended)
* Fluent Cart plugin (active)
* 256MB memory minimum (512MB for large datasets)
* MCP integration (optional): WordPress Abilities API + `mcp-adapter` plugin

== Frequently Asked Questions ==

= How does Fluent Cart integration work? =
Generators persist through native Fluent Cart Eloquent models, preserving validation, relationships, and business logic. Raw database writes are avoided so generated data behaves like real data.

= How many generators are included? =
Thirteen: products, product variations, customers, orders, transactions, refunds, coupons, shipping plans, tax classes, attributes, cart sessions, locations, and activity logs.

= Why does the Refund generator say "no eligible transaction"? =
Refunds are created against existing successful charge transactions. Generate some orders/transactions first, then run the Refund generator.

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
3. Customer generator with demographics and loyalty options.

== Changelog ==

= 2.0.0 - June 25, 2026 =
* **New React single-page admin** — sidebar shell, dashboard, schema-driven generator pages, command palette, batch tray, and toasts (React Router v7, Tailwind CSS v4).
* **13 generators** — added Attributes, Refunds, and Logs, alongside products, variations, customers, orders, transactions, coupons, shipping plans, tax classes, cart sessions, and locations.
* **Optional MCP integration** — generators exposed as MCP tools via the WordPress Abilities API + `mcp-adapter` (graceful no-op when absent).
* **REST parity** — every generator served at `fluent-cart-fakerpress/v1/<resource>/generate`, with admin routes aligned to the REST bases.
* **Fix** — corrected the Fluent Cart dependency guard (`FLUENTCART_VERSION`) that previously prevented generators from running.

= 1.0.0 - November 11, 2025 =
* Initial release: product and customer generators, React admin, REST API, hook system.

== Upgrade Notice ==

= 2.0.0 =
Major release: new React admin, 13 generators (Attributes/Refunds/Logs added), and optional MCP integration. Rebuild assets (`yarn build`) if installing from source.

== Other Notes ==

**Privacy & Data Handling**
* Data is stored only in your WordPress database; nothing is transmitted externally.
* Generated content is fictional.
* Restrict to non-production use and keep backups.

**Contributing**
* Repository: https://github.com/mralaminahamed/fluent-cart-fakerpress
* Report issues or request features via GitHub Issues.
* Submit pull requests with tests, following WordPress Coding Standards and PSR-4.
