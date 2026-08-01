<?php
/**
 * WooCommerce platform driver
 *
 * @since   1.1.0
 * @package StoreSeeder\Platforms\Drivers\Woo_Commerce
 */

namespace StoreSeeder\Platforms\Drivers\Woo_Commerce;

use StoreSeeder\Platforms\Capability;
use StoreSeeder\Platforms\Platform_Driver;
use StoreSeeder\Platforms\Resource;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce.
 *
 * Writes through WooCommerce's CRUD objects — `WC_Product`, `WC_Order`, `WC_Customer`,
 * `WC_Coupon` — rather than through posts and meta directly. That is the same route
 * WooCommerce's own `wc-smooth-generator` takes, and for the same reason: the CRUD layer is
 * what applies the data store (HPOS or posts), fires the hooks other plugins listen for, and
 * keeps a record valid across a WooCommerce upgrade. Inserting posts by hand produces rows
 * that look right in the database and are invisible to half the admin.
 *
 * Three of StoreSeeder's canonical resources have no WooCommerce equivalent, and the driver
 * says so by name rather than dimming a tile in silence. See `capabilities()`.
 *
 * @since 1.1.0
 */
final class Platform extends Platform_Driver {
	/**
	 * Plugin basename, for the activation check.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	const BASENAME = 'woocommerce/woocommerce.php';

	/**
	 * WooCommerce Subscriptions' basename.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	const SUBSCRIPTIONS_BASENAME = 'woocommerce-subscriptions/woocommerce-subscriptions.php';

	/**
	 * Subscriptions' slug, as reported to the admin.
	 *
	 * Not a directory listing: it is what the UI names when it has to say "this needs
	 * WooCommerce Subscriptions".
	 *
	 * @since 1.1.0
	 * @var string
	 */
	const SUBSCRIPTIONS_SLUG = 'woocommerce-subscriptions';

	/**
	 * Machine identifier.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public function id(): string {
		return 'woocommerce';
	}

	/**
	 * Display name.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'WooCommerce', 'storeseeder' );
	}

	/**
	 * Whether WooCommerce is present.
	 *
	 * The class is the authoritative signal that WooCommerce has actually loaded; the
	 * basename check covers the window before it boots, which is when the dependency
	 * notice runs.
	 *
	 * @since 1.1.0
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return class_exists( 'WooCommerce' ) || $this->is_plugin_active( self::BASENAME );
	}

	/**
	 * WooCommerce's version.
	 *
	 * @since 1.1.0
	 *
	 * @return string|null
	 */
	public function version(): ?string {
		return defined( 'WC_VERSION' ) ? (string) WC_VERSION : null;
	}

	/**
	 * Whether WooCommerce Subscriptions is present.
	 *
	 * `wcs_create_subscription()` is the function the writer calls, so its existence is the
	 * check that matters — a Subscriptions install that failed to load would otherwise be
	 * reported as available and fail per item.
	 *
	 * @since 1.1.0
	 *
	 * @return bool
	 */
	public function is_subscriptions_active(): bool {
		return function_exists( 'wcs_create_subscription' )
			|| $this->is_plugin_active( self::SUBSCRIPTIONS_BASENAME );
	}

	/**
	 * Subscriptions' version, or null when it is not installed.
	 *
	 * @since 1.1.0
	 *
	 * @return string|null
	 */
	public function subscriptions_version(): ?string {
		// A public static property rather than a constant, which is what Subscriptions
		// actually exposes — `WC_Subscriptions::$version`, set in its main file.
		if ( class_exists( 'WC_Subscriptions' ) && property_exists( 'WC_Subscriptions', 'version' ) ) {
			return (string) \WC_Subscriptions::$version;
		}

		return null;
	}

	/**
	 * What this site has installed alongside WooCommerce.
	 *
	 * @since 1.1.0
	 *
	 * @return array<int, array{slug: string, label: string, active: bool, version: string|null}>
	 */
	public function extensions(): array {
		return array(
			array(
				'slug'    => self::SUBSCRIPTIONS_SLUG,
				'label'   => __( 'WooCommerce Subscriptions', 'storeseeder' ),
				'active'  => $this->is_subscriptions_active(),
				'version' => $this->subscriptions_version(),
			),
		);
	}

