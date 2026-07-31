=== StoreSeeder – eCommerce Test Data Generator for Fluent Cart ===
Contributors: mralaminahamed
Tags: test data, dummy data, demo content, ecommerce, faker
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generate realistic e-commerce test data with 21 generators, a platform driver per store plugin, a modern React admin, and optional MCP integration.

== Description ==

StoreSeeder generates realistic test data for WordPress e-commerce platforms. It helps developers, agencies, and store owners build sophisticated datasets for testing, demos, and performance evaluation — without hand-crafting records.

Where the data lands is decided by a **platform driver**, and the same twenty-one generators feed every driver. **Fluent Cart and WooCommerce are included today**; drivers for other platforms are planned, and the registration filter is public, so a third party can add one from their own plugin without changes here.

All data is created through the target platform's own models, so generated records respect the same validation, relationships, and business logic as real data and stay compatible with that platform's updates.

**Sample data is optional and consent-based.** StoreSeeder can download locale-specific reference data (product names, addresses, customer tags) from GitHub to make generated content more realistic. Nothing is downloaded until you accept a one-time consent prompt on the plugin's admin page. After that, StoreSeeder will re-fetch the files if they go missing. No data about your site is ever transmitted, and declining costs you no functionality — generators fall back to built-in defaults. You can change the decision at any time from Settings.

**Highlights**

* **21 generators** — products, product variations, categories, tags, brands, customers, orders, transactions, refunds, coupons, shipping plans, shipping classes, tax classes, order tax lines, attributes, cart sessions, labels, product downloads, subscriptions, licences, and activity logs.
* **One driver per platform** — the generators are platform-neutral, so the same fixed seed produces identical data wherever it is written. Fluent Cart and WooCommerce ship; the driver layer is public for the rest.
* **Modern admin** — a single-page React app (React Router v7, Tailwind CSS v4, lucide icons) that adapts to your WordPress admin color scheme.
* **Live preview** — a read-only preview of real faker rows that refreshes as you change settings, without persisting anything.
* **75 locales** — names, addresses, phone numbers, and postcodes in any locale FakerPHP ships a provider for. The picker offers exactly what the REST API accepts, searchable by name or code.
* **Schema-driven configuration** — each generator renders its own fields from a parameter schema: nested options, ranges, toggles, and intelligent defaults.
* **REST API** — every generator is exposed at `storeseeder/v1/<resource>/generate` for programmatic use.
* **WP-CLI** — `wp storeseeder generate products --count=20 --locale=de_DE`, plus preview, platform and locale commands. The same controllers as the REST API, so nothing can drift.
* **Translation-ready** — every string passes through gettext and the plugin's own `languages` directory is registered, so Loco Translate and WPML String Translation pick the admin up without configuration.
* **One-click cleanup** — delete the data StoreSeeder generated, tracked in its own ledger so your own rows are never matched on.
* **Optional MCP integration** — expose generators as AI tools via the WordPress Abilities API (see below).
* **Extensible** — filters and actions cover the full generation lifecycle, one filter registers a whole platform, and one filter sets the capability required to use the plugin.

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
* **Product Categories** — categories, nested where asked, with existing products filed under them. Needs a platform that has them: WooCommerce, EasyCommerce and Fluent Cart all do.
* **Product Tags** — tags applied to existing products. WooCommerce and EasyCommerce have tags; Fluent Cart does not, and says so rather than inventing somewhere to put them.
* **Brands** — product brands attached to existing products, optionally nested as sub-brands. Needs a platform that has brands: WooCommerce, EasyCommerce and Fluent Cart do.
* **Cart Sessions** — abandoned and active cart sessions.
* **Labels** — labels (tags) attached to existing orders and customers.
* **Product Downloads** — downloadable files for products, with download permissions on existing orders.
* **Subscriptions** — subscription records against existing orders (on Fluent Cart, active billing requires Pro).
* **Logs** — activity log entries across orders, products, customers, and system events.

**Deleting generated data**

Settings has a Danger zone action that deletes what StoreSeeder created — products, orders, customers and everything hanging off them — with a per-resource breakdown of what will go. `wp storeseeder cleanup delete` does the same from WP-CLI.

It deletes only rows the plugin recorded creating, in its own ledger table. Nothing is ever matched on for resembling test data, so a staging site restored from production keeps its real catalogue. Data generated before this version was released is not in the ledger and is not offered.

**Model Context Protocol (MCP) Integration**

