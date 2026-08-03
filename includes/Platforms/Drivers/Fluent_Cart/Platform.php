<?php
/**
 * Fluent Cart platform driver
 *
 * @since   1.1.0
 * @package StoreSeeder\Platforms\Drivers\Fluent_Cart
 */

namespace StoreSeeder\Platforms\Drivers\Fluent_Cart;

use FluentCart\App\Models\Customer as CustomerModel;
use FluentCart\App\Models\ProductDetail as ProductDetailModel;
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

		// Fluent Cart registers `product-categories` and `product-brands` and no tag taxonomy at
		// all. Its own Product model reads `product-tags` in three places, which resolves to
		// nothing — so tags are not merely unimplemented here, they have nowhere to live.
		// StoreSeeder could register the taxonomy itself and refuses to: the terms would be
		// real, and unreachable from any Fluent Cart screen, which is worse than absent.
		$matrix[ Resource::PRODUCT_TAG ] = Capability::unsupported(
			__( 'Fluent Cart has product categories and brands but no product tags — there is no tag taxonomy to write to.', 'storeseeder' )
		);

		// Supported, minus one field. Fluent Cart's `backorders` is a boolean where the canonical
		// vocabulary has three values, so "allow but notify the customer" cannot be stored — it
		// becomes a plain yes. Reported rather than silently flattened.
		//
		// `gallery_count` joins it for a different reason. A Fluent Cart product is a post, so it
		// takes a featured image the ordinary WordPress way, but there is no second image field
		// to address without inventing one — and a gallery written where Fluent Cart never looks
		// would present as a working feature and render nothing.
		$matrix[ Resource::PRODUCT ] = Capability::supported_except( array( 'backorders', 'gallery_count' ) );

		// `fct_order_addresses` has no company column — name, two street lines, city, state,
		// postcode, country and a meta blob. A company name could be buried in the meta, but
		// nothing in Fluent Cart reads it there, so it would be stored and invisible.
		//
		// Not listed, deliberately: `shipping_total` is NOT NULL here, so an order that was never
		// shipped and one shipped for free are the same row. The *request* is still honoured —
		// switching shipping off charges nothing — and reporting a field as ignored when the
		// caller got what they asked for is a false alarm, which is worse than the lost nuance.
		$matrix[ Resource::ORDER ] = Capability::supported_except( array( 'company' ) );

		// Two coupon restrictions with nowhere to go. Fluent Cart's conditions have
		// `min_purchase_amount` and no maximum — `max_discount_amount` caps the discount, not the
		// cart, so writing one there would enforce something different from what was asked. And
		// nothing in its coupon validation knows about sale prices.
		$matrix[ Resource::COUPON ] = Capability::supported_except( array( 'maximum_amount', 'exclude_sale_items' ) );

		// `fct_order_transactions.order_id` is a NOT NULL foreign key into fct_orders, so a
		// transaction that belongs to no order cannot exist here — which is what asking for
		// `associate_with_orders` off would mean.
		$matrix[ Resource::TRANSACTION ] = Capability::supported_except( array( 'associate_with_orders' ) );

		if ( ! $this->is_pro_active() ) {
			$matrix[ Resource::LICENSE ] = Capability::missing_extension(
				self::PRO_SLUG,
				__( 'Fluent Cart Pro', 'storeseeder' )
			);
		}

		return $matrix;
	}

	/**
	 * Fluent Cart's own admin screens.
	 *
	 * One page and a hash router rather than WordPress list tables, so every path shares a prefix
	 * and differs only after the `#`. Taken from the plugin's own submenu registration rather than
	 * guessed, since a route it does not know simply renders its dashboard — a link that appears to
	 * work while going nowhere in particular.
	 *
	 * @since 1.2.0
	 *
	 * @param string $resource_type Canonical resource name.
	 *
	 * @return string|null
	 */
	protected function admin_path( string $resource_type ): ?string {
		$routes = array(
			Resource::PRODUCT      => 'products',
			Resource::ORDER        => 'orders',
			Resource::CUSTOMER     => 'customers',
			Resource::SUBSCRIPTION => 'subscriptions',
		);

		if ( ! isset( $routes[ $resource_type ] ) ) {
			return null;
		}

		return 'admin.php?page=fluent-cart#/' . $routes[ $resource_type ];
	}

	/**
	 * Products and customers a Fluent Cart store already has.
	 *
	 * Through its Eloquent models, which is where the data lives — a product is a `wp_posts` row
	 * paired with a detail row, and a customer is its own table rather than a WordPress user.
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
	 * Published products, newest matching first.
	 *
	 * @since 1.1.0
	 *
	 * @param string $query The search term.
	 * @param int    $limit Maximum results.
	 *
	 * @return array<int, array{id: int|string, label: string}>
	 */
	private function search_products( string $query, int $limit ): array {
		if ( ! class_exists( ProductDetailModel::class ) ) {
			return array();
		}

		$builder = ProductDetailModel::query()->with( 'product' )->limit( $limit );

		// A term that is only digits is an id as often as it is part of a name — and it is the only
		// way a picker restoring a saved value can turn that value back into a name to show.
		if ( '' !== $query && ctype_digit( $query ) ) {
			$builder->where( 'post_id', (int) $query );
		} elseif ( '' !== $query ) {
			// Through the relation, because the title lives on the post rather than the detail row.
			$builder->whereHas(
				'product',
				static function ( $posts ) use ( $query ) {
					$posts->where( 'post_title', 'LIKE', '%' . $query . '%' );
				}
			);
		}

		$results = array();

		foreach ( $builder->get() as $detail ) {
			$post = $detail->product;

			if ( ! $post || 'publish' !== $post->post_status ) {
				continue;
			}

			$results[] = array(
				// The post id: it is what `product_id` means to every writer here, and what the
				// variation writer looks a parent up by.
				'id'    => (int) $detail->post_id,
				'label' => $this->product_label( (string) $post->post_title, '', (int) $detail->post_id ),
			);
		}

		return $results;
	}

	/**
	 * Customers, matched on either name or email.
	 *
	 * @since 1.1.0
	 *
	 * @param string $query The search term.
	 * @param int    $limit Maximum results.
	 *
	 * @return array<int, array{id: int|string, label: string}>
	 */
	private function search_customers( string $query, int $limit ): array {
		if ( ! class_exists( CustomerModel::class ) ) {
			return array();
		}

		$builder = CustomerModel::query()->limit( $limit );

		if ( '' !== $query && ctype_digit( $query ) ) {
			$builder->where( 'id', (int) $query );
		} elseif ( '' !== $query ) {
			$builder->where(
				static function ( $where ) use ( $query ) {
					$like = '%' . $query . '%';

					$where->where( 'first_name', 'LIKE', $like )
						->orWhere( 'last_name', 'LIKE', $like )
						->orWhere( 'email', 'LIKE', $like );
				}
			);
		}

		$results = array();

		foreach ( $builder->get() as $customer ) {
			$name = trim( (string) $customer->first_name . ' ' . (string) $customer->last_name );

			$results[] = array(
				'id'    => (int) $customer->id,
				'label' => $this->customer_label( $name, (string) $customer->email, (int) $customer->id ),
			);
		}

		return $results;
	}

	/**
	 * Fluent Cart-only generation parameters.
	 *
	 * `payment_type` is a Fluent Cart variation column with no equivalent elsewhere: a variation
	 * is bought once or on a recurring basis. The writer hardcoded `onetime`, which made
	 * subscription products impossible to seed; this exposes the choice without putting a Fluent
	 * Cart column into a canonical entity.
	 *
	 * @since 1.1.0
	 *
	 * @param string $resource_type Canonical resource name.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	protected function platform_fields( string $resource_type ): array {
		if ( ! in_array( $resource_type, array( Resource::PRODUCT, Resource::PRODUCT_VARIATION ), true ) ) {
			return array();
		}

		return array(
			'payment_type' => array(
				'description'       => __( 'Fluent Cart only. Whether generated variations are bought once or on a subscription.', 'storeseeder' ),
				'type'              => 'string',
				'enum'              => array( 'onetime', 'subscription' ),
				'default'           => 'onetime',
				'sanitize_callback' => 'sanitize_key',
			),
		);
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
			// Not generated: created alongside products, registered so the purge can remove it.
			Resource::MEDIA             => Writers\Media::class,
			Resource::ORDER             => Writers\Order::class,
			Resource::ORDER_TAX_RATE    => Writers\Order_Tax_Rate::class,
			Resource::PRODUCT           => Writers\Product::class,
			Resource::PRODUCT_CATEGORY  => Writers\Product_Category::class,
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