	/**
	 * Capability matrix.
	 *
	 * Fifteen of the eighteen resources map onto something WooCommerce genuinely stores.
	 * The other three are reported unsupported with the reason, because a seeder that
	 * invented a place to put them would produce rows no WooCommerce screen can show:
	 *
	 * - **Transactions.** WooCommerce records the payment on the order — a transaction id,
	 *   a payment method, a date paid — and has no separate transaction record to create.
	 * - **Labels.** There is no order or customer label in WooCommerce. Product tags exist,
	 *   but they are a product taxonomy, not the same thing.
	 * - **Licences.** Not a core concept, and the extensions that add them each use their
	 *   own storage, so there is nothing canonical to write to.
	 *
	 * Subscriptions is the conditional one: `wcs_create_subscription()` belongs to
	 * WooCommerce Subscriptions, so without it the resource names that plugin and the admin
	 * can say "install WooCommerce Subscriptions" instead of leaving the user guessing.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, Capability|bool>
	 */
	protected function capabilities(): array {
		$matrix = array();

		foreach ( Resource::all() as $resource_type ) {
			$matrix[ $resource_type ] = true;
		}

		// Supported, but not in full — and the writers already say why in their own docblocks.
		// A WooCommerce customer *is* a WordPress user, so `with_account` cannot mean what it
		// means on a platform with a separate customer record; and a shipping class's cost lives
		// on the shipping method, so the class itself has nowhere to keep one.
		$matrix[ Resource::CUSTOMER ]       = Capability::supported_except( array( 'with_account' ) );
		$matrix[ Resource::SHIPPING_CLASS ] = Capability::supported_except( array( 'cost', 'per_item' ) );

		$matrix[ Resource::TRANSACTION ] = Capability::unsupported(
			__( 'WooCommerce records payment details on the order itself — a transaction id, a payment method and a date paid — rather than as separate transaction records.', 'storeseeder' )
		);

		$matrix[ Resource::LABEL ] = Capability::unsupported(
			__( 'WooCommerce has no order or customer labels. Product tags are a product taxonomy, which is a different thing.', 'storeseeder' )
		);

		$matrix[ Resource::LICENSE ] = Capability::unsupported(
			__( 'Licensing is not a WooCommerce core concept, and the extensions that add it each use their own storage.', 'storeseeder' )
		);

		if ( ! $this->is_subscriptions_active() ) {
			$matrix[ Resource::SUBSCRIPTION ] = Capability::missing_extension(
				self::SUBSCRIPTIONS_SLUG,
				__( 'WooCommerce Subscriptions', 'storeseeder' )
			);
		}

		// A WooCommerce coupon has an expiry and no start: `WC_Coupon` carries `date_expires` and
		// nothing else, so a coupon that becomes valid next Tuesday cannot be expressed. The
		// generated one is simply live from the moment it is written.
		$matrix[ Resource::COUPON ] = Capability::supported_except( array( 'starts_at' ) );

		// A WooCommerce shipping method has a cost, a tax status and a title, and no delivery
		// estimate — that is a feature of table-rate plugins, not of core's flat rate. The window is
		// still visible to a shopper, because the generated title carries it.
		$matrix[ Resource::SHIPPING_PLAN ] = Capability::supported_except( array( 'delivery_min', 'delivery_max' ) );

		return $matrix;
	}

	/**
	 * Products and customers a WooCommerce store already has.
	 *
	 * Through the CRUD layer rather than a direct query, for the same reason the writers use it:
	 * `wc_get_products()` honours the same visibility and status rules the shop does, so nothing is
	 * suggested that the store itself would not show.
	 *
	 * @since 1.1.0
	 *
	 * @param string $resource_type Canonical resource name.
	 * @param string $query         The search term.
	 * @param int    $limit         Maximum results.
	 *
	 * @return array<int, array{id: int|string, label: string}>
	 */
	protected function platform_search( string $resource_type, string $query, int $limit ): array {
		if ( Resource::PRODUCT === $resource_type ) {
			return $this->search_products( $query, $limit );
		}

		if ( Resource::CUSTOMER === $resource_type ) {
			return $this->search_customers( $query, $limit );
		}

		return array();
	}

