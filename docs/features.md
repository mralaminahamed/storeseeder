# Features Overview

What StoreSeeder does. Everything listed here exists in the shipped code — where a capability is
planned rather than present, it says so.

## The 21 generators

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
| **Attributes** | Attribute groups (Colour, Size, Material…) with terms, bound to real variations | Products |
| **Product Categories** | Categories, nested where asked, with existing products filed under them | Products |
| **Product Tags** | Tags applied to existing products — WooCommerce only | Products |
| **Brands** | Product brands, attached to existing products, optionally nested as sub-brands | Products |
| **Product Downloads** | Downloadable files on products, plus download permissions on existing orders | Products, Orders |
| **Cart Sessions** | Abandoned and active carts containing real products | Products |
| **Transactions** | Payment transactions against real orders | Orders |
| **Refunds** | Full and partial refunds against existing successful charges | Transactions |
| **Subscriptions** | Subscription records against existing orders | Orders, Products |
| **Licenses** | Software licences against existing orders — keys, site limits, activation counts, expiry, and activation rows for the sites in use | Orders, Products, **Fluent Cart Pro** |
| **Labels** | Labels (tags) attached to existing orders and customers | Orders, Customers |
| **Tax Classes** | A tax class plus the geographic rate rows that make it applicable | — |
| **Order Tax Lines** | Per-order tax lines linking an order to a tax rate | Orders, Tax Classes |
| **Shipping Plans** | Shipping methods (flat rate, free shipping) attached to a zone | — |
| **Shipping Classes** | Groups of products with shared shipping requirements | — |
| **Logs** | Activity log entries across orders, products, customers, coupons and subscriptions | — |

Generators that build on others say so when a prerequisite is missing, naming what to generate
first rather than failing opaquely.

## Multi-platform

StoreSeeder writes through a **platform driver**, so the same generators can seed different
e-commerce plugins.

- **Fluent Cart** — shipped, 20 of the 21 resources: it registers no product tag taxonomy, so that one is refused by name. Licences are conditional: the tables belong to Fluent Cart Pro, so without it the resource reports that plugin by name
- **WooCommerce** — shipped, 18 of the 21 resources. Written through WooCommerce's own CRUD
  objects (`WC_Product`, `WC_Order`, `WC_Customer`, `WC_Coupon`), the same route
  `wc-smooth-generator` takes, so records are valid under HPOS or the post store and fire the
  hooks other extensions listen for. Subscriptions need WooCommerce Subscriptions; transactions,
  labels and licences are reported unsupported *with the reason*, because WooCommerce has no
  equivalent and no plugin changes that — payment lives on the order, there are no order labels,
  and licensing is not a core concept
- **EasyCommerce, StoreEngine** — planned
- **Anything else** — a third party can register a driver from their own plugin through the
  `storeseeder_platforms` filter, with no changes here

### What each platform stores

Fifteen resources exist on both. The three WooCommerce refuses are refused with a reason rather
than dimmed in silence, because "install something" and "this platform works differently" are
different answers:

| Resource | Fluent Cart | WooCommerce |
|---|---|---|
| Brands | `product-brands` taxonomy | `product_brand` taxonomy (core since 9.6) |
| Categories | `product-categories` taxonomy | `product_cat` taxonomy — note the shortened name |
| Tags | **none** — refused with a reason | `product_tag` taxonomy |
| Transactions | a payment record per attempt | payment lives on the order — no separate record |
| Labels | labels on orders and customers | no order or customer labels |
| Licences | needs Fluent Cart Pro | not a core concept |
| Subscriptions | core tables | needs WooCommerce Subscriptions |

Two smaller differences worth knowing, since they change what the same entity produces:

- A **WooCommerce customer is a WordPress user** — there is no separate customer record — so the
  guest case is served by generating a guest order rather than an account-less customer.
