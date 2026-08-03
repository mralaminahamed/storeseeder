<?php
/**
 * Tests for the per-resource result filter every writer offers.
 *
 * The hook name is derived from the writer's resource, which is what stops it drifting. Before
 * that, three writers offered no filter at all and the shipping-plan writer fired a hook named
 * after a resource that had been renamed — both invisible unless you read all eighteen files.
 *
 * @package StoreSeeder\Tests
 */

namespace StoreSeeder\Tests\Platform;

use StoreSeeder\Platforms\Registry;
use StoreSeeder\Platforms\Resource;
use StoreSeeder\Tests\StoreSeederUnitTestCase;

/**
 * @covers \StoreSeeder\Platforms\Writer::filter_result
 */
class WriterResultFilterTest extends StoreSeederUnitTestCase {

	/**
	 * Every writer the Fluent Cart driver ships offers a filter named for its resource.
	 *
	 * Asserted by reading the source rather than by running a write: a write needs a live
	 * store and fixtures for every resource, and what is under test here is the contract —
	 * that no writer returns its result without offering the hook.
	 */
	public function test_every_writer_offers_the_derived_result_filter(): void {
		$dir = dirname( __DIR__, 4 ) . '/includes/Platforms/Drivers/Fluent_Cart/Writers';

		$this->assertDirectoryExists( $dir );

		$files = glob( $dir . '/*.php' );

		$this->assertNotEmpty( $files );

		// A writer with no result has nothing to filter. `Media` is the only one: attachments are
		// created alongside products by `Platforms\Media`, never generated on their own, so its
		// `write()` is unreachable and returns a WP_Error saying so. It exists because the purge
		// resolves a deleter by resource name.
		$resultless = array( 'Media.php' );

		foreach ( (array) $files as $file ) {
			$source = (string) file_get_contents( (string) $file );
			$name   = basename( (string) $file );

			if ( in_array( $name, $resultless, true ) ) {
				continue;
			}

			// Either the writer calls it, or it returns a shared helper on the base class that
			// does — `create_term_in()` is how brands, categories and tags all persist, and it
			// fires the filter once for the three of them rather than three near-identical times.
			$offers = false !== strpos( $source, '$this->filter_result(' )
				|| false !== strpos( $source, '$this->create_term_in(' );

			$this->assertTrue(
				$offers,
				"{$name} returns its result without offering storeseeder_{resource}_generation_result"
			);
		}
	}

	/**
	 * The hook name comes from resource(), so a writer cannot name it wrongly.
	 *
	 * The one hardcoded result filter left is the deprecated shipping alias, which is
	 * deliberate and documented as such.
	 */
	public function test_no_writer_hardcodes_a_result_filter_except_the_legacy_alias(): void {
		$dir   = dirname( __DIR__, 4 ) . '/includes/Platforms/Drivers/Fluent_Cart/Writers';
		$files = glob( $dir . '/*.php' );

		foreach ( (array) $files as $file ) {
			$source = (string) file_get_contents( (string) $file );

			if ( 0 === preg_match_all( "/'storeseeder_[a-z_]+_generation_result'/", $source, $matches ) ) {
				continue;
			}

			foreach ( $matches[0] as $hook ) {
				$this->assertSame(
					"'storeseeder_shipping_method_generation_result'",
					$hook,
					basename( (string) $file ) . " hardcodes {$hook}; use filter_result() so the name follows the resource"
				);
			}
		}
	}

	/**
	 * The filter fires under the resource's own name, and can reshape the result.
	 */
	public function test_the_filter_fires_for_the_writer_resource(): void {
		$platform = Registry::instance()->get( 'fluent-cart' );
		$this->assertNotNull( $platform );

		$writer = $platform->writer( Resource::COUPON );
		$this->assertNotNull( $writer );

		$seen = array();

		add_filter(
			'storeseeder_coupon_generation_result',
			static function ( array $result, $id, array $data ) use ( &$seen ): array {
				$seen = array( $id, $data );

				$result['tagged'] = true;

				return $result;
			},
			10,
			3
		);

		// No setAccessible(): it has been a no-op since PHP 8.1 and is deprecated in 8.5,
		// which PHPUnit reports as a risky test.
		$method   = new \ReflectionMethod( $writer, 'filter_result' );
		$filtered = $method->invoke( $writer, array( 'id' => 7 ), 7, array( 'code' => 'SAVE10' ) );

		$this->assertTrue( $filtered['tagged'] );
		$this->assertSame( array( 7, array( 'code' => 'SAVE10' ) ), $seen );

		remove_all_filters( 'storeseeder_coupon_generation_result' );
	}

	/**
	 * The renamed shipping hook keeps working: callbacks registered against the old name
	 * still run, after the correctly named one.
	 */
	public function test_the_legacy_shipping_hook_still_receives_the_result(): void {
		$source = (string) file_get_contents(
			dirname( __DIR__, 4 ) . '/includes/Platforms/Drivers/Fluent_Cart/Writers/Shipping_Plan.php'
		);

		$this->assertStringContainsString( '$this->filter_result(', $source );
		$this->assertStringContainsString( 'storeseeder_shipping_method_generation_result', $source );
		$this->assertStringContainsString( '@deprecated 1.1.0', $source );

		// Order matters: the canonical hook runs first, so the legacy one sees the same
		// result any modern callback produced.
		$this->assertLessThan(
			strpos( $source, 'storeseeder_shipping_method_generation_result' ),
			strpos( $source, '$this->filter_result(' )
		);
	}
}
