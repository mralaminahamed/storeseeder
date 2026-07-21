<?php
/**
 * StoreSeeder MCP Server Registration
 *
 * Registers the MCP server via the WordPress mcp-adapter, exposing all
 * FakerPress data-generation abilities as MCP tools so AI clients such as
 * Claude Desktop and VS Code Copilot can invoke them with natural language.
 *
 * Dependencies (must be installed on the WordPress site):
 *   - abilities-api  (included in WordPress 6.9+, or download from GitHub)
 *   - mcp-adapter    (download from https://github.com/WordPress/mcp-adapter/releases)
 *
 * @package StoreSeeder\MCP
 * @since   2.1.0
 */

namespace StoreSeeder\MCP;

defined( 'ABSPATH' ) || exit;

/**
 * MCP_Server
 *
 * Hooks into `mcp_adapter_init` and creates one MCP server that exposes
 * every registered FakerPress ability as an MCP tool.
 *
 * @since 1.0.0
 */
class MCP_Server {

	/**
	 * Unique server identifier used by the mcp-adapter.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const SERVER_ID = 'storeseeder';

	/**
	 * REST API namespace for the MCP endpoint.
	 * Results in: /wp-json/storeseeder-mcp/mcp
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const REST_NAMESPACE = 'storeseeder-mcp';

	/**
	 * REST API route segment.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const REST_ROUTE = 'mcp';

	/**
	 * Register all WordPress hooks required by this class.
	 *
	 * Called from the plugin's main init() method.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init(): void {
		// Register ability categories + abilities as soon as the Abilities API is ready.
		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_ability_categories' ) );
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );

		// Create the MCP server once the MCP Adapter is initialised.
		add_action( 'mcp_adapter_init', array( $this, 'register_mcp_server' ) );
	}

	/**
	 * Register the ability category that groups all FakerPress abilities.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_ability_categories(): void {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		wp_register_ability_category(
			'storeseeder',
			array(
				'label'       => __( 'StoreSeeder', 'storeseeder' ),
				'description' => __( 'Generate realistic test data for Fluent Cart stores: products, customers, orders, coupons, variations, shipping plans, tax classes, transactions, cart sessions, product attributes, shipping classes, labels, order tax lines, product downloads, and subscriptions.', 'storeseeder' ),
			)
		);
	}

	/**
	 * Register all FakerPress abilities with the Abilities API.
	 *
	 * Each ability maps 1-to-1 with an existing REST controller / generator pair.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		// Guard: FakerPress must be active and Fluent Cart present.
		if ( ! $this->dependencies_met() ) {
			return;
		}

		$abilities = $this->get_ability_definitions();

		foreach ( $abilities as $id => $args ) {
			$name = strtolower( $id );
			if ( ! $name ) {
				continue;
			}
			wp_register_ability( $name, $args );
		}
	}

	/**
	 * Create the MCP server and expose all FakerPress abilities as tools.
	 *
	 * @since 1.0.0
	 * @param mixed $adapter The MCP adapter instance provided by the hook.
	 * @return void
	 */
	public function register_mcp_server( $adapter ): void {
		if ( ! $this->dependencies_met() ) {
			return;
		}

		$tool_ids = array_keys( $this->get_ability_definitions() );

		$adapter->create_server(
			self::SERVER_ID,
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			__( 'StoreSeeder', 'storeseeder' ),
			__( 'Generate realistic test data for Fluent Cart stores. Supports products, customers, orders, coupons, product variations, shipping plans, tax classes, payment transactions, cart sessions, product attributes, shipping classes, labels, order tax lines, product downloads, and subscriptions.', 'storeseeder' ),
			STORESEEDER_VERSION,
			array(
				\WP\MCP\Transport\HttpTransport::class,
			),
			\WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler::class,
			\WP\MCP\Infrastructure\Observability\NullMcpObservabilityHandler::class,
			$tool_ids, // abilities exposed as tools.
			array(),   // resources (none).
			array()    // prompts (none).
		);
	}