- A **variation needs a variable parent**. If the store has none, the WooCommerce writer promotes
  an existing simple product rather than refusing, and adds the attribute variations vary on.

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
- `track_cost` — record what the shop paid, for margin reporting
- `categories` — `max_per_product`, drawn from categories that already exist
- `inventory` — `manage_stock`, `stock_range`
- `content_options` — `description_length` (`short` | `medium` | `long`)

Each of those changes the output. That is worth stating because until recently most of them did
not: the generator declared six groups and read one, so the price range in the admin was decorative
and every product came out between 9.99 and 999.99. Three parameters that no writer could ever read
— `include_images`, `attributes.include_attributes`, `attributes.variation_count` — have been
removed rather than left as controls that do nothing. Attributes and variations are their own
generators; images will return when there is something to attach.

A product now carries `slug`, `short_description`, `sale_price`, `cost`, `manage_stock`,
`backorders`, `sold_individually` and a category count alongside the original fields. All of them
exist on both shipped platforms, though not always under the same name — WooCommerce's sale price is
Fluent Cart's compare-at price, and WooCommerce's cost of goods is Fluent Cart's item cost.

### Customers

- `customer_types` — any of `regular`, `vip`, `wholesale`, `guest`, `returning`. A guest holds no
  account, a wholesale buyer always carries a company, a VIP is flagged as one, and a returning
  customer has definitely bought something
- `country_focus` — two-letter codes to draw addresses from
- `demographics` — `age_groups` (`18-25` … `65+`), which set the birth date. A third of customers
  give none, which is the realistic case
- `address_preferences` — `include_shipping`, `different_addresses_ratio`
- `contact_preferences` — `phone_numbers`, `marketing_opt_in_ratio`
- `include_history` — writes lifetime **metadata**; it does not create orders. Use the Orders
  generator for that
- `loyalty_tier_focus` — where set, the tier is chosen from this list rather than derived from spend
- `account_status` — `active`, `inactive`, `pending`, or `mixed`

Customers were the worst case of the three surfaces disagreeing. The endpoint declared
`customer_type` — singular, enumerating `individual`/`business`/`mixed` — while the admin and the
MCP ability declared `customer_types` with five entirely different values, and nothing read either.
`include_billing` is gone: every platform requires a billing address on a customer, so switching it
off could only produce a record nothing could use.

A customer now carries `date_created`, equal to `customer_since`, so an account "since 2021" is
registered in 2021 rather than today. Lifetime spend is an integer in minor units like every other
amount.

The demographic and loyalty fields — birth date, gender, occupation, tier, points, VIP flag, source
— have no native column on either platform, so they are stored under `storeseeder_`-prefixed keys:
WordPress user meta on WooCommerce, `fct_customer_meta` on Fluent Cart. A parameter whose result
nobody can read afterwards is the same broken promise as one nothing reads at all.

### Orders

- `order_status` — any of `pending`, `processing`, `on_hold`, `completed`, `cancelled`, `refunded`,
  `failed`; a list, drawn from at random
- `items_per_order` — `{ min, max }`
- `payment_methods` — `stripe`, `paypal`, `cod`, `bank_transfer`, `check`
- `geographical_distribution` — `US`, `CA`, `GB`, `AU`, `DE`, `FR`
- `include_customer` — off generates guest orders
- `customer_id` — attach every order to one account, to give it a purchase history
- `include_shipping` — off generates orders with no shipping line at all, which is what a
  download-only store looks like; that is a different order from one shipped for free, and both
  are generated
- `include_tax`

Orders had the same problem products did, worse: seven parameter groups declared and none read, so
asking for completed orders got the usual spread and every order carried one to three items whatever
range was set. Two parameters are gone rather than left decorative. `order_value_range` cannot be
honoured — an order's total is the sum of the catalogue prices of the products it points at, so a
requested range could only be met by charging something other than what the store sells for; use
`price_range` on products instead. `customer_type` and `customer_distribution` described a
new-versus-existing split that no writer implemented; `customer_id` covers the case that was
actually useful.

