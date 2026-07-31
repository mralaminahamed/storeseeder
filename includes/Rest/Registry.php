<?php
/**
 * REST controller registry
 *
 * Holds the controllers that expose generators over REST. The shipped ones are listed
 * here; the `storeseeder_rest_controllers` filter is the supported way for anything
 * else to join, which is what lets a third-party platform ship a resource of its own
 * without patching this plugin.
 *
 * This mirrors StoreSeeder\Platforms\Registry deliberately. Both answer the same kind
 * of question — "what is available?" — and there is no reason for the two to be
 * discovered differently.
 *
 * @since   1.1.0
 * @package StoreSeeder\Rest
 */

namespace StoreSeeder\Rest;

defined( 'ABSPATH' ) || exit;

/**
 * Registry of REST controllers.
 *
 * @since 1.1.0
 */
final class Registry {
	/**
	 * Shared instance.
	 *
	 * @since 1.1.0
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Instantiated controllers keyed by REST base, or null before the filter has run.
	 *
	 * Keyed by base rather than held as a list so a filter can replace one controller
	 * without having to find and remove the original.
	 *
	 * @since 1.1.0
	 * @var array<string, Controller>|null
	 */
	private $controllers = null;

	/**
	 * Shared instance.
	 *
	 * @since 1.1.0
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Discard the shared instance and its memoised controllers.
	 *
	 * For tests, which add controllers through the filter between cases.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$instance = null;
	}

	/**
	 * Controller classes shipped with the plugin, in registration order.
	 *
	 * Class names rather than instances: a controller's constructor adds a
	 * `rest_api_init` listener, so building all seventeen before the filter has had a
	 * chance to remove any would register routes this registry then claimed to have
	 * dropped.
	 *
	 * @since 1.1.0
	 *
	 * @return array<int, class-string<Controller>>
	 */
	private function default_classes(): array {
		return array(
			// Core.
			Controllers\Product::class,
			Controllers\Customer::class,
			Controllers\Order::class,
			Controllers\Coupon::class,

			// Advanced.
			Controllers\Product_Variation::class,
			Controllers\Shipping_Plan::class,
			Controllers\Tax_Class::class,
			Controllers\Transaction::class,
			Controllers\Cart_Session::class,
			Controllers\Attribute::class,
			Controllers\Refund::class,
			Controllers\Log::class,
			Controllers\Shipping_Class::class,
			Controllers\Label::class,
			Controllers\Order_Tax_Rate::class,
			Controllers\Product_Download::class,
			Controllers\Subscription::class,
		);
	}

	/**
	 * Every registered controller, keyed by REST base.
	 *
	 * Memoised for the request: the set cannot change between the filter running and
	 * routes being registered.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, Controller>
	 */
	public function all(): array {
		if ( null !== $this->controllers ) {
			return $this->controllers;
		}

		/**
		 * Filters the REST controllers StoreSeeder registers.
		 *
		 * Append a class name extending StoreSeeder\Rest\Controller to expose a new
		 * resource, or remove one to withdraw its routes. Instances are accepted too,
		 * for a controller that needs constructor arguments.
		 *
		 * @since 1.1.0
		 *
		 * @param array<int, mixed> $controllers Controllers, in registration order. Each
		 *                                       entry is expected to be a class name
		 *                                       extending Controller, or an instance of
		 *                                       one; anything else is discarded rather
		 *                                       than trusted, since a filter can return
		 *                                       whatever it likes.
		 */
		$controllers = apply_filters( 'storeseeder_rest_controllers', $this->default_classes() );

		$this->controllers = array();

		foreach ( $controllers as $controller ) {
			if ( is_string( $controller ) ) {
				if ( ! class_exists( $controller ) ) {
					continue;
				}

				$controller = new $controller();
			}

			// A malformed entry from a third-party filter must not take down the REST
			// API, which is registered from this same call path.
			if ( ! $controller instanceof Controller ) {
				continue;
			}

			$this->controllers[ $controller->rest_base() ] = $controller;
		}

		return $this->controllers;
	}

	/**
	 * One controller by its REST base.
	 *
	 * @since 1.1.0
	 *
	 * @param string $rest_base REST base, e.g. 'cart-sessions'.
	 *
	 * @return Controller|null
	 */
	public function get( string $rest_base ): ?Controller {
		$controllers = $this->all();

		return $controllers[ $rest_base ] ?? null;
	}

	/**
	 * Register every controller's routes.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function register_routes(): void {
		foreach ( $this->all() as $controller ) {
			$controller->register_routes();
		}
	}
}