	/**
	 * Return the full map of ability-id → $args for every FakerPress generator.
	 *
	 * Keeping all definitions in one place makes it trivial to add or remove
	 * abilities without touching any other files.
	 *
	 * @since 1.0.0
	 * @return array<string, array<string, mixed>>
	 */
	private function get_ability_definitions(): array {
		return array(

			'storeseeder/generate-products'           => array(
				'label'               => __( 'Generate Products', 'storeseeder' ),
				'description'         => __( 'Generate realistic Fluent Cart products with attributes, variations, categories, pricing strategies, and inventory data. Returns an array of created product IDs and summaries.', 'storeseeder' ),
				'category'            => 'storeseeder',
				'input_schema'        => $this->build_input_schema(
					array(
						'product_type'       => array(
							'type'        => 'string',
							'description' => __( 'Type of products to generate. Allowed: simple, variable, grouped, external, digital, mixed. Default: mixed.', 'storeseeder' ),
							'enum'        => array( 'simple', 'variable', 'grouped', 'external', 'digital', 'mixed' ),
							'default'     => 'mixed',
						),
						'price_min'          => array(
							'type'        => 'number',
							'description' => __( 'Minimum product price (USD). Default: 10.', 'storeseeder' ),
							'default'     => 10,
						),
						'price_max'          => array(
							'type'        => 'number',
							'description' => __( 'Maximum product price (USD). Default: 500.', 'storeseeder' ),
							'default'     => 500,
						),
						'include_attributes' => array(
							'type'        => 'boolean',
							'description' => __( 'Generate product attributes (size, colour, material). Default: true.', 'storeseeder' ),
							'default'     => true,
						),
						'variation_count'    => array(
							'type'        => 'integer',
							'description' => __( 'Number of variations per variable product (1–20). Default: 5.', 'storeseeder' ),
							'minimum'     => 1,
							'maximum'     => 20,
							'default'     => 5,
						),
						'manage_stock'       => array(
							'type'        => 'boolean',
							'description' => __( 'Enable stock management and generate inventory levels. Default: true.', 'storeseeder' ),
							'default'     => true,
						),
						'description_length' => array(
							'type'        => 'string',
							'description' => __( 'Length of product descriptions. Allowed: short, medium, long. Default: medium.', 'storeseeder' ),
							'enum'        => array( 'short', 'medium', 'long' ),
							'default'     => 'medium',
						),
					)
				),
				'output_schema'       => $this->build_output_schema( 'products', __( 'Array of generated product objects with id, title, type, variations count, price_range, and stock_status.', 'storeseeder' ) ),
				'execute_callback'    => array( Abilities\Generate_Products::class, 'execute' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			),

			'storeseeder/generate-customers'          => array(
				'label'               => __( 'Generate Customers', 'storeseeder' ),
				'description'         => __( 'Generate realistic WordPress customer accounts with billing/shipping addresses, demographic metadata, purchase history, and loyalty tier assignments.', 'storeseeder' ),
				'category'            => 'storeseeder',
				'input_schema'        => $this->build_input_schema(
					array(
						'customer_types'            => array(
							'type'        => 'array',
							'description' => __( 'Customer segment types to mix. Allowed values: regular, vip, wholesale, guest, returning. Default: ["regular","returning"].', 'storeseeder' ),
							'items'       => array( 'type' => 'string' ),
							'default'     => array( 'regular', 'returning' ),
						),
						'include_billing'           => array(
							'type'        => 'boolean',
							'description' => __( 'Generate billing addresses. Default: true.', 'storeseeder' ),
							'default'     => true,
						),
						'include_shipping'          => array(
							'type'        => 'boolean',
							'description' => __( 'Generate shipping addresses. Default: true.', 'storeseeder' ),
							'default'     => true,
						),
						'different_addresses_ratio' => array(
							'type'        => 'integer',
							'description' => __( 'Percentage of customers with a different shipping address (0–100). Default: 30.', 'storeseeder' ),
							'minimum'     => 0,
							'maximum'     => 100,
							'default'     => 30,
						),
						'simulate_purchase_history' => array(
							'type'        => 'boolean',
							'description' => __( 'Populate realistic purchase history metadata (order counts, spend totals, loyalty tier). Default: true.', 'storeseeder' ),
							'default'     => true,
						),
						'marketing_opt_in_ratio'    => array(
							'type'        => 'integer',
							'description' => __( 'Percentage of customers opted into marketing emails (0–100). Default: 65.', 'storeseeder' ),
							'minimum'     => 0,
							'maximum'     => 100,
							'default'     => 65,
						),
					)
				),
				'output_schema'       => $this->build_output_schema( 'customers', __( 'Array of generated customer objects with id, name, email, billing_country, loyalty_tier, total_orders, and total_spent.', 'storeseeder' ) ),
				'execute_callback'    => array( Abilities\Generate_Customers::class, 'execute' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			),

			'storeseeder/generate-orders'             => array(
				'label'               => __( 'Generate Orders', 'storeseeder' ),
				'description'         => __( 'Generate realistic Fluent Cart orders with line items, addresses, payment details, shipping calculations, tax breakdowns, and fulfilment status. Requires at least one product with variations and one customer to exist.', 'storeseeder' ),
				'category'            => 'storeseeder',
				'input_schema'        => $this->build_input_schema(
					array(
						'order_status'         => array(
							'type'        => 'string',
							'description' => __( 'Status of generated orders. Allowed: pending, processing, completed, cancelled, on_hold, refunded, mixed. Default: mixed.', 'storeseeder' ),
							'enum'        => array( 'pending', 'processing', 'completed', 'cancelled', 'on_hold', 'refunded', 'mixed' ),
							'default'     => 'mixed',
						),
						'customer_type'        => array(
							'type'        => 'string',
							'description' => __( 'Whose customer accounts to attach. Allowed: existing, new, mixed, specific. Default: mixed.', 'storeseeder' ),
							'enum'        => array( 'existing', 'new', 'mixed', 'specific' ),
							'default'     => 'mixed',
						),
						'specific_customer_id' => array(
							'type'        => 'integer',
							'description' => __( 'WordPress user ID to attach all orders to. Only used when customer_type is "specific".', 'storeseeder' ),
							'minimum'     => 1,
						),
						'min_total'            => array(
							'type'        => 'number',
							'description' => __( 'Minimum order total (USD). Default: 10.', 'storeseeder' ),
							'default'     => 10,
						),
						'max_total'            => array(
							'type'        => 'number',
							'description' => __( 'Maximum order total (USD). Default: 1000.', 'storeseeder' ),
							'default'     => 1000,
						),
						'min_items'            => array(
							'type'        => 'integer',
							'description' => __( 'Minimum line items per order. Default: 1.', 'storeseeder' ),
							'minimum'     => 1,
							'default'     => 1,
						),
						'max_items'            => array(
							'type'        => 'integer',
							'description' => __( 'Maximum line items per order (1–20). Default: 5.', 'storeseeder' ),
							'minimum'     => 1,
							'maximum'     => 20,
							'default'     => 5,
						),
						'payment_methods'      => array(
							'type'        => 'array',
							'description' => __( 'Payment methods to distribute across orders. Allowed: stripe, paypal, bank_transfer, cash_on_delivery, credit_card. Default: ["stripe","paypal","bank_transfer"].', 'storeseeder' ),
							'items'       => array( 'type' => 'string' ),
							'default'     => array( 'stripe', 'paypal', 'bank_transfer' ),
						),
					)
				),
				'output_schema'       => $this->build_output_schema( 'orders', __( 'Array of generated order objects with id, order_number, status, total, payment_method, and item count.', 'storeseeder' ) ),
				'execute_callback'    => array( Abilities\Generate_Orders::class, 'execute' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			),

			'storeseeder/generate-coupons'            => array(
				'label'               => __( 'Generate Coupons', 'storeseeder' ),
				'description'         => __( 'Generate realistic Fluent Cart discount coupons with various discount types, usage limits, validity periods, and product or customer restrictions.', 'storeseeder' ),
				'category'            => 'storeseeder',
				'input_schema'        => $this->build_input_schema(
					array(
						'discount_types'    => array(
							'type'        => 'array',
							'description' => __( 'Discount types to include. Allowed: percentage, fixed, free_shipping, products. Default: ["percentage","fixed"].', 'storeseeder' ),
							'items'       => array( 'type' => 'string' ),
							'default'     => array( 'percentage', 'fixed' ),
						),
						'min_percentage'    => array(
							'type'        => 'integer',
							'description' => __( 'Minimum percentage discount (5–95). Default: 10.', 'storeseeder' ),
							'minimum'     => 5,
							'maximum'     => 95,
							'default'     => 10,
						),
						'max_percentage'    => array(
							'type'        => 'integer',
							'description' => __( 'Maximum percentage discount (5–95). Default: 50.', 'storeseeder' ),
							'minimum'     => 5,
							'maximum'     => 95,
							'default'     => 50,
						),
						'min_fixed'         => array(
							'type'        => 'number',
							'description' => __( 'Minimum fixed discount amount (USD). Default: 5.', 'storeseeder' ),
							'default'     => 5,
						),
						'max_fixed'         => array(
							'type'        => 'number',
							'description' => __( 'Maximum fixed discount amount (USD). Default: 100.', 'storeseeder' ),
							'default'     => 100,
						),
						'set_usage_limits'  => array(
							'type'        => 'boolean',
							'description' => __( 'Add usage-limit rules to coupons. Default: true.', 'storeseeder' ),
							'default'     => true,
						),
						'max_uses'          => array(
							'type'        => 'integer',
							'description' => __( 'Maximum total uses per coupon when set_usage_limits is true (1–1000). Default: 100.', 'storeseeder' ),
							'minimum'     => 1,
							'maximum'     => 1000,
							'default'     => 100,
						),
						'validity_min_days' => array(
							'type'        => 'integer',
							'description' => __( 'Minimum coupon validity period in days. Default: 7.', 'storeseeder' ),
							'minimum'     => 1,
							'default'     => 7,
						),
						'validity_max_days' => array(
							'type'        => 'integer',
							'description' => __( 'Maximum coupon validity period in days (1–365). Default: 90.', 'storeseeder' ),
							'minimum'     => 1,
							'maximum'     => 365,
							'default'     => 90,
						),
					)
				),
				'output_schema'       => $this->build_output_schema( 'coupons', __( 'Array of generated coupon objects with id, code, type, offer, status, usage_limit, and validity dates.', 'storeseeder' ) ),
				'execute_callback'    => array( Abilities\Generate_Coupons::class, 'execute' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			),

			'storeseeder/generate-product-variations' => array(
				'label'               => __( 'Generate Product Variations', 'storeseeder' ),
				'description'         => __( 'Generate product variations (size/colour/storage combinations) for existing products. Creates variation records with unique SKUs, individual pricing, stock levels, and dimension metadata. Requires products to exist.', 'storeseeder' ),
				'category'            => 'storeseeder',
				'input_schema'        => $this->build_input_schema(
					array(
						'specific_product_id' => array(
							'type'        => 'integer',
							'description' => __( 'Generate variations only for this product ID. Omit to pick a random eligible product.', 'storeseeder' ),
							'minimum'     => 1,
						),
						'exclude_product_ids' => array(
							'type'        => 'array',
							'description' => __( 'Array of product IDs to skip during generation.', 'storeseeder' ),
							'items'       => array( 'type' => 'integer' ),
							'default'     => array(),
						),
						'manage_stock'        => array(
							'type'        => 'boolean',
							'description' => __( 'Enable inventory tracking for variations. Default: true.', 'storeseeder' ),
							'default'     => true,
						),
						'stock_min'           => array(
							'type'        => 'integer',
							'description' => __( 'Minimum stock quantity per variation. Default: 0.', 'storeseeder' ),
							'minimum'     => 0,
							'default'     => 0,
						),
						'stock_max'           => array(
							'type'        => 'integer',
							'description' => __( 'Maximum stock quantity per variation. Default: 100.', 'storeseeder' ),
							'minimum'     => 1,
							'default'     => 100,
						),
					)
				),
				'output_schema'       => $this->build_output_schema( 'product_variations', __( 'Array of generated variation objects with id, product_id, name, sku, price, stock_quantity, type, status, and attributes.', 'storeseeder' ) ),
				'execute_callback'    => array( Abilities\Generate_Product_Variations::class, 'execute' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			),

			'storeseeder/generate-shipping-plans'     => array(
				'label'               => __( 'Generate Shipping Plans', 'storeseeder' ),
				'description'         => __( 'Generate realistic shipping plans with tiered pricing methods (price-based, weight-based, quantity-based), regional coverage, delivery timeframes, and taxability settings.', 'storeseeder' ),
				'category'            => 'storeseeder',
				'input_schema'        => $this->build_input_schema(
					array(
						'shipping_types' => array(
							'type'        => 'array',
							'description' => __( 'Shipping method types. Allowed: standard, express, overnight, pickup, free, weight_based, flat_rate. Default: ["standard","express","free"].', 'storeseeder' ),
							'items'       => array( 'type' => 'string' ),
							'default'     => array( 'standard', 'express', 'free' ),
						),
						'cost_min'       => array(
							'type'        => 'number',
							'description' => __( 'Minimum shipping cost (USD). Default: 0.', 'storeseeder' ),
							'minimum'     => 0,
							'default'     => 0,
						),
						'cost_max'       => array(
							'type'        => 'number',
							'description' => __( 'Maximum shipping cost (USD). Default: 50.', 'storeseeder' ),
							'minimum'     => 0,
							'default'     => 50,
						),
						'coverage_areas' => array(
							'type'        => 'array',
							'description' => __( 'Geographic coverage. Allowed: domestic, international, regional, worldwide. Default: ["domestic","international"].', 'storeseeder' ),
							'items'       => array( 'type' => 'string' ),
							'default'     => array( 'domestic', 'international' ),
						),
					)
				),
				'output_schema'       => $this->build_output_schema( 'shipping_plans', __( 'Array of generated shipping plan objects with id, name, active status, calculation_base, methods count, and regions count.', 'storeseeder' ) ),
				'execute_callback'    => array( Abilities\Generate_Shipping_Plans::class, 'execute' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			),

			'storeseeder/generate-tax-classes'        => array(
				'label'               => __( 'Generate Tax Classes', 'storeseeder' ),
				'description'         => __( 'Generate tax classes with country/state/city-level rate tables, compound tax configurations, and priority settings for a Fluent Cart store.', 'storeseeder' ),
				'category'            => 'storeseeder',
				'input_schema'        => $this->build_input_schema(
					array(
						'tax_types'        => array(
							'type'        => 'array',
							'description' => __( 'Tax class types to generate. Allowed: standard, reduced, zero, exempt, digital. Default: ["standard","reduced","zero"].', 'storeseeder' ),
							'items'       => array( 'type' => 'string' ),
							'default'     => array( 'standard', 'reduced', 'zero' ),
						),
						'countries'        => array(
							'type'        => 'array',
							'description' => __( 'ISO-2 country codes to generate tax rates for. Default: ["US","CA","GB","AU","DE"].', 'storeseeder' ),
							'items'       => array( 'type' => 'string' ),
							'default'     => array( 'US', 'CA', 'GB', 'AU', 'DE' ),
						),
						'include_compound' => array(
							'type'        => 'boolean',
							'description' => __( 'Include compound tax rate configurations. Default: true.', 'storeseeder' ),
							'default'     => true,
						),
					)
				),
				'output_schema'       => $this->build_output_schema( 'tax_classes', __( 'Array of generated tax class objects with id, name, active status, rates array, and covered regions.', 'storeseeder' ) ),
				'execute_callback'    => array( Abilities\Generate_Tax_Classes::class, 'execute' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			),

			'storeseeder/generate-transactions'       => array(
				'label'               => __( 'Generate Transactions', 'storeseeder' ),
				'description'         => __( 'Generate realistic payment transaction records linked to existing orders. Creates transactions with gateway-specific IDs, amounts, statuses, and currency assignments. Requires orders to exist.', 'storeseeder' ),
				'category'            => 'storeseeder',
				'input_schema'        => $this->build_input_schema(
					array(
						'customer_type'        => array(
							'type'        => 'string',
							'description' => __( 'Filter orders by customer type. Allowed: all, specific, existing_customers_only, new_customers_only. Default: all.', 'storeseeder' ),
							'enum'        => array( 'all', 'specific', 'existing_customers_only', 'new_customers_only' ),
							'default'     => 'all',
						),
						'specific_customer_id' => array(
							'type'        => 'integer',
							'description' => __( 'Only create transactions for orders belonging to this customer ID. Requires customer_type="specific".', 'storeseeder' ),
							'minimum'     => 1,
						),
						'transaction_types'    => array(
							'type'        => 'array',
							'description' => __( 'Transaction types to generate. Allowed: payment, refund, adjustment, fee, commission. Default: ["payment","refund"].', 'storeseeder' ),
							'items'       => array( 'type' => 'string' ),
							'default'     => array( 'payment', 'refund' ),
						),
						'payment_gateways'     => array(
							'type'        => 'array',
							'description' => __( 'Payment gateways to use. Allowed: stripe, paypal, square, authorize_net, braintree, razorpay, mollie. Default: ["stripe","paypal","square"].', 'storeseeder' ),
							'items'       => array( 'type' => 'string' ),
							'default'     => array( 'stripe', 'paypal', 'square' ),
						),
					)
				),
				'output_schema'       => $this->build_output_schema( 'transactions', __( 'Array of generated transaction objects with id, order_id, transaction_id, payment_gateway, amount, currency, status, and type.', 'storeseeder' ) ),
				'execute_callback'    => array( Abilities\Generate_Transactions::class, 'execute' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			),

			'storeseeder/generate-cart-sessions'      => array(
				'label'               => __( 'Generate Cart Sessions', 'storeseeder' ),
				'description'         => __( 'Generate realistic shopping cart sessions including pending, abandoned, completed, and cancelled states. Useful for testing abandoned-cart recovery workflows and marketing-automation integrations.', 'storeseeder' ),
				'category'            => 'storeseeder',
				'input_schema'        => $this->build_input_schema(
					array(
						'customer_type'        => array(
							'type'        => 'string',
							'description' => __( 'Cart owner type. Allowed: existing, new, mixed, specific, guest_only. Default: mixed.', 'storeseeder' ),
							'enum'        => array( 'existing', 'new', 'mixed', 'specific', 'guest_only' ),
							'default'     => 'mixed',
						),
						'specific_customer_id' => array(
							'type'        => 'integer',
							'description' => __( 'Attach all carts to this customer ID. Requires customer_type="specific".', 'storeseeder' ),
							'minimum'     => 1,
						),
						'guest_cart_ratio'     => array(
							'type'        => 'integer',
							'description' => __( 'Percentage of guest (unauthenticated) carts when customer_type is "mixed" (0–100). Default: 40.', 'storeseeder' ),
							'minimum'     => 0,
							'maximum'     => 100,
							'default'     => 40,
						),
						'abandonment_rate'     => array(
							'type'        => 'integer',
							'description' => __( 'Percentage of carts with "abandoned" status (0–100). Default: 30.', 'storeseeder' ),
							'minimum'     => 0,
							'maximum'     => 100,
							'default'     => 30,
						),
						'cart_value_min'       => array(
							'type'        => 'number',
							'description' => __( 'Minimum cart value (USD). Default: 5.', 'storeseeder' ),
							'minimum'     => 0,
							'default'     => 5,
						),
						'cart_value_max'       => array(
							'type'        => 'number',
							'description' => __( 'Maximum cart value (USD). Default: 500.', 'storeseeder' ),
							'minimum'     => 1,
							'default'     => 500,
						),
					)
				),
				'output_schema'       => $this->build_output_schema( 'cart_sessions', __( 'Array of generated cart session objects with hash, user_id, status, items_count, total_amount, customer details, and timestamps.', 'storeseeder' ) ),
				'execute_callback'    => array( Abilities\Generate_Cart_Sessions::class, 'execute' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			),

			'storeseeder/generate-attributes'         => array(
				'label'               => __( 'Generate Attributes', 'storeseeder' ),
				'description'         => __( 'Generate Fluent Cart product attributes (Color, Size, Material, etc.) with option values. Returns an array of created attribute IDs, names, types, and values.', 'storeseeder' ),
				'category'            => 'storeseeder',
				'input_schema'        => $this->build_input_schema( array() ),
				'output_schema'       => $this->build_output_schema( 'attributes', __( 'Array of generated attribute objects with id, name, type, slug, and values array.', 'storeseeder' ) ),
				'execute_callback'    => array( Abilities\Generate_Attributes::class, 'execute' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			),

			'storeseeder/generate-refunds'            => array(
				'label'               => __( 'Generate Refunds', 'storeseeder' ),
				'description'         => __( 'Generate refund records against existing Fluent Cart orders. Requires completed or processing orders to exist. Returns refund IDs, amounts, statuses, and gateway transaction IDs.', 'storeseeder' ),
				'category'            => 'storeseeder',
				'input_schema'        => $this->build_input_schema( array() ),
				'output_schema'       => $this->build_output_schema( 'refunds', __( 'Array of generated refund objects with id, order_id, amount, status, and payment_gateway.', 'storeseeder' ) ),
				'execute_callback'    => array( Abilities\Generate_Refunds::class, 'execute' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			),

			'storeseeder/generate-logs'               => array(
				'label'               => __( 'Generate Logs', 'storeseeder' ),
				'description'         => __( 'Generate activity log entries for orders, products, customers, coupons, refunds, carts, transactions, and system events. Returns log IDs, object types, actions, and severity levels.', 'storeseeder' ),
				'category'            => 'storeseeder',
				'input_schema'        => $this->build_input_schema(
					array(
						'log_types' => array(
							'type'        => 'array',
							'description' => __( 'Log severity types to generate. Default: all types.', 'storeseeder' ),
							'items'       => array(
								'type' => 'string',
								'enum' => array( 'info', 'success', 'warning', 'error' ),
							),
							'default'     => array( 'info', 'success', 'warning', 'error' ),
						),
					)
				),
				'output_schema'       => $this->build_output_schema( 'logs', __( 'Array of generated log objects with id, object, action, type, note, and is_public.', 'storeseeder' ) ),
				'execute_callback'    => array( Abilities\Generate_Logs::class, 'execute' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			),

			'storeseeder/generate-shipping-classes'   => array(
				'label'               => __( 'Generate Shipping Classes', 'storeseeder' ),
				'description'         => __( 'Generate shipping classes that group products with similar shipping requirements, each with a cost and per-item flag, for a Fluent Cart store.', 'storeseeder' ),
				'category'            => 'storeseeder',
				'input_schema'        => $this->build_input_schema( array() ),
				'output_schema'       => $this->build_output_schema( 'shipping_classes', __( 'Array of generated shipping class objects with id, name, cost, and per_item.', 'storeseeder' ) ),
				'execute_callback'    => array( Abilities\Generate_Shipping_Classes::class, 'execute' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			),

			'storeseeder/generate-labels'             => array(
				'label'               => __( 'Generate Labels', 'storeseeder' ),
				'description'         => __( 'Generate labels (tags) and attach them to existing orders and customers for testing Fluent Cart segmentation. Requires existing orders or customers to attach to.', 'storeseeder' ),
				'category'            => 'storeseeder',
				'input_schema'        => $this->build_input_schema( array() ),
				'output_schema'       => $this->build_output_schema( 'labels', __( 'Array of generated label objects with id, value, and attached count.', 'storeseeder' ) ),
				'execute_callback'    => array( Abilities\Generate_Labels::class, 'execute' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			),

			'storeseeder/generate-order-tax-rates'    => array(
				'label'               => __( 'Generate Order Tax Lines', 'storeseeder' ),
				'description'         => __( 'Generate per-order tax lines linking orders to tax rates with the tax amounts collected. Requires existing orders and tax rates.', 'storeseeder' ),
				'category'            => 'storeseeder',
				'input_schema'        => $this->build_input_schema( array() ),
				'output_schema'       => $this->build_output_schema( 'order_tax_rates', __( 'Array of generated order tax line objects with id, order_id, tax_rate_id, and total_tax.', 'storeseeder' ) ),
				'execute_callback'    => array( Abilities\Generate_Order_Tax_Rates::class, 'execute' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			),

			'storeseeder/generate-product-downloads'  => array(
				'label'               => __( 'Generate Product Downloads', 'storeseeder' ),
				'description'         => __( 'Generate downloadable files for products and grant download permissions on existing orders, for testing Fluent Cart digital fulfillment. Requires existing products.', 'storeseeder' ),
				'category'            => 'storeseeder',
				'input_schema'        => $this->build_input_schema( array() ),
				'output_schema'       => $this->build_output_schema( 'product_downloads', __( 'Array of generated product download objects with id, post_id, title, download_identifier, and permission_granted.', 'storeseeder' ) ),
				'execute_callback'    => array( Abilities\Generate_Product_Downloads::class, 'execute' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			),

			'storeseeder/generate-subscriptions'      => array(
				'label'               => __( 'Generate Subscriptions', 'storeseeder' ),
				'description'         => __( 'Generate subscription records against existing orders for testing Fluent Cart recurring-billing views. The subscriptions table ships in core, but active billing requires Fluent Cart Pro. Requires existing orders and products.', 'storeseeder' ),
				'category'            => 'storeseeder',
				'input_schema'        => $this->build_input_schema( array() ),
				'output_schema'       => $this->build_output_schema( 'subscriptions', __( 'Array of generated subscription objects with id, uuid, customer_id, item_name, billing_interval, status, and recurring_total.', 'storeseeder' ) ),
				'execute_callback'    => array( Abilities\Generate_Subscriptions::class, 'execute' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			),

		);
	}

	/**
	 * Build a JSON Schema input_schema array that always includes the common
	 * count + locale + seed parameters, then merges in any resource-specific ones.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, array<string, mixed>> $extra_properties Additional properties.
	 * @return array<string, mixed>
	 */
	private function build_input_schema( array $extra_properties ): array {
		$common = array(
			'count'  => array(
				'type'        => 'integer',
				'description' => __( 'Number of items to generate (1–100). Required.', 'storeseeder' ),
				'minimum'     => 1,
				'maximum'     => 100,
			),
			'locale' => array(
				'type'        => 'string',
				'description' => __( 'Faker locale for generated data (e.g. en_US, fr_FR, de_DE, ja_JP). Affects names, addresses, and phone numbers. Default: en_US.', 'storeseeder' ),
				'default'     => 'en_US',
			),
			'seed'   => array(
				'type'        => 'integer',
				'description' => __( 'Optional integer seed for reproducible data generation. Omit for random output.', 'storeseeder' ),
				'minimum'     => 1,
			),
		);

		$properties = array_merge( $common, $extra_properties );

		return array(
			'type'       => 'object',
			'required'   => array( 'count' ),
			'properties' => $properties,
		);
	}

	/**
	 * Build a standard JSON Schema output_schema for the generated-items envelope.
	 *
	 * @since 1.0.0
	 *
	 * @param string $resource_key  Key in the response object that holds the items array.
	 * @param string $items_description  Human-readable description of the items.
	 * @return array<string, mixed>
	 */
	private function build_output_schema( string $resource_key, string $items_description ): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'message'     => array(
					'type'        => 'string',
					'description' => __( 'Human-readable summary of the generation result.', 'storeseeder' ),
				),
				$resource_key => array(
					'type'        => 'array',
					'description' => $items_description,
					'items'       => array( 'type' => 'object' ),
				),
			),
		);
	}

	/**
	 * Shared permission callback for all FakerPress abilities.
	 *
	 * Only users with the manage_options capability (administrators) may execute
	 * FakerPress abilities. This mirrors the existing REST API permission check
	 * already enforced in Controller::generate_items_permissions_check().
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public function permission_callback(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Return true only when both Fluent Cart and the Abilities API are active.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	private function dependencies_met(): bool {
		return function_exists( 'wp_register_ability' )
				&& storeseeder()->check_dependencies();
	}
}
