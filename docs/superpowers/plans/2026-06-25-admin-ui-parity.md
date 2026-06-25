# Admin UI Parity Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace `fluent-cart-fakerpress`'s basic nav-tab admin UI with the `easycommerce-fakerpress` React SPA, rebranded for Fluent Cart, and bring the backend to parity (3 new generators + MCP layer).

**Architecture:** Port ref's data-driven `src/admin/` SPA (hash router + provider stack + schema-driven generator pages) into the target, applying mechanical rewire rules (data var, root id, text domain, namespace, bundle entry). Add 3 backend generators (`Attribute`, `Refund`, `Log`) bound to Fluent Cart Eloquent models, their REST controllers, and the WordPress Abilities-API MCP layer.

**Tech Stack:** React 18 + `@wordpress/element`, `react-router-dom` (hash router), `lucide-react`, Tailwind, Webpack (`@wordpress/scripts`-style), PHP 7.4+, Fluent Cart Eloquent models, WordPress REST API, WordPress Abilities API + `mcp-adapter`.

## Global Constraints

- Text domain: `fluent-cart-fakerpress` (every `__()/_n()/sprintf()` text-domain arg).
- PHP namespace root: `FluentCartFakerPress\`.
- REST namespace: `fluent-cart-fakerpress/v1`.
- Localized JS global: `fluentCartFakerpressApi`.
- Root DOM id: `fluent-cart-fakerpress-root` (matches `render_admin_page()`).
- Webpack entry name: `admin` → emits `build/admin.js` + `build/admin.css`.
- `@/` path alias resolves to `src/`.
- MCP server id: `fluent-cart-fakerpress`; MCP namespace `FluentCartFakerPress\MCP`.
- Branding URLs stay on `mralaminahamed/fluent-cart-fakerpress`; sample-data repo `fluent-cart-fakerpress-sample-data`. Author query for Plugins page: `mralaminahamed`.
- Product_Review is NOT built (no Fluent Cart review model).
- Generators persist via Fluent Cart models only (no raw inserts), except read-only eligibility lookups.
- Paths below are relative to the plugin root: `wp-content/plugins/fluent-cart-fakerpress/`.
- Ref (source) root: `wp-content/plugins/easycommerce-fakerpress/`.

---

## File Structure

**Frontend (ported into target `src/admin/`):**
- `index.tsx` (entry), `styles.css`, `components.css`
- `components/App.tsx`, `components/shell/{AppShell,Sidebar,Topbar}.tsx`
- `components/dashboard/{StatCard,Sparkline,RecentActivity}.tsx`
- `components/home/GeneratorGrid.tsx`
- `components/generator/{ConfigColumn,FieldSection,PreviewTable,RunBar}.tsx`, `components/generator/fields/{Chips,FieldSelect,NumberField,RangeField,Stepper,TextField,Toggle}.tsx`
- `components/overlays/{BatchTray,CommandPalette,LocalePicker,Toasts,TweaksPanel}.tsx`
- `components/Pages/{RootLayout,HomePage,GeneratorPage,SettingsPage,PluginsPage}.tsx`
- `components/ui/{badge,button,section-label,status-pill}.tsx`
- `lib/{fieldsFromSchema,generators,icons,paths,preview,settings,storage,tone,utils}.ts(x)`
- `providers/{BatchProvider,StatsProvider,ToastProvider}.tsx`
- `theme/{ThemeProvider,useTheme}.ts(x)`, `types/index.ts`

**Backend (new):**
- `includes/Generators/{Attribute,Refund,Log}.php`
- `includes/Controllers/{Attribute,Refund,Log}.php`
- `includes/Abstracts/Ability.php`
- `includes/MCP/MCP_Server.php`
- `includes/MCP/Abilities/Generate_*.php` (13 — one per generator, no Product_Reviews)
- Modify: `class-fluent-cart-fakerpress.php` (route registration, MCP bootstrap, index id already correct)

**Removed:** target's existing `src/admin/components/Generators/*`, `src/admin/components/GeneratorBase.tsx`, old `src/admin/components/App.tsx`, old `src/admin/components/Pages/*`, old `src/admin/components/ui/*`, `src/admin/lib/utils.ts`, `src/admin/utils/cn.ts` — replaced by the ported tree.

---

## Phase 1 — Frontend SPA Port

### Task 1: Branch + copy ref `src/admin/` tree into target

**Files:**
- Create branch; copy ref `src/` → target `src/admin/` (see steps for exact layout).

- [ ] **Step 1: Create feature branch**

```bash
cd wp-content/plugins/fluent-cart-fakerpress
git checkout -b feat/admin-ui-parity
```

- [ ] **Step 2: Remove target's current admin tree (keep build config + index location)**

```bash
rm -rf src/admin/components src/admin/lib src/admin/utils
```

- [ ] **Step 3: Copy ref SPA tree into target `src/admin/`**

Ref `src/admin/*` → target `src/admin/*`; ref `src/index.tsx` → target `src/admin/index.tsx`; ref `src/styles.css` → target `src/admin/styles.css`.

```bash
REF=../easycommerce-fakerpress
cp -R "$REF/src/admin/." src/admin/
cp "$REF/src/index.tsx" src/admin/index.tsx
cp "$REF/src/styles.css" src/admin/styles.css
```

- [ ] **Step 4: Delete the Product_Review-specific frontend pieces (none expected, verify)**

```bash
grep -rln "product-review\|ProductReview\|product_review" src/admin || echo "no product_review frontend refs"
```

- [ ] **Step 5: Commit the raw copy (pre-rewire checkpoint)**

```bash
git add -A && git commit -m "chore: copy easycommerce-fakerpress admin SPA into fluent-cart-fakerpress (pre-rewire)"
```

### Task 2: Apply rewire substitutions across `src/admin/`

**Files:** Modify all of `src/admin/**`.

- [ ] **Step 1: Rewire entry root id + import paths in `src/admin/index.tsx`**

Ensure `src/admin/index.tsx` reads:

```tsx
import React from 'react';
import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';
import App from '@/admin/components/App';
import './styles.css';
import '@/admin/components.css';

domReady( () => {
	const container = document.getElementById( 'fluent-cart-fakerpress-root' )!;
	if ( container ) {
		const root = createRoot( container );
		root.render( <App /> );
	}
} );
```

- [ ] **Step 2: Global string rewires across the tree**

```bash
# Localized JS global
grep -rl "easycommerceFakerpressApi" src/admin | xargs sed -i 's/easycommerceFakerpressApi/fluentCartFakerpressApi/g'
# Root id (any remaining refs)
grep -rl "easycommerce-fakerpress-root" src/admin | xargs sed -i 's/easycommerce-fakerpress-root/fluent-cart-fakerpress-root/g'
# Display name
grep -rl "EasyCommerce FakerPress" src/admin | xargs sed -i 's/EasyCommerce FakerPress/Fluent Cart FakerPress/g'
# Slug + text domain + URLs + sample-data repo (covers easycommerce-fakerpress-sample-data → fluent-cart-fakerpress-sample-data)
grep -rl "easycommerce-fakerpress" src/admin | xargs sed -i 's/easycommerce-fakerpress/fluent-cart-fakerpress/g'
# Generic product label phrasing left as-is; verify no stray "EasyCommerce" brand text
grep -rn "EasyCommerce\|easycommerce" src/admin || echo "clean"
```

- [ ] **Step 3: Verify Plugins page author query + self-filter**

In `src/admin/components/Pages/PluginsPage.tsx` confirm `request[author]=mralaminahamed` is retained and the self-filter excludes `fluent-cart-fakerpress` (the global sed already converted the slug). Confirm REST/data var reads via `fluentCartFakerpressApi`.

- [ ] **Step 4: Verify Settings page URLs**

In `src/admin/components/Pages/SettingsPage.tsx` confirm constants now point to `github.com/mralaminahamed/fluent-cart-fakerpress`, sample-data repo `fluent-cart-fakerpress-sample-data`, and `PLUGIN_VERSION` matches the target plugin header version (read from `fluent-cart-fakerpress.php`). Update `PLUGIN_VERSION` literal to the target's current version if different.

- [ ] **Step 5: Remove Product_Review entry from `lib/generators.ts`**

Open `src/admin/lib/generators.ts`. Delete the generator object whose `route` is `product-reviews` (the Product Review entry) and its `import` of any unused icon. Keep the entries for `attributes`, `refunds`, `logs`. Result: 13 generator objects.

- [ ] **Step 6: Commit**

```bash
git add -A && git commit -m "refactor: rebrand ported admin SPA for fluent-cart (rewire data var, root id, text domain, urls); drop product-review entry"
```

### Task 3: Reconcile build config + dependencies

**Files:**
- Modify: `tsconfig.json`, `webpack.config.js`, `package.json`, `tailwind.config.js`, `postcss.config.js`, `components.json`

- [ ] **Step 1: Confirm webpack entry maps `admin` → `src/admin/index.tsx`**

Open `webpack.config.js`. Ensure:

```js
entry: { admin: path.resolve( process.cwd(), 'src/admin', 'index.tsx' ) },
```

and a resolve alias `'@': path.resolve(process.cwd(), 'src')`. Add the alias if missing:

```js
resolve: {
  ...defaultConfig.resolve,
  alias: { ...defaultConfig.resolve?.alias, '@': path.resolve( process.cwd(), 'src' ) },
  extensions: ['.tsx', '.ts', '.js', '.jsx'],
},
```

- [ ] **Step 2: Confirm `tsconfig.json` path alias**

Ensure `compilerOptions.paths` has `"@/*": ["src/*"]` and `baseUrl: "."`.

- [ ] **Step 3: Reconcile dependencies against ref**

Compare ref `package.json` deps to target. Add any missing runtime deps (notably `react-router-dom`, `lucide-react`, `clsx`, `tailwind-merge`, `class-variance-authority`, and any Radix/`@wordpress/*` packages the ported components import).

```bash
diff <(jq -S '.dependencies' ../easycommerce-fakerpress/package.json) <(jq -S '.dependencies' package.json)
diff <(jq -S '.devDependencies' ../easycommerce-fakerpress/package.json) <(jq -S '.devDependencies' package.json)
```

Install the missing ones (versions matched to ref):

```bash
yarn add react-router-dom lucide-react clsx tailwind-merge class-variance-authority
# plus any others the diff surfaces
```

- [ ] **Step 4: Reconcile Tailwind content globs + components.json**

Ensure `tailwind.config.js` `content` includes `./src/admin/**/*.{ts,tsx}`. Copy ref `tailwind.config.js` theme extensions if the ported CSS relies on them. Ensure `components.json` `aliases` match (`@/admin/...`).

- [ ] **Step 5: Build**

```bash
yarn install
yarn build
```

Expected: build succeeds; `build/admin.js` and `build/admin.css` emitted, no TS errors.

- [ ] **Step 6: Fix any unresolved imports / type errors**

Resolve build errors (missing dep, alias miss, removed-file import). Re-run `yarn build` until green.

- [ ] **Step 7: Commit**

```bash
git add -A && git commit -m "build: reconcile webpack/tsconfig/tailwind/deps for ported SPA; green build"
```

---

## Phase 2 — Backend Generators (Attribute, Refund, Log)

> Pattern reference: `includes/Generators/Coupon.php` (generate flow) and `includes/Controllers/Coupon.php` (controller). All three generators extend `FluentCartFakerPress\Abstracts\Generator` and implement `get_resource_type()`, `get_supported_types()`, `get_description()`, `generate_single_item()`. Controllers extend `FluentCartFakerPress\Abstracts\Controller` and implement `get_resource_type()`, `get_resource_type_label()`, `get_rest_base()`, `get_generator_instance()`, plus optional `get_resource_specific_params()`.

### Task 4: Attribute generator + controller

**Files:**
- Create: `includes/Generators/Attribute.php`
- Create: `includes/Controllers/Attribute.php`
- Modify: `class-fluent-cart-fakerpress.php` (register route)
- Test: `tests/php/AttributeGeneratorTest.php`

**Interfaces:**
- Produces: `FluentCartFakerPress\Generators\Attribute::generate_single_item()` → `array{id:int,name:string,slug:string,values:array}` | `WP_Error`.
- Produces: `FluentCartFakerPress\Controllers\Attribute` REST base `attributes`, endpoint `POST /fluent-cart-fakerpress/v1/attributes/generate`.

- [ ] **Step 1: Write the generator**

`includes/Generators/Attribute.php`:

```php
<?php
/**
 * Attribute Generator.
 *
 * @since   1.0.0
 * @package FluentCartFakerPress\Generators
 */