	/**
	 * Published products, by title and then by SKU.
	 *
	 * @since 1.1.0
	 *
	 * @param string $query The search term.
	 * @param int    $limit Maximum results.
	 *
	 * @return array<int, array{id: int|string, label: string}>
	 */
	private function search_products( string $query, int $limit ): array {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return array();
		}

		$args = array(
			'limit'   => $limit,
			'status'  => 'publish',
			'orderby' => 'title',
			'order'   => 'ASC',
			'return'  => 'objects',
		);

		if ( '' !== $query ) {
			// `s` searches the title; a shopkeeper looking for a specific product is as likely to
			// know its SKU, and WooCommerce matches that only through this separate argument.
			$args['s'] = $query;
		}

		$results = array();

		// A term that is only digits is an id as often as it is part of a name — and it is the only
		// way a picker restoring a saved value can turn that value back into a name to show.
		if ( '' !== $query && ctype_digit( $query ) ) {
			$exact = wc_get_product( (int) $query );

			if ( $exact instanceof \WC_Product ) {
				$results[] = array(
					'id'    => (int) $exact->get_id(),
					'label' => $this->product_label( $exact->get_name(), $exact->get_sku(), $exact->get_id() ),
				);
			}
		}

		foreach ( (array) wc_get_products( $args ) as $product ) {
			if ( ! $product instanceof \WC_Product || (string) $product->get_id() === $query ) {
				continue;
			}

			$results[] = array(
				'id'    => (int) $product->get_id(),
				'label' => $this->product_label( $product->get_name(), $product->get_sku(), $product->get_id() ),
			);
		}

		if ( '' !== $query && count( $results ) < $limit ) {
			$results = array_merge( $results, $this->search_products_by_sku( $query, $limit - count( $results ), $results ) );
		}

