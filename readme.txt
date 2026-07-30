=== StoreSeeder ===
Contributors: mralaminahamed
Tags: test data, dummy data, sample data, demo content, faker
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 7.4
Requires Plugins: fluent-cart
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generate realistic Fluent Cart test data with 17 generators, a modern React admin, schema-driven configuration, and optional MCP integration.

== Description ==

StoreSeeder generates realistic test data for the Fluent Cart e-commerce platform. It helps developers, agencies, and store owners build sophisticated datasets for testing, demos, and performance evaluation — without hand-crafting records.

All data is created through native Fluent Cart models, so generated records respect the same validation, relationships, and business logic as real data and stay compatible with Fluent Cart updates.

**Sample data is optional and consent-based.** StoreSeeder can download locale-specific reference data (product names, addresses, customer tags) from GitHub to make generated content more realistic. This never happens automatically — it runs only after you accept a one-time consent prompt on the plugin's admin page, transmits no data about your site, and can be declined without losing any functionality (generators fall back to built-in defaults). You can change the decision at any time from Settings.

**Highlights**

* **17 generators** — products, product variations, customers, orders, transactions, refunds, coupons, shipping plans, shipping classes, tax classes, order tax lines, attributes, cart sessions, labels, product downloads, subscriptions, and activity logs.
* **Modern admin** — a single-page React app (React Router v7, Tailwind CSS v4, lucide icons) that adapts to your WordPress admin color scheme.
* **Live preview** — a read-only preview of real faker rows that refreshes as you change settings, without persisting anything.
* **Schema-driven configuration** — each generator renders its own fields from a parameter schema: nested options, ranges, toggles, and intelligent defaults.
* **REST API** — every generator is exposed at `storeseeder/v1/<resource>/generate` for programmatic use.
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
2. Search for "StoreSeeder".
3. Click **Install Now**, then **Activate**.
4. Open the new **StoreSeeder** menu item.

= Manual Installation =
1. Download the plugin ZIP.
2. Upload it to `/wp-content/plugins/storeseeder/`.
3. Activate via the **Plugins** screen.
4. Open the **StoreSeeder** menu.

= Development Setup =
1. Clone: `git clone https://github.com/mralaminahamed/storeseeder.git`
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

1. Dashboard — run totals, recent activity, and all 17 generators grouped by category.
2. Product generator — price range, categories, and attributes, with a live preview of the rows the run will create.
3. Customer generator — customer types, age groups, and address preferences, previewed before anything is written.
4. Order generator — order status mix, line items, and date range, with the preview updating as settings change.
5. Settings — generation defaults, locale, reproducible seed, and run-history retention.

== Changelog ==

Only the four most recent releases are listed here. The complete history, in Keep a Changelog format, is maintained in the repository:

[CHANGELOG.md](https://github.com/mralaminahamed/storeseeder/blob/trunk/CHANGELOG.md)

= 1.0.0 =
* Initial release.
* 17 generators — products, product variations, customers, orders, transactions, refunds, coupons, shipping plans, shipping classes, tax classes, order tax lines, attributes, cart sessions, labels, product downloads, subscriptions, and activity logs — all persisting through native Fluent Cart models.
* Modern single-page React admin (React Router v7, Tailwind CSS v4) with live preview, command palette, and a batch queue.
* REST API — every generator at `storeseeder/v1/<resource>/generate`.
* Optional MCP integration via the WordPress Abilities API + `mcp-adapter`.
* Consent-gated sample data — locale reference data downloads from GitHub only after an administrator accepts a one-time consent prompt; no site data is sent and declining uses built-in defaults.
* Security — sample-data archives are validated before extraction to prevent path traversal (zip-slip).
* Filters and actions across the generation lifecycle.

== Upgrade Notice ==

= 1.0.0 =
Initial release.

== External services ==

StoreSeeder connects to two external services. Neither is contacted on activation, both are administrator-initiated, and no personal or store data is ever transmitted to either one.

**1. GitHub — sample data repository**

Locale-specific reference data (product names, addresses, customer tags) used to make generated content more realistic. Downloaded only after an administrator grants consent — either by accepting the one-time consent prompt on the plugin admin page, or by clicking "Sync now" on the Settings page. Declining leaves every generator working from built-in defaults, and the decision can be changed from Settings at any time.

**2. WordPress.org — plugin directory API**

The "Our Plugins" admin page lists the plugin author's other WordPress.org plugins with live ratings and install counts. Requested by the browser, only when an administrator opens that page.

The full disclosure for each service — endpoint, exactly when the request is made, what is sent and received, and the provider's terms of service and privacy policy — is documented here:

[docs/external-services.md](https://github.com/mralaminahamed/storeseeder/blob/trunk/docs/external-services.md)

== Source code ==

The minified JavaScript and CSS in `build/` is compiled from the TypeScript and CSS sources in `src/`, which are not included in the distributed plugin package. The complete, human-readable source is public:

[github.com/mralaminahamed/storeseeder](https://github.com/mralaminahamed/storeseeder)

Build tooling is webpack (via @wordpress/scripts), TypeScript, and Tailwind CSS, configured by `webpack.config.js`, `tsconfig.json`, and `postcss.config.js` in the repository root. The build steps are listed under "Development Setup" above; local setup, the full toolchain, and the quality gates are documented in the contributing guide:

[CONTRIBUTING.md](https://github.com/mralaminahamed/storeseeder/blob/trunk/CONTRIBUTING.md)

== Other Notes ==

**Privacy**

All generated data is stored in your own WordPress database and is never transmitted anywhere. Generated content is fictional and does not represent real individuals or transactions. The plugin does not collect analytics and does not phone home.

The plugin makes two outbound requests, both administrator-initiated and both carrying no site data — see the "External services" section above, and [docs/external-services.md](https://github.com/mralaminahamed/storeseeder/blob/trunk/docs/external-services.md) for the full disclosure.

**Contributing**

Development happens on [GitHub](https://github.com/mralaminahamed/storeseeder). Bug reports, feature requests, and pull requests are all welcome — the [issue tracker](https://github.com/mralaminahamed/storeseeder/issues) is the place to start. Branching, commit conventions, quality gates, and pull request expectations are all documented in the contributing guide:

[CONTRIBUTING.md](https://github.com/mralaminahamed/storeseeder/blob/trunk/CONTRIBUTING.md)
