# Design: Admin UI Parity — Port `easycommerce-fakerpress` SPA into `storeseeder`

**Date:** 2026-06-25
**Status:** Approved (design), pending spec review
**Reference plugin:** `easycommerce-fakerpress` (the "ref")
**Target plugin:** `storeseeder`

## Goal

Replace the target's basic nav-tab admin UI with the ref's full React SPA, and bring
the backend to parity by adding the generators/controllers the ref has but the target
lacks, plus the ref's MCP (WordPress Abilities API) layer. End state: target admin UI
and capabilities mirror ref, rebranded for Fluent Cart.

## Scope

### In scope
- Full port of ref `src/admin/` SPA → target, rewired for Fluent Cart.
- 3 new backend generators + controllers: `Attribute`, `Refund`, `Log`.
- MCP layer: `MCP_Server` + one `Generate_*` ability per generator + `Abstracts/Ability.php`.
- Settings + Plugins pages, adapted to Fluent Cart branding.
- Build (`yarn build`) green; browser smoke test.

### Out of scope
- **Product_Review generator** — dropped. Fluent Cart ships no product-review subsystem
  (no `Review`/`Comment` model in `FluentCart\App\Models`). Not built.
- Fluent Cart core changes. Generators bind to existing Fluent Cart models only.
- Net-new generator types beyond ref parity.

### Generator roster (final — 13)

Existing (10, keep): Product, Customer, Order, Coupon, Product_Variation,
Shipping_Plan, Tax_Class, Transaction, Cart_Session, Location.
New (3, add): **Attribute**, **Refund**, **Log**.

## Backend model mapping (new generators)

| Generator | Ref model(s) | Fluent Cart model(s) | Notes |
|-----------|--------------|----------------------|-------|
| Attribute | `Attribute`, `Attribute_Value` | `FluentCart\App\Models\AttributeGroup`, `AttributeTerm` (+ `AttributeRelation` if linking) | Generate groups (e.g. Color, Size) each with N terms. |
| Refund | `Refund` | `FluentCart\App\Models\OrderTransaction` with `transaction_type = Status::TRANSACTION_TYPE_REFUND` ('refund') against an existing `Order` | Full + partial refunds; status via `Status` helper. |
| Log | `Log` | `FluentCart\App\Models\Activity` | Activity entries across orders/products/customers/coupons. |

Each new generator extends `StoreSeeder\Abstracts\Generator`, follows the
existing generator pattern (see `includes/Generators/Product.php`):
`get_resource_type()`, `get_supported_types()`, `get_description()`,
`generate_single_item()`. Each gets a matching controller extending
`StoreSeeder\Abstracts\Controller`, registered in
`register_rest_routes()` in `class-storeseeder.php`. REST namespace stays
`storeseeder/v1/`.

## Frontend architecture (port of ref `src/admin/`)

Data-driven SPA. Hash router with provider stack; generator pages render fields
auto-derived from a JSON-schema-like `parameterConfig` in `lib/generators.ts`.

```
src/admin/
  index.tsx                      entry → mounts <App/> on #storeseeder-root
  styles.css, components.css
  components/
    App.tsx                      createHashRouter + provider stack
    shell/      AppShell, Sidebar, Topbar
    dashboard/  StatCard, Sparkline, RecentActivity
    home/       GeneratorGrid
    generator/  ConfigColumn, FieldSection, PreviewTable, RunBar
                fields/ Chips, FieldSelect, NumberField, RangeField, Stepper, TextField, Toggle
    overlays/   BatchTray, CommandPalette, LocalePicker, Toasts, TweaksPanel
    Pages/      RootLayout, HomePage, GeneratorPage, SettingsPage, PluginsPage
    ui/         badge, button, section-label, status-pill
  lib/          fieldsFromSchema, generators, icons, paths, preview, settings, storage, tone, utils
  providers/    BatchProvider, StatsProvider, ToastProvider
  theme/        ThemeProvider, useTheme
  types/        index
```

Routes (hash): `/` Home, `/generator/:type` Generator, `/settings` Settings,
`/plugins` Plugins. Provider order (outer→inner): Theme → Toast → Stats → Batch → Router.

Target's current admin tree (`components/Generators/*`, `components/Pages/*`,
`GeneratorBase.tsx`, simple `App.tsx`, `lib/utils.ts`, `utils/cn.ts`, hand-written
`ui/*`) is **removed/replaced** by the ported tree. The ported `ui/` set replaces the
existing shadcn set; keep `cn`/`utils` helpers consolidated to match ref.

