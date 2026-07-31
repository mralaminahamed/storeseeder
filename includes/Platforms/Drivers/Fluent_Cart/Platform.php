<?php
/**
 * Fluent Cart platform driver
 *
 * @since   1.1.0
 * @package StoreSeeder\Platforms\Drivers\Fluent_Cart
 */

namespace StoreSeeder\Platforms\Drivers\Fluent_Cart;

use StoreSeeder\Platforms\Capability;
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
	 * Fluent Cart Pro's basename, for the same check.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	const PRO_BASENAME = 'fluent-cart-pro/fluent-cart-pro.php';

	/**
	 * Pro's WordPress.org-style slug, as reported to the admin.
	 *
	 * Not a directory listing: it is what the UI names when it has to say "this needs
	 * Fluent Cart Pro".
	 *
	 * @since 1.1.0
	 * @var string
	 */
	const PRO_SLUG = 'fluent-cart-pro';

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
	 * Whether Fluent Cart Pro is present.
	 *
	 * @since 1.1.0
	 *
	 * @return bool
	 */
	public function is_pro_active(): bool {
		return defined( 'FLUENTCART_PRO_PLUGIN_VERSION' )
			|| $this->is_plugin_active( self::PRO_BASENAME );
	}

	/**
	 * Pro's version, or null when it is not installed.
	 *
	 * @since 1.1.0
	 *
	 * @return string|null
	 */
	public function pro_version(): ?string {
		return defined( 'FLUENTCART_PRO_PLUGIN_VERSION' )
			? (string) FLUENTCART_PRO_PLUGIN_VERSION
			: null;
	}

	/**
	 * What this site has installed alongside Fluent Cart.
	 *
	 * @since 1.1.0
	 *
	 * @return array<int, array{slug: string, label: string, active: bool, version: string|null}>
	 */
	public function extensions(): array {
		return array(
			array(
				'slug'    => self::PRO_SLUG,
				'label'   => __( 'Fluent Cart Pro', 'storeseeder' ),
				'active'  => $this->is_pro_active(),
				'version' => $this->pro_version(),
			),
		);
	}

	/**
	 * Capability matrix.
	 *
	 * Every core resource is supported. Subscriptions are worth a note: the table ships
	 * in Fluent Cart core, so records generate without Pro — it is *billing* them that
	 * needs Pro, and billing is not something a seeder does. So that one is a genuine
	 * yes, not a conditional one.
	 *
	 * Licences are the conditional case. The `fct_licenses` table is created by Pro's own
	 * migrator, so without Pro there is nowhere to write them — and reporting that as a
	 * flat "unsupported" would leave the user guessing. Naming the plugin lets the admin
	 * say "install Fluent Cart Pro" and the REST API answer with the same reason.
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

		if ( ! $this->is_pro_active() ) {
			$matrix[ Resource::LICENSE ] = Capability::missing_extension(
				self::PRO_SLUG,
				__( 'Fluent Cart Pro', 'storeseeder' )
			);
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
			Resource::BRAND             => Writers\Brand::class,
			Resource::CART_SESSION      => Writers\Cart_Session::class,
			Resource::COUPON            => Writers\Coupon::class,
			Resource::CUSTOMER          => Writers\Customer::class,
			Resource::LABEL             => Writers\Label::class,
			Resource::LICENSE           => Writers\License::class,
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