An order now carries `discount_total`, `shipping_total`, `customer_note`, `ip_address`,
`user_agent`, `paid_at` and `completed_at`, and both addresses carry a `company`. The dates follow
the status rather than being invented: only a paid order has a payment date, only a completed one a
completion date. Fluent Cart has no company column on an order address and reports it under
`ignored`.

### Coupons

- `discount_types` — any of `percentage`, `fixed`, `free_shipping`
- `discount_range` — `min_percentage`, `max_percentage`, `min_fixed`, `max_fixed`
- `usage_limits` — `set_usage_limits`, `max_uses`, `max_uses_per_user`
- `validity_period` — `{ min_days, max_days }`
- `restrictions` — `minimum_spend`, `maximum_spend`, `exclude_sale_items`, `product_restrictions`

Five groups, and until now none of them reached the generator: asking for percentage coupons got a
spread across all three types, and every discount came out between 5 and 50 percent or 5 and 100
dollars. The type vocabularies also disagreed — the endpoint accepted `fixed_amount` and the admin
offered `products`, neither of which is a type any platform has, so choosing either failed the
request or silently produced the full spread.

A coupon now carries `usage_limit_per_user`, `starts_at`, `minimum_amount`, `maximum_amount`,
`exclude_sale_items`, `stackable` and a product-restriction count. Switching usage limits off leaves
them **null**, which is an unlimited coupon — a limit of zero would reject the coupon on its first
use. The per-customer limit never exceeds the total, which Fluent Cart rejects outright.

Some coupons have not started yet, which is the checkout case no fixture had. WooCommerce has no
start date — `WC_Coupon` carries an expiry and nothing else — and reports `starts_at` under
`ignored`.

The remaining fourteen resources follow the same pattern; the admin renders whatever the schema
declares.

## Locales

Seventy-five locales, every one of which FakerPHP ships a provider for — the admin picker, the
REST `locale` enum and the MCP input schema are all enumerated from
`StoreSeeder\Platforms\Locale`, so what is offered is exactly what generates. Locale affects
names, addresses, phone numbers, company names and postcodes.

An unrecognised locale falls back to the same language where a regional variant exists — a site
on `de_LU` generates German — and to `en_US` when nothing matches. `storeseeder_locales` narrows
or extends the list; `storeseeder_locale` overrides the choice outright.

Optional **sample data** — locale-specific product names, addresses and customer tags — can be
downloaded from GitHub to make content more realistic, but only after an administrator accepts a
one-time consent prompt. Declining costs no functionality; generators fall back to built-in
defaults. See [external-services.md](external-services.md).

## REST API

