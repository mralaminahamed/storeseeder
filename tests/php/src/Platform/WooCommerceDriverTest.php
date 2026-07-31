<?php
/**
 * Tests for the WooCommerce driver.
 *
 * The capability matrix is the interesting part. Three resources WooCommerce genuinely cannot
 * represent are reported unsupported *with a reason*, and one is conditional on a plugin — get
 * that wrong in either direction and the admin either offers a generator that cannot work or
 * hides one that can.
 *
 * @package StoreSeeder\Tests
 */

namespace StoreSeeder\Tests\Platform;

use StoreSeeder\Platforms\Drivers\Woo_Commerce\Platform;
use StoreSeeder\Platforms\Registry;
use StoreSeeder\Platforms\Resource;
use StoreSeeder\Platforms\Writer;
use StoreSeeder\Tests\StoreSeederUnitTestCase;

/**
 * @covers \StoreSeeder\Platforms\Drivers\Woo_Commerce\Platform
 */
class WooCommerceDriverTest extends StoreSeederUnitTestCase {

	/**
	 * Resources WooCommerce has no equivalent for, whatever is installed.
	 *
	 * @var string[]
	 */
	private const UNSUPPORTED = array(
		Resource::TRANSACTION,
		Resource::LABEL,
		Resource::LICENSE,
	);

	public function setUp(): void {
		parent::setUp();
		$this->require_platform( 'woocommerce' );

		// WooCommerce's tables are created on demand rather than for the whole suite: doing it
		// in the bootstrap made every unrelated test slower and changed the environment enough
		// to break three that have nothing to do with WooCommerce.
		if ( ! storeseeder_install_woocommerce_tables() ) {
			$this->markTestSkipped( 'WooCommerce tables could not be created.' );
		}
	}

	public function tearDown(): void {
		remove_all_filters( 'storeseeder_platform_supports_woocommerce' );
		Registry::reset();
		parent::tearDown();
	}

	public function test_it_identifies_itself(): void {
		$platform = new Platform();

		$this->assertSame( 'woocommerce', $platform->id() );
		$this->assertSame( 'WooCommerce', $platform->label() );
	}

	public function test_it_reports_active_with_woocommerce_loaded(): void {
		$platform = new Platform();

		$this->assertTrue( $platform->is_active() );
		$this->assertSame( WC_VERSION, $platform->version() );
	}

	public function test_it_is_registered_alongside_fluent_cart(): void {
		$all = Registry::instance()->all();

		$this->assertArrayHasKey( 'woocommerce', $all );
		$this->assertInstanceOf( Platform::class, $all['woocommerce'] );
	}

	/**
	 * The matrix covers every canonical resource. A resource missing from it reads as
	 * unsupported for no stated reason, which is the outcome the Capability class exists to
	 * prevent.
	 */
	public function test_the_matrix_answers_for_every_resource(): void {
		$matrix = ( new Platform() )->supports();

		$this->assertCount( count( Resource::all() ), $matrix );

		foreach ( Resource::all() as $resource_type ) {
			$this->assertArrayHasKey( $resource_type, $matrix, $resource_type );
		}
	}

	public function test_the_core_resources_are_supported(): void {
		$matrix = ( new Platform() )->supports();

		foreach ( array( Resource::PRODUCT, Resource::CUSTOMER, Resource::ORDER, Resource::COUPON ) as $resource_type ) {
			$this->assertTrue( $matrix[ $resource_type ]->is_supported(), $resource_type );
		}
	}

	/**
	 * "Unsupported" on its own leaves the user wondering whether a plugin would fix it. Each
	 * of these three says what WooCommerce does instead, and names no plugin, because none
	 * exists that would change the answer.
	 */
	public function test_what_woocommerce_cannot_represent_is_refused_with_a_reason(): void {
		$matrix = ( new Platform() )->supports();

		foreach ( self::UNSUPPORTED as $resource_type ) {
			$capability = $matrix[ $resource_type ];

			$this->assertFalse( $capability->is_supported(), $resource_type );
			$this->assertNotSame( '', $capability->get_reason(), $resource_type );
			$this->assertSame( '', $capability->get_extension(), $resource_type );
		}
	}

	public function test_transactions_explain_that_payment_lives_on_the_order(): void {
		$capability = ( new Platform() )->supports()[ Resource::TRANSACTION ];

		$this->assertStringContainsString( 'order', $capability->get_reason() );
	}

	/**
	 * Subscriptions is the conditional case, and the distinction matters: a missing extension
	 * is actionable, a missing concept is not.
	 */
	public function test_subscriptions_depend_on_the_subscriptions_plugin(): void {
		$platform   = new Platform();
		$capability = $platform->supports()[ Resource::SUBSCRIPTION ];

		if ( $platform->is_subscriptions_active() ) {
			$this->assertTrue( $capability->is_supported() );

			return;
		}

		$this->assertFalse( $capability->is_supported() );
		$this->assertSame( Platform::SUBSCRIPTIONS_SLUG, $capability->get_extension() );
	}

	public function test_extensions_report_subscriptions_by_name(): void {
		$platform   = new Platform();
		$extensions = $platform->extensions();

		$this->assertCount( 1, $extensions );
		$this->assertSame( Platform::SUBSCRIPTIONS_SLUG, $extensions[0]['slug'] );
		$this->assertSame( $platform->is_subscriptions_active(), $extensions[0]['active'] );
	}

	/**
	 * A driver that claims a resource and ships no writer for it is reported as
	 * `storeseeder_missing_writer` — once per run rather than per item, but still a promise
	 * broken at generate time rather than at page load. The matrix and the writer map have to
	 * agree.
	 */
	public function test_every_supported_resource_has_a_writer(): void {
		$platform = new Platform();

		foreach ( $platform->supports() as $resource_type => $capability ) {
			$writer = $platform->writer( $resource_type );

			if ( $capability->is_supported() ) {
				$this->assertInstanceOf( Writer::class, $writer, $resource_type );
				$this->assertSame( $resource_type, $writer->resource(), $resource_type );
				continue;
			}

			// And nothing it cannot represent has one, so the two lists cannot drift apart
			// in the other direction either.
			if ( in_array( $resource_type, self::UNSUPPORTED, true ) ) {
				$this->assertNull( $writer, $resource_type );
			}
		}
	}

	/**
	 * The seam a third-party extension uses to say it satisfies a requirement the driver
	 * reported as missing.
	 */
	public function test_the_supports_filter_can_override_the_matrix(): void {
		add_filter(
			'storeseeder_platform_supports_woocommerce',
			static function ( array $matrix ): array {
				$matrix[ Resource::LICENSE ] = \StoreSeeder\Platforms\Capability::supported();
				return $matrix;
			}
		);

		$this->assertTrue( ( new Platform() )->supports()[ Resource::LICENSE ]->is_supported() );
	}
}
