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

		return $matrix;
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