namespace FluentCartFakerPress\Generators;

defined( 'ABSPATH' ) || exit;

use FluentCart\App\Models\AttributeGroup;
use FluentCart\App\Models\AttributeTerm;
use FluentCartFakerPress\Abstracts\Generator;
use WP_Error;

/**
 * Generates Fluent Cart product attribute groups with terms.
 *
 * @since 1.0.0
 */
class Attribute extends Generator {

	/**
	 * Predefined attribute sets mapping a group name to representative terms.
	 *
	 * @var array<string, string[]>
	 */
	private const ATTRIBUTE_SETS = array(
		'Color'    => array( 'Red', 'Blue', 'Green', 'Black', 'White', 'Yellow', 'Purple', 'Orange', 'Pink', 'Gray' ),
		'Size'     => array( 'XS', 'S', 'M', 'L', 'XL', 'XXL', '3XL' ),
		'Material' => array( 'Cotton', 'Polyester', 'Wool', 'Silk', 'Leather', 'Denim', 'Linen', 'Nylon' ),
		'Storage'  => array( '64GB', '128GB', '256GB', '512GB', '1TB', '2TB' ),
		'Style'    => array( 'Classic', 'Modern', 'Vintage', 'Sport', 'Casual', 'Formal' ),
		'Pattern'  => array( 'Solid', 'Striped', 'Plaid', 'Polka Dot', 'Floral', 'Geometric' ),
	);

