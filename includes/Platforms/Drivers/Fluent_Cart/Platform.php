<?php
/**
 * Fluent Cart platform driver
 *
 * @since   1.1.0
 * @package StoreSeeder\Platforms\Drivers\Fluent_Cart
 */

namespace StoreSeeder\Platforms\Drivers\Fluent_Cart;

use StoreSeeder\Platforms\Platform_Driver;
use StoreSeeder\Platforms\Resource;

defined( 'ABSPATH' ) || exit;

/**
 * Fluent Cart.
 *
 * The reference driver: every resource StoreSeeder knows about exists here as a
 * first-class record, which is why the plugin was built against it first.
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
	const BASENAME = 'fluent-cart/fluent-cart.php';

	/**
	 * Machine identifier.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public function id(): string {
		return 'fluent-cart';
	}

	/**
	 * Display name.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Fluent Cart', 'storeseeder' );
	}

	/**
	 * Whether Fluent Cart is present.
	 *
	 * The constant is the authoritative signal that Fluent Cart has actually loaded;
	 * the basename check covers the window before it boots, which is when the
	 * dependency notice runs.
	 *
	 * @since 1.1.0
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return defined( 'FLUENTCART_VERSION' ) || $this->is_plugin_active( self::BASENAME );
	}

	/**
	 * Fluent Cart's version.
	 *
	 * @since 1.1.0
	 *
	 * @return string|null
	 */
	public function version(): ?string {
		return defined( 'FLUENTCART_VERSION' ) ? (string) FLUENTCART_VERSION : null;
	}

	/**
	 * Capability matrix.
	 *
	 * Everything is supported. Subscriptions are worth a note: the table ships in
	 * Fluent Cart core, so records generate without Pro — it is *billing* them that
	 * needs Pro, which is not something a seeder does. So this is a genuine yes, not a
	 * conditional one.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, bool>
	 */
	protected function capabilities(): array {
		$matrix = array();

		foreach ( Resource::all() as $resource_type ) {
			$matrix[ $resource_type ] = true;
		}

		return $matrix;
	}

	/**
	 * Writer classes, keyed by canonical resource.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, string>
	 */
	protected function writer_classes(): array {
		return array(
			Resource::ATTRIBUTE         => Writers\Attribute::class,
			Resource::CART_SESSION      => Writers\Cart_Session::class,
			Resource::COUPON            => Writers\Coupon::class,
			Resource::CUSTOMER          => Writers\Customer::class,
			Resource::LABEL             => Writers\Label::class,
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
			Resource::TRANSACTION       => Writers\Transaction::class,
		);
	}
}
