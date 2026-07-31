<?php
/**
 * Tests for platform-specific generation fields, and for reporting what a platform ignores.
 *
 * Two halves of one promise. A driver can declare parameters only it understands, and a driver can
 * admit that it stores a resource *without* some of the canonical fields — and in both cases the
 * caller is told. The failure this replaces is the `include_images` bug: a control the admin
 * offered that no writer read, discoverable only by generating data and noticing nothing changed.
 *
 * @package StoreSeeder\Tests
 */

namespace StoreSeeder\Tests\Platform;

use StoreSeeder\Platforms\Capability;
use StoreSeeder\Platforms\Registry;
use StoreSeeder\Platforms\Resource;
use StoreSeeder\Tests\StoreSeederUnitTestCase;
use WP_REST_Request;

/**
 * @covers \StoreSeeder\Platforms\Platform_Driver::fields
 * @covers \StoreSeeder\Platforms\Capability::supported_except
 * @covers \StoreSeeder\Rest\Controller::get_platform_params
 * @covers \StoreSeeder\Rest\Controller::ignored_params
 */
class PlatformFieldsTest extends StoreSeederUnitTestCase {

	public function tearDown(): void {
		remove_all_filters( 'storeseeder_platform_fields_fluent-cart' );
		remove_all_filters( 'storeseeder_platform_fields_woocommerce' );
		Registry::reset();
		parent::tearDown();
	}

	// -----------------------------------------------------------------------------------
	// Declaring fields.
	// -----------------------------------------------------------------------------------

	public function test_a_driver_declares_nothing_by_default(): void {
		$platform = new StubPlatform( 'stub-cart' );

		$this->assertSame( array(), $platform->fields( Resource::PRODUCT ) );
		$this->assertSame( array(), $platform->all_fields() );
	}

	public function test_fluent_cart_declares_payment_type_for_products(): void {
		$this->require_platform( 'fluent-cart' );

		$fields = Registry::instance()->get( 'fluent-cart' )->fields( Resource::PRODUCT );

		$this->assertArrayHasKey( 'payment_type', $fields );
		$this->assertSame( array( 'onetime', 'subscription' ), $fields['payment_type']['enum'] );
	}

	public function test_woocommerce_declares_its_own_product_fields(): void {
		$this->require_platform( 'woocommerce' );

		$fields = Registry::instance()->get( 'woocommerce' )->fields( Resource::PRODUCT );

		foreach ( array( 'featured_ratio', 'catalog_visibility', 'tax_status' ) as $name ) {
			$this->assertArrayHasKey( $name, $fields, $name );
			$this->assertArrayHasKey( 'description', $fields[ $name ] );
		}

		// Named in the description, because the endpoint accepts every driver's fields and the
		// reader needs to know whose is whose.
		$this->assertStringContainsString( 'WooCommerce', $fields['tax_status']['description'] );
	}

	/**
	 * A field declared for one resource must not leak onto another — an order has no catalogue
	 * visibility, and offering one would be a control that does nothing.
	 */
	public function test_fields_are_scoped_to_their_resource(): void {
		$this->require_platform( 'woocommerce' );

		$this->assertSame( array(), Registry::instance()->get( 'woocommerce' )->fields( Resource::ORDER ) );
	}

	public function test_the_filter_can_add_a_field(): void {
		$this->require_platform( 'fluent-cart' );

		add_filter(
			'storeseeder_platform_fields_fluent-cart',
			static function ( array $fields, string $resource_type ): array {
				if ( Resource::ORDER === $resource_type ) {
					$fields['tax_behavior'] = array( 'type' => 'string' );
				}

				return $fields;
			},
			10,
			2
		);

		$fields = Registry::instance()->get( 'fluent-cart' )->fields( Resource::ORDER );

		$this->assertArrayHasKey( 'tax_behavior', $fields );
	}

	/**
	 * A filter returns whatever it likes, and anything malformed would reach `register_rest_route`
	 * as an argument definition and fail the whole endpoint rather than just itself.
	 */
	public function test_malformed_filter_entries_are_discarded(): void {
		$this->require_platform( 'fluent-cart' );

		add_filter(
			'storeseeder_platform_fields_fluent-cart',
			static function ( array $fields ): array {
				$fields['fine']   = array( 'type' => 'string' );
				$fields['broken'] = 'not a schema';
				$fields[]         = array( 'type' => 'string' );

				return $fields;
			}
		);

		$fields = Registry::instance()->get( 'fluent-cart' )->fields( Resource::PRODUCT );

		$this->assertArrayHasKey( 'fine', $fields );
		$this->assertArrayNotHasKey( 'broken', $fields );
		$this->assertSame( array(), array_filter( array_keys( $fields ), 'is_int' ) );
	}

	// -----------------------------------------------------------------------------------
	// The REST schema.
	// -----------------------------------------------------------------------------------

	/**
	 * Routes register once, before any platform is resolved, so the endpoint has to accept the
	 * union. Discovery then lists every driver's field with the platform named in it.
	 */
	public function test_the_endpoint_accepts_every_drivers_fields(): void {
		$routes = rest_get_server()->get_routes( 'storeseeder/v1' );

		$this->assertArrayHasKey( '/storeseeder/v1/products/generate', $routes );

		$args = $routes['/storeseeder/v1/products/generate'][0]['args'];

		if ( null !== Registry::instance()->get( 'woocommerce' ) ) {
			$this->assertArrayHasKey( 'tax_status', $args );
		}

		if ( null !== Registry::instance()->get( 'fluent-cart' ) ) {
			$this->assertArrayHasKey( 'payment_type', $args );
		}
	}