Every generator exposes two routes. All require `manage_options`, or whatever
`storeseeder_capability` returns.

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
`tax_class` at `tax_classes`. [Usage](usage.md#rest-api) lists all eighteen with the response
shape.

## Extensibility

Filters and actions across the whole lifecycle, with the full table in
[architecture.md](architecture.md#extension-points). The ones worth knowing:

- `storeseeder_platforms` — register a platform driver; the entire surface needed to add one
- `storeseeder_rest_controllers` — add or remove a REST controller, so a driver can expose a
  resource of its own
- `storeseeder_mcp_abilities` — add or remove an MCP ability
- `storeseeder_mcp_settings` — decide the three MCP switches in code rather than in the database
- `storeseeder_purge_order` — the order generated resources are deleted in; children first
- `storeseeder_canonical_{resource}` — change generated data before it is written, for every
  platform at once
- `storeseeder_locales` — narrow or extend the offered locales; the admin, REST enum and MCP
  schema all follow it
- `storeseeder_capability` — who may use the plugin, for the admin menu, REST and MCP at once
- `storeseeder_admin_payload` — add to what the admin app receives on first paint
- `storeseeder_generated_item_{type}`, `storeseeder_after_batch_generate_{type}` — observe or
  adjust a run
- `storeseeder_platform_supports_{id}` — override a capability, or declare that your extension
  satisfies a requirement

## Platform-specific parameters

Some properties exist on one platform and nowhere else. Those are not canonical entity fields — a
generator names no platform, and a field only one platform stores would make a fixed seed produce
different data elsewhere. They are **generation parameters** the driver declares and its own writer
reads:

| Platform | Resource | Parameters |
|---|---|---|
| WooCommerce | Products | `featured_ratio`, `catalog_visibility`, `tax_status` |
| Fluent Cart | Products, Variations | `payment_type` (`onetime` or `subscription`) |

The admin shows them only when that platform is the target, so a control is never offered for a run
that would ignore it. The REST endpoint is looser by necessity — routes are registered before a
target is resolved, so it accepts any driver's field — and reports what the target could not use in
an `ignored` key on the response.

`storeseeder_platform_fields_{id}` is the seam for an extension that adds a column to its platform.

### Product variations

- `variation_types` — any of `size`, `color`, `material`, `style`, `flavor`, `weight`, `dimension`
- `attributes_per_product` — `{ min, max }` axes per variation
- `variations_per_attribute` — `{ min, max }` distinct values each axis draws from
- `price_variation_range` — `{ min_percentage, max_percentage }` from the parent's price
- `inventory` — `manage_stock`, `stock_range`
- `generate_skus`
- `product_id`, `exclude_product_ids` — which product a variation attaches to

Variations declared roughly fourteen distinct parameter names across three surfaces and read none of
them. The endpoint had `variation_types` and `include_inventory`, the admin `price_variance` and
`stock_settings`, the MCP ability flat `stock_min` and `stock_max`. Every variation came out as a size
and a colour at a price unrelated to the product it hung off.

`inventory` is the name Products already uses, so both resources now spell the same idea the same way.
`price_variation_range` is applied by the **writer**, because a percentage of the parent's price needs
the parent — and neither platform stores a price on a variable product, so the base is the cheapest
variation already on it. A variation is never priced at zero, whatever percentage is asked for.

Each axis becomes its own attribute on WooCommerce, so a size-and-colour product gets a Size dropdown
and a Colour dropdown rather than one called "Variant" holding `Large / Red` as a single option. The
first variation on a product establishes the axes and every later one fills the same set: an unfilled
axis means "any size" in WooCommerce, and a product whose variations each specify a different subset
is a confusing fixture rather than a realistic one. Fluent Cart has no attribute model — the title
*is* the option — so the axes are kept in its variation payload alongside everything else with no
column.

### Transactions

- `transaction_types` — any of `charge`, `refund`, `dispute`
- `transaction_statuses` — `pending`, `authorized`, `completed`, `failed` for charges; a refund is
  always `refunded` and a dispute always `disputed`
- `payment_methods` (also accepted as `payment_gateways`)
- `amount_range` — `{ min, max }` in major units
- `refund_percentage` — the share of transactions that are refunds
- `include_gateway_metadata` — payer email, card brand and last four
- `customer_id`, `order_status_filter` — which orders a transaction may attach to

Six of the endpoint's seven parameters were read by nothing, and the other two surfaces declared a
different set again: `payment_gateways` for `payment_methods`, and a `transaction_types` enum of
`payment`, `adjustment`, `fee` and `commission` — none of which is a type any platform stores, and
`payment` is `charge` under another name. A third of every run came out as refunds however few were
asked for.

The canonical transaction vocabulary now lives in `Platforms\Status` beside the order statuses.
Fluent Cart spells completed `succeeded` and disputed `dispute_lost`, and its writer maps both — an
unmapped status is a string its own status filters never match, so the transaction exists and appears
nowhere. `cancelled` and `partially_refunded` were offered by the endpoint and are not transaction
states: a cancelled payment is a failed one, and a partial refund is a refund for part of the amount.

A transaction now carries its own date, clamped forward by the writer so it never predates the order
it belongs to. Card details accompany a card payment that actually went through, and nothing else.

### Cart sessions

- `abandonment_rate` — the share left abandoned; the rest split between still-active and converted
- `status_distribution` — stage weights, which win over the rate where given
- `guest_cart_ratio` — the share belonging to a guest rather than an account
- `items_per_cart` — `{ min, max }`
- `customer_id` — attach every cart to one customer

Seven of the endpoint's eight parameters were read by nothing: a run asking for a store full of
abandoned carts got an even third of each stage, the guest share was fixed at 30% whatever was asked,
and every cart carried one to five items regardless.

Carts have canonical stages now — `active`, `abandoned`, `converted` — where the generator used to
emit Fluent Cart's own words. Three, because three is what a platform can tell apart: Fluent Cart has
no abandoned-cart concept at all, so an abandoned cart is one that reached checkout and never got an
order, and that is what the stage maps to. A converted cart carries an order and a completion date;
without them it is marked completed and appears in no revenue figure, which is the same as not
converting.

Three parameters are gone. `cart_value_range` cannot be honoured for the same reason
`order_value_range` could not: a cart's value is the sum of the catalogue prices of the products in
it, and the writer now prices cart lines from the variation each points at. `abandonment_tracking`
described reminders and a recovery rate, and Fluent Cart stores neither — there is no reminder record
to write. `customer_type` enumerated a new-versus-existing split no writer implemented; `customer_id`
and `guest_cart_ratio` are what was useful in it, and `guest_only` is still accepted as a way of
asking for a guest ratio of 100.

### Supported, but not in full

A platform can store a resource without storing everything a canonical entity carries, and the
capability says which fields it drops rather than the writer discarding them quietly:

| Platform | Resource | Ignored | Why |
|---|---|---|---|
| WooCommerce | Customers | `with_account` | A WooCommerce customer *is* a WordPress user; there is no account-less customer record |
| WooCommerce | Shipping Classes | `cost`, `per_item` | A class's cost belongs to a shipping *method*, so the same class costs different amounts per zone |
| WooCommerce | Coupons | `starts_at` | `WC_Coupon` has an expiry and no start date, so a coupon that becomes valid next week cannot be expressed |
| Fluent Cart | Products | `backorders` | A boolean column where the canonical vocabulary has three values, so "allow, but notify" cannot be stored |
| Fluent Cart | Orders | `company` | `fct_order_addresses` has no company column, and nothing reads one out of the meta blob |
| Fluent Cart | Transactions | `associate_with_orders` | `fct_order_transactions.order_id` is a NOT NULL foreign key, so a transaction belonging to no order cannot exist |
| Fluent Cart | Coupons | `maximum_amount`, `exclude_sale_items` | Its conditions have a minimum and no maximum — `max_discount_amount` caps the discount, not the cart — and its validation does not know about sale prices |

The generator page prints those under the fields, so a setting that will not apply says so before
the run rather than after it.

## Deleting generated data

Settings → **Danger zone** offers to delete what StoreSeeder created: the products, orders,
customers and everything hanging off them, with a per-resource breakdown so the number is
checkable before it is acted on. `wp storeseeder cleanup delete` does the same from the
command line.

**It deletes only rows the plugin recorded creating.** Every successful write goes into a
ledger table (`{prefix}storeseeder_generated`), and the cleanup walks that list rather than
the store's tables. Nothing is ever matched on for looking like test data — on a staging site
restored from production, that guess eventually takes out a real catalogue.

Consequences worth knowing:

- Data generated before this feature existed is not in the ledger, so it is not offered. It
  has to be removed by hand.
- Children go first: an order's line items, addresses, applied coupons, tax lines,
  transactions and download permissions are removed with it, because none of them cascade.
- Things the writer *reused* rather than created stay — the WordPress user a generated
  customer was linked to, a shipping zone an existing method already used, the tax rate an
  order tax line points at.
- A row that cannot be deleted keeps its record and the reason is reported once, not once per
  row. If the rows are already gone, **Forget the remaining records** drops the records
  without touching the store; it is a separate action because forgetting and deleting are
  opposite mistakes to make.
- Deletion is batched (100 rows per request) and the response says what is left, so a site
  with thousands of recorded rows shows progress instead of timing out.
- `storeseeder_purge_order` reorders deletion for a platform whose resources have their own
  dependencies.

## Model Context Protocol (MCP)

Optional. Each generator is exposed as **two** MCP tools so an AI client — Claude Desktop, an
IDE assistant — can work with test data conversationally:

| Tool | What it does |
|---|---|
| `storeseeder/preview-<resource>` | Read-only. Returns the columns and rows a run would create, and writes nothing |
| `storeseeder/generate-<resource>` | Creates the rows in the store |

Two tools rather than one flag, because an AI client's permission model works on tools:
"you may call preview, not generate" is enforceable, while "you may call generate with
`dry_run: true`" is a promise. Each carries MCP annotations saying which it is, so a client
can treat them differently without reading the description.

Requires the WordPress Abilities API (bundled in WordPress 6.9+, or installable separately)
and the `mcp-adapter` plugin.

### The three switches

Settings → **AI tools (MCP)** has one switch per risk class, and each is a registration gate:
a tool that is not registered cannot be called.

| Switch | Off means |
|---|---|
| Enable AI tools | No MCP server at all — `/wp-json/storeseeder-mcp/mcp` stops existing and a client cannot connect |
| Allow preview tools | The read-only tools are withdrawn |
| Allow generating | An agent can preview but cannot write rows into the store |

All three default to on. Only administrators can change them, like the access roles they sit
beside, and `storeseeder_mcp_settings` decides them in code where policy should not be an
administrator's choice.

### Two endpoints, the same tools

StoreSeeder registers its own server at `/wp-json/storeseeder-mcp/mcp`, where every tool is
listed by name with its own JSON Schema — so a client validates parameters before it calls,
and the model chooses from a typed list.

The same tools are also reachable through mcp-adapter's default server at
`/wp-json/mcp/mcp-adapter-default-server`, which exposes three generic tools
(`discover-abilities`, `get-ability-info`, `execute-ability`) an agent uses to find and run
any ability on the site. That route works because StoreSeeder marks its abilities
`mcp.public`, and it honours the same three switches. It is the right choice when a client is
already configured for one site-wide endpoint; StoreSeeder's own server has the better
ergonomics, since the tools arrive typed rather than behind a discovery call.

### Connecting a client

A desktop client talks to WordPress through
[Automattic's `mcp-wordpress-remote` proxy](https://github.com/Automattic/mcp-wordpress-remote),
which is where the connection details and the current config format are documented. Point it at
either endpoint with an application password — never your login password:

```json
{
  "mcpServers": {
    "storeseeder": {
      "command": "npx",
      "args": ["-y", "@automattic/mcp-wordpress-remote@latest"],
      "env": {
        "WP_API_URL": "https://example.com/wp-json/storeseeder-mcp/mcp",
        "WP_API_USERNAME": "admin",
        "WP_API_PASSWORD": "application password, from Users → Profile"
      }
    }
  }
}
```

Swap `WP_API_URL` for `/wp-json/mcp/mcp-adapter-default-server` to go through the shared
default server instead. On a local site over plain HTTP, add `--allow-http` to `args`.

The user the application password belongs to needs StoreSeeder access — the same gate as the
admin screen and the REST API, so an agent can never do more than the person who authorised it.

It degrades gracefully: with either dependency absent, MCP does nothing and the rest of the
plugin is unaffected. Abilities dispatch through the REST API rather than calling generators
directly, so they inherit the same validation and platform resolution — including the target
platform, which an MCP client can set with the `platform` parameter.

## Security

- One capability gate — `StoreSeeder\Access`, default `manage_options` — for the admin screen, every
  REST route, every MCP ability and the AJAX handlers
- Roles can be granted access from Settings without writing code. Administrators are always
  allowed and cannot be revoked, and only an administrator can change the setting, so it cannot
  escalate itself
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