		return $results;
	}

	/**
	 * A second pass matching the SKU, since `wc_get_products( 's' => … )` searches the title only.
	 *
	 * @since 1.1.0
	 *
	 * @param string                                           $query    The search term.
	 * @param int                                              $limit    How many more to find.
	 * @param array<int, array{id: int|string, label: string}> $existing Already-found results, not to repeat.
	 *
	 * @return array<int, array{id: int|string, label: string}>
	 */
	private function search_products_by_sku( string $query, int $limit, array $existing ): array {
		$seen = array();

		foreach ( $existing as $result ) {
			$seen[ (int) $result['id'] ] = true;
		}

		$found = array();

		foreach ( (array) wc_get_products(
			array(
				'limit'  => $limit + count( $seen ),
				'status' => 'publish',
				'sku'    => $query,
				'return' => 'objects',
			)
		) as $product ) {
			if ( ! $product instanceof \WC_Product || isset( $seen[ (int) $product->get_id() ] ) ) {
				continue;
			}

			$found[] = array(
				'id'    => (int) $product->get_id(),
				'label' => $this->product_label( $product->get_name(), $product->get_sku(), $product->get_id() ),
			);

			if ( count( $found ) >= $limit ) {
				break;
			}
		}

		return $found;
	}

	/**
	 * Users in a shopping role, matched on login, email, display name or nicename.
	 *
	 * @since 1.1.0
	 *
	 * @param string $query The search term.
	 * @param int    $limit Maximum results.
	 *
	 * @return array<int, array{id: int|string, label: string}>
	 */
	private function search_customers( string $query, int $limit ): array {
		$args = array(
			'role__in' => array( 'customer', 'subscriber' ),
			'number'   => $limit,
			'orderby'  => 'display_name',
			'order'    => 'ASC',
		);

		if ( '' !== $query ) {
			// Wildcarded both sides: someone typing "ada" should find "ada@example.test".
			$args['search']         = '*' . $query . '*';
			$args['search_columns'] = array( 'user_login', 'user_email', 'display_name', 'user_nicename' );
		}

		$results = array();

		if ( '' !== $query && ctype_digit( $query ) ) {
			$exact = get_userdata( (int) $query );

			if ( $exact ) {
				$results[] = array(
					'id'    => (int) $exact->ID,
					'label' => $this->customer_label( $exact->display_name, $exact->user_email, (int) $exact->ID ),
				);
			}
		}

		foreach ( get_users( $args ) as $user ) {
			if ( (string) $user->ID === $query ) {
				continue;
			}

			$results[] = array(
				'id'    => (int) $user->ID,
				'label' => $this->customer_label( $user->display_name, $user->user_email, (int) $user->ID ),
			);
		}

		return $results;
	}

	/**
	 * WooCommerce-only generation parameters.
	 *
	 * Three product properties WooCommerce stores and no canonical entity carries, because no
	 * other platform has them: whether a product is featured, whether it appears in the catalogue
	 * and in search, and whether it is taxable. Each is read by the product writer from
	 * `$this->params`; declaring one the writer ignores would recreate the bug this seam exists
	 * to fix.
	 *
	 * @since 1.1.0
	 *
	 * @param string $resource_type Canonical resource name.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	protected function platform_fields( string $resource_type ): array {
		if ( Resource::PRODUCT !== $resource_type ) {
			return array();
		}

		return array(
			'featured_ratio'     => array(
				'description'       => __( 'WooCommerce only. Percentage of products marked featured (0–100).', 'storeseeder' ),
				'type'              => 'integer',
				'minimum'           => 0,
				'maximum'           => 100,
				'default'           => 0,
				'sanitize_callback' => 'absint',
			),
			'catalog_visibility' => array(
				'description'       => __( 'WooCommerce only. Where generated products appear.', 'storeseeder' ),
				'type'              => 'string',
				'enum'              => array( 'visible', 'catalog', 'search', 'hidden' ),
				'default'           => 'visible',
				'sanitize_callback' => 'sanitize_key',
			),
			'tax_status'         => array(
				'description'       => __( 'WooCommerce only. Whether generated products are taxable.', 'storeseeder' ),
				'type'              => 'string',
				'enum'              => array( 'taxable', 'shipping', 'none' ),
				'default'           => 'taxable',
				'sanitize_callback' => 'sanitize_key',
			),
		);
	}

	/**
	 * Writer classes, keyed by canonical resource.
	 *
	 * The three unsupported resources have no entry. A driver that declared support and
	 * shipped no writer is reported as `storeseeder_missing_writer` once, rather than
	 * failing per item — but the honest thing is for the matrix and this map to agree, and
	 * they do.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, string>
	 */
	protected function writer_classes(): array {
		return array(
			Resource::ATTRIBUTE         => Writers\Attribute::class,
			Resource::BRAND             => Writers\Brand::class,
			Resource::CART_SESSION      => Writers\Cart_Session::class,
			Resource::COUPON            => Writers\Coupon::class,
			Resource::CUSTOMER          => Writers\Customer::class,
			Resource::LOG               => Writers\Log::class,
			Resource::ORDER             => Writers\Order::class,
			Resource::ORDER_TAX_RATE    => Writers\Order_Tax_Rate::class,
			Resource::PRODUCT           => Writers\Product::class,
			Resource::PRODUCT_CATEGORY  => Writers\Product_Category::class,
			Resource::PRODUCT_TAG       => Writers\Product_Tag::class,
			Resource::PRODUCT_DOWNLOAD  => Writers\Product_Download::class,
			Resource::PRODUCT_VARIATION => Writers\Product_Variation::class,
			Resource::REFUND            => Writers\Refund::class,
			Resource::SHIPPING_CLASS    => Writers\Shipping_Class::class,
			Resource::SHIPPING_PLAN     => Writers\Shipping_Plan::class,
			Resource::SUBSCRIPTION      => Writers\Subscription::class,
			Resource::TAX_CLASS         => Writers\Tax_Class::class,
		);
	}
}
