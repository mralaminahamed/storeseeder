<?php
/**
 * Tests for the capability matrix.
 *
 * @package StoreSeeder\Tests
 */

namespace StoreSeeder\Tests\Platform;

use StoreSeeder\Platforms\Capability;
use StoreSeeder\Platforms\Registry;
use StoreSeeder\Platforms\Resource;
use StoreSeeder\Tests\StoreSeederUnitTestCase;

/**
 * @covers \StoreSeeder\Platforms\Capability
 * @covers \StoreSeeder\Platforms\Platform_Driver
 */
class CapabilityTest extends StoreSeederUnitTestCase {

	public function tearDown(): void {
		remove_all_filters( 'storeseeder_platforms' );
		remove_all_filters( 'storeseeder_platform_supports_stub-cart' );
		Registry::reset();
		parent::tearDown();
	}

	public function test_supported_carries_no_reason(): void {
		$capability = Capability::supported();

		$this->assertTrue( $capability->is_supported() );
		$this->assertSame( '', $capability->get_reason() );
		$this->assertSame( '', $capability->get_extension() );
	}

	/**
	 * "Cannot" and "install this and it can" are different messages, and only the
	 * second is actionable — so the extension slug has to survive to the client.
	 */
	public function test_missing_extension_names_the_plugin(): void {
		$capability = Capability::missing_extension( 'woocommerce-subscriptions', 'WooCommerce Subscriptions' );

		$this->assertFalse( $capability->is_supported() );
		$this->assertSame( 'woocommerce-subscriptions', $capability->get_extension() );
		$this->assertStringContainsString( 'WooCommerce Subscriptions', $capability->get_reason() );
	}

	public function test_unsupported_explains_itself(): void {
		$capability = Capability::unsupported( 'No such concept here.' );

		$this->assertFalse( $capability->is_supported() );
		$this->assertSame( 'No such concept here.', $capability->get_reason() );
		$this->assertSame( '', $capability->get_extension() );
	}

	public function test_bare_booleans_are_normalised(): void {
		$this->assertTrue( Capability::from( true )->is_supported() );
		$this->assertFalse( Capability::from( false )->is_supported() );
		$this->assertNotSame( '', Capability::from( false )->get_reason() );
	}

	public function test_from_passes_a_capability_through(): void {
		$capability = Capability::unsupported( 'nope' );

		$this->assertSame( $capability, Capability::from( $capability ) );
	}

	public function test_fluent_cart_supports_every_core_resource(): void {
		$platform = Registry::instance()->get( 'fluent-cart' );
		$supports = $platform->supports();

		foreach ( Resource::all() as $resource_type ) {
			$this->assertArrayHasKey( $resource_type, $supports );

			// Licences are the one conditional resource: Pro owns their tables.
			if ( Resource::LICENSE === $resource_type ) {
				continue;
			}

			$this->assertTrue( $supports[ $resource_type ]->is_supported(), $resource_type );
		}
	}

	/**
	 * Licences depend on Fluent Cart Pro, and the answer has to name it.
	 *
	 * A bare "unsupported" would leave the user guessing at what to install, which is the
	 * whole reason a capability carries a reason and an extension slug rather than a bool.
	 * Which branch runs depends on whether Pro is present in the test environment, so both
	 * are asserted rather than assuming one.
	 */
	public function test_licences_depend_on_fluent_cart_pro(): void {
		$platform = Registry::instance()->get( 'fluent-cart' );
		$this->assertNotNull( $platform );

		$licence = $platform->supports()[ Resource::LICENSE ];

		if ( $platform->is_pro_active() ) {
			$this->assertTrue( $licence->is_supported() );

			return;
		}

		$this->assertFalse( $licence->is_supported() );
		$this->assertSame( 'fluent-cart-pro', $licence->get_extension() );
		$this->assertStringContainsString( 'Fluent Cart Pro', $licence->get_reason() );
	}

	/**
	 * The driver reports what is installed beside it, which is a different question from
	 * what it can generate — "Pro 1.5.3" is a fact, "licences unavailable" is a consequence.
	 */
	public function test_fluent_cart_reports_pro_as_an_extension(): void {
		$platform   = Registry::instance()->get( 'fluent-cart' );
		$extensions = $platform->extensions();

		$this->assertCount( 1, $extensions );
		$this->assertSame( 'fluent-cart-pro', $extensions[0]['slug'] );
		$this->assertSame( 'Fluent Cart Pro', $extensions[0]['label'] );
		$this->assertSame( $platform->is_pro_active(), $extensions[0]['active'] );
	}

	/**
	 * The filter is how a third-party extension announces that it satisfies a
	 * requirement the driver reported as missing, so it must run last.
	 */
	public function test_filter_can_override_a_capability(): void {
		add_filter(
			'storeseeder_platforms',
			static function ( array $platforms ): array {
				$platforms[] = new StubPlatform(
					'stub-cart',
					true,
					array(
						Resource::PRODUCT      => true,
						Resource::SUBSCRIPTION => Capability::missing_extension( 'stub-subs', 'Stub Subscriptions' ),
					)
				);
				return $platforms;
			}
		);
		Registry::reset();

		$platform = Registry::instance()->get( 'stub-cart' );
		$this->assertFalse( $platform->supports()[ Resource::SUBSCRIPTION ]->is_supported() );

		add_filter(
			'storeseeder_platform_supports_stub-cart',
			static function ( array $matrix ): array {
				$matrix[ Resource::SUBSCRIPTION ] = Capability::supported();
				return $matrix;
			}
		);

		$this->assertTrue( $platform->supports()[ Resource::SUBSCRIPTION ]->is_supported() );
	}

	/**
	 * A driver that claims a resource but ships no writer for it is a driver bug.
	 * Saying so plainly beats letting the run fail once per requested item and
	 * report a generic "generation failed" that names neither half.
	 */
	public function test_declared_support_without_a_writer_is_reported(): void {
		add_filter(
			'storeseeder_platforms',
			static function ( array $platforms ): array {
				// Claims every resource, provides no writers at all.
				$platforms[] = new StubPlatform( 'stub-cart', true );
				return $platforms;
			}
		);
		Registry::reset();

		$platform = Registry::instance()->get( 'stub-cart' );

		$this->assertTrue( $platform->supports()[ Resource::PRODUCT ]->is_supported() );
		$this->assertNull( $platform->writer( Resource::PRODUCT ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		do_action( 'rest_api_init' );

		$request = new \WP_REST_Request( 'POST', '/storeseeder/v1/products/generate' );
		$request->set_param( 'count', 1 );
		$request->set_param( 'platform', 'stub-cart' );

		$response = rest_do_request( $request );
		$data     = $response->get_data();

		$this->assertSame( 500, $response->get_status() );
		$this->assertSame( 'storeseeder_missing_writer', $data['code'] );
		$this->assertStringContainsString( 'no writer', $data['message'] );
	}

	public function test_resource_names_are_stable(): void {
		$this->assertCount( 20, Resource::all() );
		$this->assertTrue( Resource::exists( 'license' ) );
		$this->assertTrue( Resource::exists( 'product' ) );
		$this->assertFalse( Resource::exists( 'products' ) );
	}
}
