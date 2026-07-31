# Features Overview

What StoreSeeder does. Everything listed here exists in the shipped code — where a capability is
planned rather than present, it says so.

## The 17 generators

Grouped as the admin groups them. Each writes through the target platform's own models, so
generated records carry the same validation, relationships and money handling as real ones.

### Core

| Generator | Writes |
|---|---|
| **Products** | A sellable product: the post, its detail row, and at least one priced variation with SKU and stock |
| **Customers** | Customer records with billing and shipping addresses, purchase-history metadata, loyalty tier, and — for some — a linked WordPress user account |
| **Orders** | Orders with real line items drawn from existing products, addresses, tax, a payment method, and sometimes a real applied coupon |
| **Coupons** | Percentage, fixed-amount and free-shipping coupons with usage limits and expiry |

### Advanced

| Generator | Writes | Needs first |
|---|---|---|
| **Product Variations** | Extra priced variations on existing products, with unique SKUs | Products |
| **Shipping Plans** | Shipping methods (flat rate, free shipping) attached to a zone | — |
| **Tax Classes** | A tax class plus the geographic rate rows that make it applicable | — |
| **Transactions** | Payment transactions against real orders | Orders |
| **Cart Sessions** | Abandoned and active carts containing real products | Products |
| **Attributes** | Attribute groups (Colour, Size, Material…) with terms, bound to real variations | Products |
| **Refunds** | Full and partial refunds against existing successful charges | Transactions |
| **Logs** | Activity log entries across orders, products, customers, coupons and subscriptions | — |
| **Shipping Classes** | Groups of products with shared shipping requirements | — |
| **Labels** | Labels (tags) attached to existing orders and customers | Orders, Customers |
| **Order Tax Lines** | Per-order tax lines linking an order to a tax rate | Orders, Tax Classes |
| **Product Downloads** | Downloadable files on products, plus download permissions on existing orders | Products, Orders |
| **Subscriptions** | Subscription records against existing orders | Orders, Products |

Generators that build on others say so when a prerequisite is missing, naming what to generate
first rather than failing opaquely.

## Multi-platform

StoreSeeder writes through a **platform driver**, so the same generators can seed different
e-commerce plugins.

- **Fluent Cart** — shipped, all 17 resources
- **EasyCommerce, WooCommerce, StoreEngine** — planned
- **Anything else** — a third party can register a driver from their own plugin through the
  `storeseeder_platforms` filter, with no changes here

The target is chosen in the topbar and defaults to `Auto`. One platform active resolves
silently. With several active there is no safe default, so the generator page asks before it
runs — picking wrong would write rows into the wrong store, and nothing about that failure is
visible afterwards.

Support is resolved per request rather than declared once, because it is conditional:
WooCommerce core has no subscriptions until WooCommerce Subscriptions is active, and StoreEngine
gates several resources behind its own addons. A resource the target cannot represent is dimmed
with its reason, naming the plugin that would enable it where one applies, and the REST API
answers 400 rather than writing nothing and reporting success.

## Admin interface

A single-page React 18 app on one WordPress admin screen.

- **Sidebar and hash routes** — generators grouped by category, with per-generator run counts
- **Dashboard** — run totals, recent activity, and the generator grid
- **Live preview** — a read-only table of the rows a run would create, refreshing as you change
  settings. Nothing is persisted, and it works even before a target platform is chosen, because
  previewing never reaches a writer.
- **Schema-driven forms** — every control derives from the generator's parameter schema, so
  there is no per-generator UI code
- **Batch queue** — "Add to batch" collects runs and executes them in sequence from the browser
- **Command palette** — ⌘K / Ctrl+K to jump to any generator or page
- **Adapts to your WordPress admin colour scheme**, with light and dark support
- **Run history** — recent runs per generator, stored in your browser

## Parameters

Every generator accepts `count` (1–100), `locale`, `seed`, and `platform`. A fixed seed makes a
run reproducible.

Beyond those, each generator declares its own schema. The three Core generators, exactly as
shipped:

### Products

- `product_type` — `physical` | `digital` | `mixed`
- `price_range` — `{ min, max }`
- `categories` — `create_new`, `max_per_product`
- `attributes` — `include_attributes`, `variation_count`
- `inventory` — `manage_stock`, `stock_range`
- `content_options` — `description_length` (`short` | `medium` | `long`), `include_images`

> [!NOTE]
> `include_images` is accepted and currently ignored — no image is generated or attached. It is
> declared in the schema ahead of the implementation.

### Customers

