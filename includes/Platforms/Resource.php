<?php
/**
 * Canonical resource names
 *
 * The vocabulary shared by generators, writers and the capability matrix. These are
 * the existing Generator::get_resource_type() values, unchanged — hook names and REST
 * response envelopes are built from them, so renaming one is a breaking change.
 *
 * Note these are the singular resource names, not the REST bases. The two differ, and
 * inconsistently: the `cart_session` resource is served at `cart-sessions` while
 * `tax_class` is served at `tax_classes`. Controllers own that mapping via
 * get_rest_base(); nothing here should try to derive one from the other.
 *
 * @since   1.1.0
 * @package StoreSeeder\Platforms
 */

namespace StoreSeeder\Platforms;

/**
 * Resource name constants.
 *
 * @since 1.1.0
 */
final class Resource {
	const ATTRIBUTE         = 'attribute';
	const CART_SESSION      = 'cart_session';
	const COUPON            = 'coupon';
	const CUSTOMER          = 'customer';
	const LABEL             = 'label';
	const LICENSE           = 'license';
	const LOG               = 'log';
	const ORDER             = 'order';
	const ORDER_TAX_RATE    = 'order_tax_rate';
	const PRODUCT           = 'product';
	const PRODUCT_DOWNLOAD  = 'product_download';
	const PRODUCT_VARIATION = 'product_variation';
	const REFUND            = 'refund';
	const SHIPPING_CLASS    = 'shipping_class';
	const SHIPPING_PLAN     = 'shipping_plan';
	const SUBSCRIPTION      = 'subscription';
	const TAX_CLASS         = 'tax_class';
	const TRANSACTION       = 'transaction';

	/**
	 * Every canonical resource name.
	 *
	 * @since 1.1.0
	 *
	 * @return array<int, string>
	 */
	public static function all(): array {
		return array(
			self::PRODUCT,
			self::PRODUCT_VARIATION,
			self::PRODUCT_DOWNLOAD,
			self::CUSTOMER,
			self::ORDER,
			self::ORDER_TAX_RATE,
			self::TRANSACTION,
			self::REFUND,
			self::COUPON,
			self::SHIPPING_PLAN,
			self::SHIPPING_CLASS,
			self::TAX_CLASS,
			self::ATTRIBUTE,
			self::CART_SESSION,
			self::LABEL,
			self::SUBSCRIPTION,
			self::LICENSE,
			self::LOG,
		);
	}

	/**
	 * Whether a string is a known resource name.
	 *
	 * @since 1.1.0
	 *
	 * @param string $resource_type Candidate resource name.
	 *
	 * @return bool
	 */
	public static function exists( string $resource_type ): bool {
		return in_array( $resource_type, self::all(), true );
	}
}