## Rewire rules (ref → target, applied during port)

| Concern | Ref value | Target value |
|---------|-----------|--------------|
| Localized JS data var | `easycommerceFakerpressApi` | `storeseederApi` |
| Root DOM id | `easycommerce-fakerpress-root` | `storeseeder-root` (matches PHP `render_admin_page`) |
| Text domain | `easycommerce-fakerpress` | `storeseeder` |
| REST base | `easycommerce-fakerpress/v1/` | `storeseeder/v1/` |
| Webpack entry / bundle | `app` → `build/app.js` | `admin` → `build/admin.js` (target PHP enqueues `build/admin.js`/`.css`) |
| PHP namespace | `EasyCommerceFakerPress\*` | `StoreSeeder\*` |
| MCP server id / NS | `easycommerce-fakerpress` / `EasyCommerceFakerPress\MCP` | `storeseeder` / `StoreSeeder\MCP` |
| Plugins page author query | `mralaminahamed` (self-filter `easycommerce-fakerpress`) | `mralaminahamed` (self-filter `storeseeder`) |
| Settings URLs | `.../easycommerce-fakerpress` | `.../storeseeder`, sample data `fluent-cart-fakerpress-sample-data` |

`@/` path alias must resolve to `src/` (check `tsconfig.json` + webpack resolve).
Strings: every `__()/sprintf()` second arg flips to `storeseeder`.

## `lib/generators.ts`

13 entries (drop Attribute? no — Attribute kept; drop Product_Review). Each entry:
`name, category, order, icon (lucide), iconName, description, useCase, route, popular?,
parameterConfig`. `route` matches controller endpoint slug. `parameterConfig` is the
schema `fieldsFromSchema.ts` renders into field components. Copy ref entries for the 10
shared + Attribute, Refund, Log; remove Product_Review.

## Build / deps

- `package.json`: ensure `react-router-dom`, `lucide-react`, and ref UI deps present;
  reconcile versions with target lockfile (`yarn.lock`). Target uses Yarn.
- `tailwind.config.js`, `postcss.config.js`, `components.json`, `tsconfig.json`,
  `webpack.config.js` reconciled to ref where the SPA needs them (content globs,
  aliases). Keep target entry name `admin`.
- MCP requires `abilities-api` (WP 6.9+ bundled, else manual) + `mcp-adapter` on the
  site. Document in readme; degrade gracefully if absent (guard like ref `MCP_Server`).

## Error handling

- REST calls via existing fetch wrapper; surface failures through `ToastProvider`.
- Generator runs report per-item errors (`WP_Error`) in `RunBar`/`Toasts`.
- MCP server only registers on `mcp_adapter_init`; absent adapter = no fatal.
- Backend generators return `WP_Error` on model/validation failure (existing pattern).

## Testing / verification

1. `yarn build` — no TS/webpack errors; `build/admin.js` + `build/admin.css` emitted.
2. PHP: `composer dump-autoload`; lint new classes (phpcs config exists).
3. Browser smoke (Chrome DevTools MCP): open StoreSeeder admin page →
   SPA mounts on `#storeseeder-root`; sidebar + home grid render; navigate
   to a generator route; run a small batch (e.g. 2 products) → REST 200, toast success,
   preview/recent-activity updates.
4. New generators: run Attribute, Refund, Log with count=1 each; confirm rows created
   in `AttributeGroup`/`AttributeTerm`, `OrderTransaction` (type refund), `Activity`.
5. PHPUnit: extend existing `tests/php` with smoke tests for the 3 new generators if
   the harness runs locally (best-effort; gate on env availability — log if skipped).

## Build sequence (for the plan)

1. Frontend port: copy ref `src/admin/` tree, apply rewire rules, fix `generators.ts`
   (13, no Product_Review), reconcile build config + deps. `yarn build` green.
2. Backend generators: `Attribute`, `Refund`, `Log` generators + controllers; register
   routes. Lint.
3. MCP layer: `Abstracts/Ability.php`, `MCP/MCP_Server.php`, 13 `MCP/Abilities/Generate_*.php`.
4. Branding pass: Settings/Plugins URLs, readme MCP dependency note.
5. Verify: build, browser smoke, new-generator runs, optional PHPUnit.