	/**
	 * {@inheritDoc}
	 */
	protected function get_resource_type(): string {
		return 'attribute';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_supported_types(): array {
		return array( 'attributes' => __( 'Product Attribute Groups with Terms', 'fluent-cart-fakerpress' ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return 'Generates Fluent Cart attribute groups (Color, Size, Material, etc.) each with a set of terms for testing product variation functionality.';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function generate_single_item() {
		if ( ! defined( 'FLUENT_CART_VERSION' ) || ! class_exists( AttributeGroup::class ) ) {
			return new WP_Error( 'missing_fluent_cart', __( 'Fluent Cart attribute models not found. Ensure Fluent Cart is active.', 'fluent-cart-fakerpress' ) );
		}

		$set_names = array_keys( self::ATTRIBUTE_SETS );
		$base_name = $this->get_faker()->randomElement( $set_names );
		$title     = $base_name . ' ' . $this->get_faker()->numerify( '###' );
		$slug      = sanitize_title( $title );

		$group = AttributeGroup::create(
			array(
				'title'       => $title,
				'slug'        => $slug,
				'description' => $this->get_faker()->sentence( 8 ),
				'settings'    => array(),
				'serial'      => $this->get_faker()->numberBetween( 1, 999 ),
			)
		);

		if ( ! $group || ! $group->id ) {
			return new WP_Error( 'attribute_creation_failed', __( 'Failed to create attribute group.', 'fluent-cart-fakerpress' ) );
		}

		$all_terms = self::ATTRIBUTE_SETS[ $base_name ];
		$count     = $this->get_faker()->numberBetween( 3, count( $all_terms ) );
		$selected  = $this->get_faker()->randomElements( $all_terms, $count, false );
		$values    = array();

		foreach ( $selected as $i => $label ) {
			$term = AttributeTerm::create(
				array(
					'group_id'    => $group->id,
					'serial'      => $i + 1,
					'title'       => $label,
					'slug'        => sanitize_title( $title . '-' . $label ),
					'description' => '',
					'settings'    => array(),
				)
			);
			if ( $term && $term->id ) {
				$values[] = array(
					'id'    => (int) $term->id,
					'label' => $label,
				);
			}
		}

		return array(
			'id'     => (int) $group->id,
			'name'   => $title,
			'slug'   => $slug,
			'values' => $values,
		);
	}
}
```

- [ ] **Step 2: Write the controller**

`includes/Controllers/Attribute.php`:

```php
<?php
/**
 * Attribute Generator REST Controller.
 *
 * @since   1.0.0
 * @package FluentCartFakerPress\Controllers
 */

namespace FluentCartFakerPress\Controllers;

use FluentCartFakerPress\Abstracts\Controller;
use FluentCartFakerPress\Abstracts\Generator;
use FluentCartFakerPress\Generators\Attribute as AttributeGenerator;

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for attribute generation.
 *
 * @since 1.0.0
 */
class Attribute extends Controller {

	/**
	 * {@inheritDoc}
	 */
	protected function get_resource_type(): string {
		return 'attribute';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_resource_type_label(): string {
		return __( 'Attribute', 'fluent-cart-fakerpress' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_rest_base(): string {
		return 'attributes';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_generator_instance(): Generator {
		return new AttributeGenerator();
	}
}
```

- [ ] **Step 3: Register the route**

In `class-fluent-cart-fakerpress.php`, add `use FluentCartFakerPress\Controllers\Attribute;` with the other controller imports, and add `new Attribute(),` to the `$controllers` array in `register_rest_routes()`.

- [ ] **Step 4: Write the smoke test**

`tests/php/AttributeGeneratorTest.php`:

```php
<?php
use FluentCartFakerPress\Generators\Attribute;
use PHPUnit\Framework\TestCase;

class AttributeGeneratorTest extends TestCase {
	public function test_generate_single_attribute_returns_group_with_terms(): void {
		if ( ! defined( 'FLUENT_CART_VERSION' ) ) {
			$this->markTestSkipped( 'Fluent Cart not loaded in test env.' );
		}
		$gen = new Attribute();
		$gen->set_locale( 'en_US' );
		$gen->set_faker();
		$gen->set_generation_params( array() );
		$result = $gen->generate( 1 );
		$this->assertIsArray( $result );
		$this->assertCount( 1, $result );
		$this->assertArrayHasKey( 'id', $result[0] );
		$this->assertNotEmpty( $result[0]['values'] );
	}
}
```

- [ ] **Step 5: Run the test**

```bash
composer dump-autoload
vendor/bin/phpunit tests/php/AttributeGeneratorTest.php
```

Expected: PASS, or SKIPPED if Fluent Cart isn't loaded in the harness (log which).

- [ ] **Step 6: Commit**

```bash
git add includes/Generators/Attribute.php includes/Controllers/Attribute.php class-fluent-cart-fakerpress.php tests/php/AttributeGeneratorTest.php
git commit -m "feat: add Attribute generator + REST controller"
```

### Task 5: Refund generator + controller

**Files:**
- Create: `includes/Generators/Refund.php`
- Create: `includes/Controllers/Refund.php`
- Modify: `class-fluent-cart-fakerpress.php`
- Test: `tests/php/RefundGeneratorTest.php`

**Interfaces:**
- Produces: `FluentCartFakerPress\Generators\Refund::generate_single_item()` → `array{id:int,order_id:int,amount:float,status:string,type:string}` | `WP_Error`.
- Produces: REST base `refunds`, `POST /fluent-cart-fakerpress/v1/refunds/generate`.

- [ ] **Step 1: Write the generator**

`includes/Generators/Refund.php`:

```php
<?php
/**
 * Refund Generator.
 *
 * @since   1.0.0
 * @package FluentCartFakerPress\Generators
 */

namespace FluentCartFakerPress\Generators;

defined( 'ABSPATH' ) || exit;

use FluentCart\App\Helpers\Status;
use FluentCart\App\Models\Order;
use FluentCart\App\Models\OrderTransaction;
use FluentCartFakerPress\Abstracts\Generator;
use WP_Error;

/**
 * Generates refund transactions against existing Fluent Cart orders.
 *
 * @since 1.0.0
 */
class Refund extends Generator {

	/**
	 * Payment methods used for generated refunds.
	 *
	 * @var string[]
	 */
	private const GATEWAYS = array( 'stripe', 'paypal', 'square', 'cod', 'bank_transfer' );

	/**
	 * {@inheritDoc}
	 */
	protected function get_resource_type(): string {
		return 'refund';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_supported_types(): array {
		return array(
			'full'    => __( 'Full refund', 'fluent-cart-fakerpress' ),
			'partial' => __( 'Partial refund', 'fluent-cart-fakerpress' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return 'Generates full and partial refund transactions linked to existing Fluent Cart orders across multiple payment methods.';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function generate_single_item() {
		if ( ! defined( 'FLUENT_CART_VERSION' ) || ! class_exists( OrderTransaction::class ) ) {
			return new WP_Error( 'missing_fluent_cart', __( 'Fluent Cart transaction model not found. Ensure Fluent Cart is active.', 'fluent-cart-fakerpress' ) );
		}

		$order = Order::query()->inRandomOrder()->first();
		if ( ! $order ) {
			return new WP_Error( 'no_eligible_order', __( 'No orders found for refund generation. Generate some orders first.', 'fluent-cart-fakerpress' ) );
		}

		$total    = (float) ( $order->total_amount ?? $order->total ?? 0 );
		$currency = (string) ( $order->currency ?? 'USD' );

		if ( $this->get_faker()->boolean( 50 ) || $total <= 0 ) {
			$type   = 'full';
			$amount = $total;
		} else {
			$type   = 'partial';
			$amount = round( $total * $this->get_faker()->randomFloat( 2, 0.1, 0.9 ), 2 );
		}

		$gateway = $this->get_faker()->randomElement(
			$this->generation_params['payment_gateways'] ?? self::GATEWAYS
		);

		$txn = OrderTransaction::create(
			array(
				'order_id'         => $order->id,
				'order_type'       => 'order',
				'transaction_type' => Status::TRANSACTION_TYPE_REFUND,
				'payment_method'   => $gateway,
				'payment_mode'     => 'test',
				'currency'         => $currency,
				'status'           => Status::TRANSACTION_SUCCEEDED,
				'total'            => (int) round( $amount * 100 ),
				'uuid'             => $this->get_faker()->uuid(),
				'created_at'       => current_time( 'mysql' ),
			)
		);

		if ( ! $txn || ! $txn->id ) {
			return new WP_Error( 'refund_creation_failed', __( 'Failed to create refund transaction.', 'fluent-cart-fakerpress' ) );
		}

		return array(
			'id'              => (int) $txn->id,
			'order_id'        => (int) $order->id,
			'amount'          => $amount,
			'currency'        => $currency,
			'status'          => Status::TRANSACTION_SUCCEEDED,
			'type'            => $type,
			'payment_gateway' => $gateway,
		);
	}
}
```

> Note: Fluent Cart stores monetary `total` in integer cents on `fct_order_transactions`. The generator multiplies by 100 to match. If a future check shows the column is a decimal, drop the `* 100` and the `(int) round`.

- [ ] **Step 2: Write the controller**

`includes/Controllers/Refund.php` — identical shape to Task 4 Step 2 with:
- `get_resource_type()` → `'refund'`
- `get_resource_type_label()` → `__( 'Refund', 'fluent-cart-fakerpress' )`
- `get_rest_base()` → `'refunds'`
- `get_generator_instance()` → `new \FluentCartFakerPress\Generators\Refund()` (import `Refund as RefundGenerator`)
- Add `get_resource_specific_params()`:

```php
protected function get_resource_specific_params(): array {
	return array(
		'payment_gateways' => array(
			'description'       => __( 'Payment gateways to attribute refunds to.', 'fluent-cart-fakerpress' ),
			'type'              => 'array',
			'items'             => array(
				'type' => 'string',
				'enum' => array( 'stripe', 'paypal', 'square', 'cod', 'bank_transfer' ),
			),
			'default'           => array( 'stripe', 'paypal' ),
			'sanitize_callback' => array( $this, 'sanitize_array' ),
		),
	);
}
```

Full file:

```php
<?php
/**
 * Refund Generator REST Controller.
 *
 * @since   1.0.0
 * @package FluentCartFakerPress\Controllers
 */

namespace FluentCartFakerPress\Controllers;

use FluentCartFakerPress\Abstracts\Controller;
use FluentCartFakerPress\Abstracts\Generator;
use FluentCartFakerPress\Generators\Refund as RefundGenerator;

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for refund generation.
 *
 * @since 1.0.0
 */
class Refund extends Controller {

	protected function get_resource_type(): string {
		return 'refund';
	}

	protected function get_resource_type_label(): string {
		return __( 'Refund', 'fluent-cart-fakerpress' );
	}

	protected function get_rest_base(): string {
		return 'refunds';
	}

	protected function get_generator_instance(): Generator {
		return new RefundGenerator();
	}

	protected function get_resource_specific_params(): array {
		return array(
			'payment_gateways' => array(
				'description'       => __( 'Payment gateways to attribute refunds to.', 'fluent-cart-fakerpress' ),
				'type'              => 'array',
				'items'             => array(
					'type' => 'string',
					'enum' => array( 'stripe', 'paypal', 'square', 'cod', 'bank_transfer' ),
				),
				'default'           => array( 'stripe', 'paypal' ),
				'sanitize_callback' => array( $this, 'sanitize_array' ),
			),
		);
	}
}
```

- [ ] **Step 3: Register the route**

Add `use FluentCartFakerPress\Controllers\Refund;` and `new Refund(),` to `$controllers` in `register_rest_routes()`.

- [ ] **Step 4: Write the smoke test**

`tests/php/RefundGeneratorTest.php` — mirror Task 4 Step 4 (class `RefundGeneratorTest`, generator `FluentCartFakerPress\Generators\Refund`). Assert result has `order_id` and `amount` keys; allow `WP_Error` (skip-assert) when no orders exist:

```php
<?php
use FluentCartFakerPress\Generators\Refund;
use PHPUnit\Framework\TestCase;

class RefundGeneratorTest extends TestCase {
	public function test_generate_refund_or_reports_no_order(): void {
		if ( ! defined( 'FLUENT_CART_VERSION' ) ) {
			$this->markTestSkipped( 'Fluent Cart not loaded in test env.' );
		}
		$gen = new Refund();
		$gen->set_locale( 'en_US' );
		$gen->set_faker();
		$gen->set_generation_params( array() );
		$result = $gen->generate( 1 );
		$this->assertIsArray( $result );
		if ( ! empty( $result ) ) {
			$this->assertArrayHasKey( 'order_id', $result[0] );
			$this->assertArrayHasKey( 'amount', $result[0] );
		}
	}
}
```

- [ ] **Step 5: Run the test**

```bash
vendor/bin/phpunit tests/php/RefundGeneratorTest.php
```

Expected: PASS or SKIPPED.

- [ ] **Step 6: Commit**

```bash
git add includes/Generators/Refund.php includes/Controllers/Refund.php class-fluent-cart-fakerpress.php tests/php/RefundGeneratorTest.php
git commit -m "feat: add Refund generator + REST controller"
```

### Task 6: Log generator + controller

**Files:**
- Create: `includes/Generators/Log.php`
- Create: `includes/Controllers/Log.php`
- Modify: `class-fluent-cart-fakerpress.php`
- Test: `tests/php/LogGeneratorTest.php`

**Interfaces:**
- Produces: `FluentCartFakerPress\Generators\Log::generate_single_item()` → `array{id:int,module_type:string,title:string,log_type:string}` | `WP_Error`.
- Produces: REST base `logs`, `POST /fluent-cart-fakerpress/v1/logs/generate`.

- [ ] **Step 1: Write the generator**

`includes/Generators/Log.php`:

```php
<?php
/**
 * Log (Activity) Generator.
 *
 * @since   1.0.0
 * @package FluentCartFakerPress\Generators
 */

namespace FluentCartFakerPress\Generators;

defined( 'ABSPATH' ) || exit;

use FluentCart\App\Models\Activity;
use FluentCartFakerPress\Abstracts\Generator;
use WP_Error;

/**
 * Generates Fluent Cart activity log entries.
 *
 * @since 1.0.0
 */
class Log extends Generator {

	/**
	 * Module types and the human module names used in titles.
	 *
	 * @var array<string, string>
	 */
	private const MODULES = array(
		'Order'    => 'order',
		'Product'  => 'product',
		'Customer' => 'customer',
		'Coupon'   => 'coupon',
		'System'   => 'system',
	);

	/**
	 * Log severities.
	 *
	 * @var string[]
	 */
	private const LOG_TYPES = array( 'info', 'info', 'info', 'success', 'warning', 'error' );

	/**
	 * Title templates keyed loosely by intent.
	 *
	 * @var string[]
	 */
	private const TITLES = array(
		'%s created',
		'%s updated',
		'%s deleted',
		'%s viewed',
		'%s payment processed',
		'%s refunded',
	);

	/**
	 * {@inheritDoc}
	 */
	protected function get_resource_type(): string {
		return 'log';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_supported_types(): array {
		return array(
			'info'    => __( 'Info', 'fluent-cart-fakerpress' ),
			'warning' => __( 'Warning', 'fluent-cart-fakerpress' ),
			'error'   => __( 'Error', 'fluent-cart-fakerpress' ),
			'success' => __( 'Success', 'fluent-cart-fakerpress' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return 'Generates Fluent Cart activity log entries across orders, products, customers, coupons, and system events with contextual titles and notes.';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function generate_single_item() {
		if ( ! defined( 'FLUENT_CART_VERSION' ) || ! class_exists( Activity::class ) ) {
			return new WP_Error( 'missing_fluent_cart', __( 'Fluent Cart Activity model not found. Ensure Fluent Cart is active.', 'fluent-cart-fakerpress' ) );
		}

		$module_names = array_keys( self::MODULES );
		$module_name  = $this->get_faker()->randomElement( $module_names );
		$module_type  = self::MODULES[ $module_name ];
		$log_type     = $this->get_faker()->randomElement(
			$this->generation_params['log_types'] ?? self::LOG_TYPES
		);
		$title        = sprintf( $this->get_faker()->randomElement( self::TITLES ), $module_name );

		$activity = Activity::create(
			array(
				'status'      => $log_type,
				'log_type'    => $log_type,
				'module_id'   => $this->get_faker()->numberBetween( 1, 9999 ),
				'module_type' => $module_type,
				'module_name' => $module_name,
				'title'       => $title,
				'content'     => $this->get_faker()->sentence( 10 ),
				'user_id'     => get_current_user_id() ?: 1,
				'read_status' => $this->get_faker()->boolean( 40 ) ? 'read' : 'unread',
				'created_by'  => get_current_user_id() ?: 1,
			)
		);

		if ( ! $activity || ! $activity->id ) {
			return new WP_Error( 'log_creation_failed', __( 'Failed to create activity log entry.', 'fluent-cart-fakerpress' ) );
		}

		return array(
			'id'          => (int) $activity->id,
			'module_type' => $module_type,
			'module_name' => $module_name,
			'title'       => $title,
			'log_type'    => $log_type,
		);
	}
}
```

- [ ] **Step 2: Write the controller**

`includes/Controllers/Log.php`:

```php
<?php
/**
 * Log Generator REST Controller.
 *
 * @since   1.0.0
 * @package FluentCartFakerPress\Controllers
 */

namespace FluentCartFakerPress\Controllers;

use FluentCartFakerPress\Abstracts\Controller;
use FluentCartFakerPress\Abstracts\Generator;
use FluentCartFakerPress\Generators\Log as LogGenerator;

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for activity log generation.
 *
 * @since 1.0.0
 */
class Log extends Controller {

	protected function get_resource_type(): string {
		return 'log';
	}

	protected function get_resource_type_label(): string {
		return __( 'Log', 'fluent-cart-fakerpress' );
	}

	protected function get_rest_base(): string {
		return 'logs';
	}

	protected function get_generator_instance(): Generator {
		return new LogGenerator();
	}

	protected function get_resource_specific_params(): array {
		return array(
			'log_types' => array(
				'description'       => __( 'Severity types to generate.', 'fluent-cart-fakerpress' ),
				'type'              => 'array',
				'items'             => array(
					'type' => 'string',
					'enum' => array( 'info', 'success', 'warning', 'error' ),
				),
				'default'           => array( 'info', 'success', 'warning', 'error' ),
				'sanitize_callback' => array( $this, 'sanitize_array' ),
			),
		);
	}
}
```

- [ ] **Step 3: Register the route**

Add `use FluentCartFakerPress\Controllers\Log;` and `new Log(),` to `$controllers`.

- [ ] **Step 4: Write the smoke test**

`tests/php/LogGeneratorTest.php` — mirror Task 4 Step 4 (class `LogGeneratorTest`, generator `FluentCartFakerPress\Generators\Log`); assert `module_type` + `title` keys present.

- [ ] **Step 5: Run the test**

```bash
vendor/bin/phpunit tests/php/LogGeneratorTest.php
```

Expected: PASS or SKIPPED.

- [ ] **Step 6: Commit**

```bash
git add includes/Generators/Log.php includes/Controllers/Log.php class-fluent-cart-fakerpress.php tests/php/LogGeneratorTest.php
git commit -m "feat: add Log (Activity) generator + REST controller"
```

---

## Phase 3 — MCP Layer (WordPress Abilities API)

> Ref source: `easycommerce-fakerpress/includes/Abstracts/Ability.php`, `includes/MCP/MCP_Server.php`, `includes/MCP/Abilities/Generate_*.php`. The layer registers one ability per generator on `wp_abilities_api_init`, and one MCP server on `mcp_adapter_init` exposing those abilities as tools. It degrades to no-op when the abilities-api / mcp-adapter dependencies are absent.

### Task 7: Port the Ability abstract + MCP server

**Files:**
- Create: `includes/Abstracts/Ability.php`
- Create: `includes/MCP/MCP_Server.php`
- Modify: `class-fluent-cart-fakerpress.php` (bootstrap MCP server)

**Interfaces:**
- Produces: `FluentCartFakerPress\Abstracts\Ability` base class (matches ref's public surface).
- Produces: `FluentCartFakerPress\MCP\MCP_Server` with `const SERVER_ID = 'fluent-cart-fakerpress'`.

- [ ] **Step 1: Copy + rewire the Ability abstract**

```bash
cp ../easycommerce-fakerpress/includes/Abstracts/Ability.php includes/Abstracts/Ability.php
sed -i \
  -e 's/EasyCommerceFakerPress/FluentCartFakerPress/g' \
  -e 's/easycommerce-fakerpress/fluent-cart-fakerpress/g' \
  -e 's/EasyCommerce FakerPress/Fluent Cart FakerPress/g' \
  includes/Abstracts/Ability.php
```

- [ ] **Step 2: Copy + rewire the MCP server**

```bash
cp ../easycommerce-fakerpress/includes/MCP/MCP_Server.php includes/MCP/MCP_Server.php
sed -i \
  -e 's/EasyCommerceFakerPress/FluentCartFakerPress/g' \
  -e 's/easycommerce-fakerpress/fluent-cart-fakerpress/g' \
  -e 's/EasyCommerce FakerPress/Fluent Cart FakerPress/g' \
  includes/MCP/MCP_Server.php
```

- [ ] **Step 3: Reconcile the ability registry in MCP_Server**

Open `includes/MCP/MCP_Server.php`. Find where it lists/registers the `Generate_*` ability classes. Remove the `Generate_Product_Reviews` entry. Keep the 13: Products, Customers, Orders, Coupons, Product_Variations, Shipping_Plans, Tax_Classes, Transactions, Cart_Sessions, Locations, Attributes, Refunds, Logs.

- [ ] **Step 4: Bootstrap the MCP server**

In `class-fluent-cart-fakerpress.php`, in the same init path that registers REST routes (guarded by `check_dependencies()`), instantiate/boot the MCP server (mirror how ref's main class calls `MCP_Server`). Add the `use FluentCartFakerPress\MCP\MCP_Server;` import. Confirm the boot is hooked (ref hooks `mcp_adapter_init` inside the class), not called eagerly.

- [ ] **Step 5: Lint**

```bash
composer dump-autoload
vendor/bin/phpcs includes/Abstracts/Ability.php includes/MCP/MCP_Server.php --standard=phpcs.xml || true
php -l includes/Abstracts/Ability.php && php -l includes/MCP/MCP_Server.php
```

Expected: `No syntax errors detected` for both.

- [ ] **Step 6: Commit**

```bash
git add includes/Abstracts/Ability.php includes/MCP/MCP_Server.php class-fluent-cart-fakerpress.php
git commit -m "feat: port MCP server + Ability abstract (WordPress Abilities API)"
```

### Task 8: Port the 13 generate abilities

**Files:**
- Create: `includes/MCP/Abilities/Generate_*.php` (13)

**Interfaces:**
- Consumes: `FluentCartFakerPress\Abstracts\Ability`, the existing 10 generators + the 3 from Phase 2.
- Each ability id namespaced `fluent-cart-fakerpress/generate-<resource>`.

- [ ] **Step 1: Copy all abilities except Product_Reviews + rewire**

```bash
mkdir -p includes/MCP/Abilities
for f in ../easycommerce-fakerpress/includes/MCP/Abilities/Generate_*.php; do
  base=$(basename "$f")
  [ "$base" = "Generate_Product_Reviews.php" ] && continue
  cp "$f" "includes/MCP/Abilities/$base"
done
sed -i \
  -e 's/EasyCommerceFakerPress/FluentCartFakerPress/g' \
  -e 's/easycommerce-fakerpress/fluent-cart-fakerpress/g' \
  -e 's/EasyCommerce FakerPress/Fluent Cart FakerPress/g' \
  -e 's/EasyCommerce/Fluent Cart/g' \
  includes/MCP/Abilities/*.php
```

- [ ] **Step 2: Verify each ability references an existing generator + supported params**

For each `Generate_*.php`, confirm the generator class it instantiates exists under `FluentCartFakerPress\Generators\` and the input schema matches that generator's controller `get_resource_specific_params()`. Fix any ability whose schema still describes EasyCommerce-only params (e.g. attribute `type` values) to match the Fluent Cart generators authored in Phase 2.

- [ ] **Step 3: Confirm no Product_Review ability remains**

```bash
ls includes/MCP/Abilities | grep -i review && echo "REMOVE IT" || echo "clean"
```

- [ ] **Step 4: Lint all abilities**

```bash
composer dump-autoload
for f in includes/MCP/Abilities/*.php; do php -l "$f"; done
```

Expected: `No syntax errors detected` for each.

- [ ] **Step 5: Commit**

```bash
git add includes/MCP/Abilities
git commit -m "feat: port 13 MCP generate abilities (drop product reviews)"
```

---

## Phase 4 — Branding + Verification

### Task 9: Readme + dependency docs

**Files:**
- Modify: `readme.txt`, `README.md`, `docs/features.md` (mention MCP + new generators)

- [ ] **Step 1: Document MCP dependency**

Add a section to `readme.txt` (and `README.md`) noting the MCP integration requires the WordPress Abilities API (bundled WP 6.9+, else install) and the `mcp-adapter` plugin, and that the feature is optional/degrades gracefully.

- [ ] **Step 2: List new generators**

Update the generators list in `readme.txt`/`docs/features.md` to include Attributes, Refunds, Logs (13 total).

- [ ] **Step 3: Commit**

```bash
git add readme.txt README.md docs/features.md
git commit -m "docs: document MCP integration and new generators"
```

### Task 10: Full build + browser smoke verification

**Files:** none (verification only).

- [ ] **Step 1: Clean build**

```bash
yarn build
```

Expected: green; `build/admin.js` + `build/admin.css` present.

- [ ] **Step 2: Load admin page in a real browser (Chrome DevTools MCP)**

Use the `wp-dev-skills:wp-admin-browser` skill / Chrome DevTools MCP: log into WP admin, navigate to the Fluent Cart FakerPress menu page. Confirm:
- SPA mounts on `#fluent-cart-fakerpress-root` (no console errors).
- Sidebar + home generator grid render with 13 generators.
- Navigating to `#/generator/attributes` (and `/refunds`, `/logs`) renders the schema-driven config form.

- [ ] **Step 3: Run a small batch end-to-end**

In the Products generator, set count = 2, run. Confirm REST `POST /fluent-cart-fakerpress/v1/products/generate` returns 200, a success toast appears, and recent-activity/preview updates.

- [ ] **Step 4: Run each new generator with count = 1**

Run Attributes, Refunds (after ensuring ≥1 order exists), Logs at count = 1. Confirm 200 + success, and DB rows created:

```bash
wp db query "SELECT COUNT(*) FROM wp_fct_atts_groups;"
wp db query "SELECT COUNT(*) FROM wp_fct_order_transactions WHERE transaction_type='refund';"
wp db query "SELECT COUNT(*) FROM wp_fct_activity;"
```

Expected: counts increased. (Refund reports a friendly error toast if no orders exist — generate orders first.)

- [ ] **Step 5: Run the PHP test suite (best-effort)**

```bash
vendor/bin/phpunit tests/php
```

Expected: PASS or documented SKIPs when Fluent Cart isn't bootstrapped in the harness. Log the outcome.

- [ ] **Step 6: Final commit + open PR**

```bash
git add -A && git commit -m "chore: verification fixups for admin UI parity" --allow-empty
git push -u origin feat/admin-ui-parity
gh pr create --fill --base trunk
```

---

## Self-Review

**Spec coverage:**
- Frontend SPA port → Phase 1 (Tasks 1-3). ✓
- Rewire rules (data var, root id, text domain, entry, namespace, URLs) → Task 2 + Global Constraints. ✓
- 13 generators, drop Product_Review → Task 2 Step 5 + Phase 2. ✓
- New generators Attribute/Refund/Log + controllers + route registration → Tasks 4-6. ✓
- Model mapping (AttributeGroup/Term, OrderTransaction refund, Activity) → Tasks 4-6 with exact fillables. ✓
- MCP layer (Ability abstract, MCP_Server, 13 abilities) → Phase 3 (Tasks 7-8). ✓
- Settings/Plugins branding → Task 2 Steps 3-4. ✓
- Verify (build, browser smoke, new-gen runs, PHPUnit) → Task 10. ✓

**Open risks flagged inline:**
- Refund `total` cents assumption (Task 5 note) — verify column scale at implementation.
- MCP ability schemas may still carry EasyCommerce-only param values (Task 8 Step 2) — reconcile against Phase 2 generators.
- PHPUnit harness may not bootstrap Fluent Cart — tests skip gracefully (documented).
