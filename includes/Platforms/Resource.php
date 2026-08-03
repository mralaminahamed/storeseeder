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
	const BRAND             = 'brand';
	const CART_SESSION      = 'cart_session';
	const COUPON            = 'coupon';
	const CUSTOMER          = 'customer';
	const LABEL             = 'label';
	const LICENSE           = 'license';
	const LOG               = 'log';
	const MEDIA             = 'media';
	const ORDER             = 'order';
	const ORDER_TAX_RATE    = 'order_tax_rate';
	const PRODUCT           = 'product';
	const PRODUCT_CATEGORY  = 'product_category';
	const PRODUCT_DOWNLOAD  = 'product_download';
	const PRODUCT_TAG       = 'product_tag';
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
			// Before products, because this list is generation order and the cleanup walks it
			// backwards: a term has to outlive the products carrying it, or it is gone before
			// the products that reference it are.
			self::PRODUCT_CATEGORY,
			self::PRODUCT_TAG,
			self::BRAND,
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
	 * Resources that exist only as a side effect of writing something else.
	 *
	 * Deliberately not in `all()`. That list is what a recipe may put in a plan and what the
	 * capability matrix describes, and neither is true of these: nothing generates a media
	 * attachment on its own, and a recipe asking for two hundred of them is asking for nothing.
	 * They still have to be deletable, so `exists()` knows them and the purge order carries them.
	 *
	 * @since 1.3.0
	 *
	 * @return array<int, string>
	 */
	public static function internal(): array {
		return array(
			self::MEDIA,
		);
	}

	/**
	 * Whether a string is a known resource name.
	 *
	 * Internal resources count. The only caller is the purge, which has to be able to name
	 * everything it might have to remove — including rows no generator ever produced directly.
	 *
	 * @since 1.1.0
	 *
	 * @param string $resource_type Candidate resource name.
	 *
	 * @return bool
	 */
	public static function exists( string $resource_type ): bool {
		return in_array( $resource_type, self::all(), true )
			|| in_array( $resource_type, self::internal(), true );
	}
}