	/**
	 * A driver must not be able to redefine a parameter every platform agrees on: that is what the
	 * canonical entity is for, and letting one win would break the same-seed guarantee elsewhere.
	 */
	public function test_a_platform_field_cannot_override_a_canonical_one(): void {
		$this->require_platform( 'fluent-cart' );

		add_filter(
			'storeseeder_platform_fields_fluent-cart',
			static function ( array $fields ): array {
				$fields['count'] = array(
					'type'    => 'string',
					'default' => 'hijacked',
				);

				return $fields;
			}
		);

		// Asked of the controller rather than of a re-registered route. Firing `rest_api_init`
		// again re-initialises every plugin's REST layer, WooCommerce's included, and left the
		// coupon writer hanging in a later test — a cost with no benefit, since the merge order
		// this asserts lives in get_generation_params().
		$params = ( new \StoreSeeder\Rest\Controllers\Product() )->get_generation_params();

		$this->assertSame( 'integer', $params['count']['type'] );
		$this->assertTrue( $params['count']['required'] );
	}

	// -----------------------------------------------------------------------------------
	// Reporting what was ignored.
	// -----------------------------------------------------------------------------------

	public function test_capability_carries_the_fields_a_platform_cannot_store(): void {
		$capability = Capability::supported_except( array( 'with_account', 'with_account' ) );

		$this->assertTrue( $capability->is_supported() );
		// Deduplicated, so a driver listing a field twice does not report it twice.
		$this->assertSame( array( 'with_account' ), $capability->get_ignored_fields() );
		$this->assertTrue( $capability->ignores( 'with_account' ) );
		$this->assertFalse( $capability->ignores( 'email' ) );
		$this->assertArrayHasKey( 'ignored_fields', $capability->to_array() );
	}

	public function test_a_plain_capability_ignores_nothing(): void {
		$this->assertSame( array(), Capability::supported()->get_ignored_fields() );
		$this->assertSame( array(), Capability::unsupported( 'no' )->get_ignored_fields() );
	}

	/**
	 * The two real cases this shipped with, both already documented in the writers themselves: a
	 * WooCommerce customer *is* a WordPress user, and a shipping class's cost lives on the
	 * shipping method.
	 */
	public function test_woocommerce_admits_the_fields_it_drops(): void {
		$this->require_platform( 'woocommerce' );

		$supports = Registry::instance()->get( 'woocommerce' )->supports();

		$this->assertTrue( $supports[ Resource::CUSTOMER ]->is_supported() );
		$this->assertContains( 'with_account', $supports[ Resource::CUSTOMER ]->get_ignored_fields() );

		$this->assertTrue( $supports[ Resource::SHIPPING_CLASS ]->is_supported() );
		$this->assertContains( 'cost', $supports[ Resource::SHIPPING_CLASS ]->get_ignored_fields() );
	}

	/**
	 * End to end: a run that sends another platform's field gets told it was not used. Silence
	 * here is the whole bug this mechanism replaces.
	 */
	public function test_a_run_reports_a_field_the_target_does_not_have(): void {
		$this->require_platform( 'woocommerce' );
		$this->require_platform( 'fluent-cart' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$request = new WP_REST_Request( 'POST', '/storeseeder/v1/products/generate' );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body(
			(string) wp_json_encode(
				array(
					'count'    => 1,
					'platform' => 'fluent-cart',
					// WooCommerce's, not Fluent Cart's.
					'tax_status' => 'none',
				)
			)
		);

		$data = rest_get_server()->dispatch( $request )->get_data();

		$this->assertArrayHasKey( 'ignored', $data );
		$this->assertContains( 'tax_status', $data['ignored'] );
	}

	/**
	 * A run that sends only what the target understands reports no *foreign* field.
	 *
	 * Products on Fluent Cart do report one thing — `backorders`, which its boolean column cannot
	 * express in three values — so the assertion is about what is absent rather than about the key
	 * being missing. That distinction is the point: the report names what this platform cannot do,
	 * not what the caller did wrong.
	 */
	public function test_a_clean_run_reports_no_foreign_field(): void {
		$this->require_platform( 'fluent-cart' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$request = new WP_REST_Request( 'POST', '/storeseeder/v1/products/generate' );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body(
			(string) wp_json_encode(
				array(
					'count'        => 1,
					'platform'     => 'fluent-cart',
					'payment_type' => 'subscription',
				)
			)
		);

		$data = rest_get_server()->dispatch( $request )->get_data();

		$this->assertNotContains( 'tax_status', (array) ( $data['ignored'] ?? array() ) );
		$this->assertNotContains( 'catalog_visibility', (array) ( $data['ignored'] ?? array() ) );
	}

	/**
	 * And a resource whose target stores every canonical field says nothing at all, so the response
	 * shape is unchanged for every existing caller.
	 */
	public function test_a_resource_with_nothing_ignored_reports_nothing(): void {
		$this->require_platform( 'fluent-cart' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$request = new WP_REST_Request( 'POST', '/storeseeder/v1/coupons/generate' );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body(
			(string) wp_json_encode(
				array(
					'count'    => 1,
					'platform' => 'fluent-cart',
				)
			)
		);

		$data = rest_get_server()->dispatch( $request )->get_data();

		$this->assertArrayNotHasKey( 'ignored', $data );
	}
}