The plugin can optionally expose each generator as two MCP tools so AI clients (e.g. Claude Desktop, IDE assistants) can work with test data in natural language: a read-only preview that shows the rows a run would create, and a generate tool that creates them. Settings has one switch per risk class — enable AI tools, allow preview tools, allow generating — and each is a registration gate, so a tool that is switched off is never offered to a client at all. Only administrators can change them.

The tools are served at `/wp-json/storeseeder-mcp/mcp`, and are also reachable through the `mcp-adapter` plugin's own default server for clients already configured against it. A desktop client connects through Automattic's `mcp-wordpress-remote` proxy (https://github.com/Automattic/mcp-wordpress-remote), using an application password for a user who has StoreSeeder access; that repository documents the setup and the current config format. This requires the WordPress Abilities API (bundled in WordPress 6.9+, or installable separately) and the `mcp-adapter` plugin. MCP is entirely optional and degrades gracefully — the plugin works normally when these dependencies are absent.

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
* One supported e-commerce platform, active. Today: Fluent Cart or WooCommerce. StoreSeeder activates without it and tells you what is missing, rather than blocking activation — blocking would rule out every other platform.
* 256MB memory minimum (512MB for large datasets)
* MCP integration (optional): WordPress Abilities API + `mcp-adapter` plugin

== Frequently Asked Questions ==

= Which e-commerce platforms are supported? =
Fluent Cart and WooCommerce today. Fluent Cart covers 20 of 21 resources — licences need Fluent Cart Pro, which owns the licensing tables, and StoreSeeder says so rather than hiding the generator. WooCommerce covers 18: subscriptions need WooCommerce Subscriptions, and transactions, labels and licences are reported unsupported with the reason, because WooCommerce has no equivalent for them and no plugin changes that. Support is provided by a platform driver rather than wired into the generators, so more can be added. With one platform active it is selected automatically; with several, StoreSeeder asks which store to write to before it runs anything.

= How does platform integration work? =
Generators produce platform-neutral records; a writer for the chosen platform persists them through that platform's own models — Eloquent models for Fluent Cart, the CRUD objects (WC_Product, WC_Order, WC_Customer) for WooCommerce — preserving validation, relationships, and business logic. Raw database writes are avoided so generated data behaves like real data, and so it stays valid across that platform's updates.

= Can I add support for my own platform? =
Yes, from your own plugin and without patching this one. Register a driver on the `storeseeder_platforms` filter and the generators, REST API, and admin pick it up. A driver answers what it is called, whether it is active, which resources it supports, and which writer handles each one. See the architecture documentation for the full contract.

= How many generators are included? =
Seventeen: products, product variations, customers, orders, transactions, refunds, coupons, shipping plans, shipping classes, tax classes, order tax lines, attributes, cart sessions, labels, product downloads, subscriptions, and activity logs.

= Some generators need existing data. Why? =
Several generators build on others: orders need products and customers; refunds need charge transactions; order tax lines need orders and tax rates; product downloads and subscriptions need products and orders. Generate the prerequisites first, and each generator reports clearly when something is missing.

= Do subscriptions require Fluent Cart Pro? =
The subscriptions table ships in Fluent Cart core, so records are generated as fixtures without Pro. Active billing and management require Fluent Cart Pro and a subscription-capable gateway. On other platforms, subscription support depends on what that platform provides — StoreSeeder reports which plugin would enable it rather than hiding the generator.

= Is it safe for production? =
Use only in development or staging. Always back up your database before generating, and start with small batches.

= Can I customize generation? =
Yes. The plugin fires filters and actions across the generation lifecycle — modify parameters, transform generated items, and customize REST responses.

= What is the MCP integration for? =
It exposes the generators as AI tools via the WordPress Abilities API, so an MCP-capable assistant can create test data conversationally. Each generator gets two tools — a read-only preview and one that writes rows — and Settings decides which kinds are offered, so an assistant can be allowed to look without being allowed to fill the store. It is optional and does nothing unless the Abilities API and `mcp-adapter` are present.

= In which languages can data be generated? =
Seventy-five locales — every one FakerPHP ships a provider for. Names, addresses, phone numbers, company names, and postcodes follow the chosen locale. The picker offers exactly the set the REST API accepts, and is searchable by language name or locale code.

= Can I remove the test data afterwards? =
Yes. Settings has a Danger zone action that deletes what StoreSeeder created, and `wp storeseeder cleanup delete` does the same from the command line. It works from a ledger of rows the plugin recorded creating, so it never guesses which rows are test data — your own products and orders are not candidates. Anything generated before this feature existed is not in that ledger and has to be removed by hand.

= Who can generate data? =
Administrators, always. Other roles can be granted access from Settings — one switch per role, covering the admin screen, the REST routes, and the MCP tools alike. Only an administrator can change that setting, so a granted role cannot widen access further, and the Administrator role itself is not listed because it cannot be revoked. Developers can also set the required capability in code with the `storeseeder_capability` filter.

= Can I generate from the command line? =
Yes, with WP-CLI: `wp storeseeder generate products --count=20 --locale=de_DE --user=1`. There are also `preview`, `platforms`, `locales` and `sample-data` commands. Every one dispatches through the same REST controller the admin uses, so the command line and the interface cannot disagree about what is valid. Commands that write require `--user` for someone with the plugin's capability, because generated rows land in a live store.

= Is the plugin translatable? =
Yes. All strings — PHP and the React admin — go through gettext, a `.pot` template ships in `languages/`, and that directory is registered for both PHP and JavaScript translations. Loco Translate can therefore translate it in place, and WPML String Translation picks the strings up once a language is active. Translating the plugin does not change the language of *generated data*; that is what the locale setting is for, and it offers 75 locales.

= How do I remove generated data? =
Use your platform's own deletion tools, WordPress's, or a cleanup plugin. Back up before removing.

== Screenshots ==

1. Dashboard — run totals, recent activity, and all 21 generators grouped by category.
2. Product generator — price range, categories, and attributes, with a live preview of the rows the run will create.
3. Customer generator — customer types, age groups, and address preferences, previewed before anything is written.
4. Order generator — order status mix, line items, and date range, with the preview updating as settings change.
5. Settings — the target platform, generation defaults (pre-filled batch size, locale, and a fixed seed for reproducible runs), and appearance.

== Changelog ==

Only the four most recent releases are listed here. The complete history, in Keep a Changelog format, is maintained in the repository:

[Read the full changelog on GitHub](https://github.com/mralaminahamed/storeseeder/blob/trunk/CHANGELOG.md)

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

Locale-specific reference data (product names, addresses, customer tags) used to make generated content more realistic. Downloaded only after an administrator accepts the consent prompt on the plugin admin page — that prompt is the only way permission is granted. Until it is, "Sync now" and "Force re-sync" on the Settings page open the prompt instead of downloading. Once permission is on record, opening the admin page re-fetches the files if they are missing. Declining leaves every generator working from built-in defaults, and the decision can be changed from Settings at any time.

**2. WordPress.org — plugin directory API**

The "Our Plugins" admin page lists the plugin author's other WordPress.org plugins with live ratings and install counts. Requested by the browser, only when an administrator opens that page.

The full disclosure for each service — endpoint, exactly when the request is made, what is sent and received, and the provider's terms of service and privacy policy — is documented here:

[Read the external services disclosure](https://github.com/mralaminahamed/storeseeder/blob/trunk/docs/external-services.md)

== Source code ==

The minified JavaScript and CSS in `build/` is compiled from the TypeScript and CSS sources in `src/`, which are not included in the distributed plugin package. The complete, human-readable source is public:

[Browse the StoreSeeder source on GitHub](https://github.com/mralaminahamed/storeseeder)

Build tooling is webpack (via @wordpress/scripts), TypeScript, and Tailwind CSS, configured by `webpack.config.js`, `tsconfig.json`, and `postcss.config.js` in the repository root. The build steps are listed under "Development Setup" above; local setup, the full toolchain, and the quality gates are documented in the contributing guide:

[Read the contributing guide](https://github.com/mralaminahamed/storeseeder/blob/trunk/CONTRIBUTING.md)


== Privacy ==

All generated data is stored in your own WordPress database and is never transmitted anywhere. Generated content is fictional and does not represent real individuals or transactions. The plugin does not collect analytics and does not phone home.

The plugin makes two outbound requests, both administrator-initiated and both carrying no site data — see the "External services" section above, and [the external services disclosure](https://github.com/mralaminahamed/storeseeder/blob/trunk/docs/external-services.md) for the full detail.

== Contributing ==

Development happens on [GitHub](https://github.com/mralaminahamed/storeseeder). Bug reports, feature requests, and pull requests are all welcome — the [issue tracker](https://github.com/mralaminahamed/storeseeder/issues) is the place to start. Branching, commit conventions, quality gates, and pull request expectations are all documented in the contributing guide:

[Read the contributing guide](https://github.com/mralaminahamed/storeseeder/blob/trunk/CONTRIBUTING.md)