- `customer_types` — any of `regular`, `vip`, `wholesale`, `guest`, `returning`
- `demographics` — `age_groups` (`18-25` … `65+`)
- `address_preferences`
- `purchase_history` — `simulate_history` writes purchase-history **metadata**; it does not
  create orders. Use the Orders generator for that.
- `contact_preferences`

### Orders

- `order_status` — `pending`, `processing`, `completed`, `cancelled`, `on_hold`, `refunded`, `mixed`
- `customer_type` — `existing` | `new` | `mixed` | `specific`
- `specific_customer_id` — shown only when `customer_type` is `specific`; the one conditional
  field in the whole schema
- `customer_distribution`
- `items_per_order` — `{ min, max }`
- `payment_methods` — `stripe`, `paypal`, `bank_transfer`, `cash_on_delivery`, `credit_card`
- `geographical_distribution` — `US`, `CA`, `GB`, `AU`, `DE`, `FR`

The remaining fourteen follow the same pattern; the admin renders whatever the schema declares.

## Locales

The generation locale is one of six: `en_US`, `fr_FR`, `de_DE`, `es_ES`, `it_IT`, `pt_BR`.
Anything else falls back to `en_US`. Locale affects names, addresses, phone numbers and
postcodes.

Optional **sample data** — locale-specific product names, addresses and customer tags — can be
downloaded from GitHub to make content more realistic, but only after an administrator accepts a
one-time consent prompt. Declining costs no functionality; generators fall back to built-in
defaults. See [external-services.md](external-services.md).

## REST API

Every generator exposes two routes. All require `manage_options`.

```
POST /wp-json/storeseeder/v1/<rest-base>/generate
POST /wp-json/storeseeder/v1/<rest-base>/preview
```

Plus:

```
GET  /wp-json/storeseeder/v1/platforms          # installed platforms and their capabilities
POST /wp-json/storeseeder/v1/platforms/target   # set the site-wide target
GET  /wp-json/storeseeder/v1/download-sample    # sample-data status
POST /wp-json/storeseeder/v1/download-sample    # download (consent required)
POST /wp-json/storeseeder/v1/download-sample/consent
```

The REST base is not always the resource name — `cart_session` is served at `cart-sessions`,
`tax_class` at `tax_classes`. [Usage](usage.md#rest-api) lists all seventeen with the response
shape.

## Extensibility

Filters and actions across the whole lifecycle, with the full table in
[architecture.md](architecture.md#extension-points). The ones worth knowing:

- `storeseeder_platforms` — register a platform driver; the entire surface needed to add one
- `storeseeder_canonical_{resource}` — change generated data before it is written, for every
  platform at once
- `storeseeder_generated_item_{type}`, `storeseeder_after_batch_generate_{type}` — observe or
  adjust a run
- `storeseeder_platform_supports_{id}` — override a capability, or declare that your extension
  satisfies a requirement

## Model Context Protocol (MCP)

Optional. Every generator is exposed as an MCP tool so an AI client — Claude Desktop, an IDE
assistant — can create test data conversationally. Requires the WordPress Abilities API
(bundled in WordPress 6.9+, or installable separately) and the `mcp-adapter` plugin.

It degrades gracefully: with either dependency absent, MCP does nothing and the rest of the
plugin is unaffected. Abilities dispatch through the REST API rather than calling generators
directly, so they inherit the same validation and platform resolution.

## Security

- `manage_options` required on every REST route and AJAX handler
- Parameters validated by the JSON Schema registered with each route, each with a
  `sanitize_callback`
- Nonce-protected admin requests
- No raw SQL — writes go through the platform's own models
- The sample-data archive is validated entry by entry before extraction, so nothing can be
  written outside the target directory (zip-slip). See [`SECURITY.md`](../SECURITY.md).
- No analytics, no telemetry, no phoning home. Generated content is fictional and represents no
  real person or transaction.

## Limits worth knowing

- **A run is capped at 100 items.** Larger datasets come from repeated runs, or from queueing
  several through the batch tray.
- **The batch queue runs in the browser**, one item at a time. There is no cron and no
  server-side background processing.
- **No caching, no transactions, no bulk inserts, no resume.** A failed item is recorded and the
  run continues; a batch where nothing succeeded returns an error rather than reporting success
  for zero rows.
- **Uninstalling leaves generated data in place.** Generated records are indistinguishable from
  real ones by design, so removing them automatically would risk deleting data you wanted.
- **Single-site.** Multisite is not specifically handled; both plugin options are per-site.

> [!WARNING]
> Use StoreSeeder on development or staging sites only, and back up the database before
> generating large datasets.
