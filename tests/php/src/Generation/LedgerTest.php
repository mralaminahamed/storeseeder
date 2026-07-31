<?php
/**
 * Tests for the ledger of generated rows.
 *
 * This is the table that makes "delete generated data" safe to offer, so the tests are about
 * the promise rather than about SQL: what is recorded is what gets deleted, and nothing else
 * is ever a candidate.
 *
 * @package StoreSeeder\Tests
 */

namespace StoreSeeder\Tests\Generation;

use StoreSeeder\Generation\Ledger;
use StoreSeeder\Platforms\Resource;
use StoreSeeder\Tests\StoreSeederUnitTestCase;

/**
 * @covers \StoreSeeder\Generation\Ledger
 */
class LedgerTest extends StoreSeederUnitTestCase {

	public function setUp(): void {
		parent::setUp();
		Ledger::install();
		Ledger::forget_all();
	}

	public function tearDown(): void {
		Ledger::forget_all();
		parent::tearDown();
	}

	public function test_the_table_exists_after_install(): void {
		global $wpdb;

		$table = Ledger::table();

		$this->assertSame(
			$table,
			$wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) )
		);
	}

	public function test_recording_counts_per_resource(): void {
		Ledger::record( 'fluent-cart', Resource::PRODUCT, 11 );
		Ledger::record( 'fluent-cart', Resource::PRODUCT, 12 );
		Ledger::record( 'fluent-cart', Resource::ORDER, 21 );

		$this->assertSame(
			array(
				Resource::PRODUCT => 2,
				Resource::ORDER   => 1,
			),
			Ledger::counts()
		);
		$this->assertSame( 3, Ledger::total() );
	}

	/**
	 * Not every resource has an integer id — a Fluent Cart cart session is identified by its
	 * hash, and storing it as an int would record 0 and make the row undeletable.
	 */
	public function test_a_string_id_survives_the_round_trip(): void {
		Ledger::record( 'fluent-cart', Resource::CART_SESSION, 'a1b2c3d4e5' );

		$batch = Ledger::batch( 'fluent-cart', Resource::CART_SESSION );

		$this->assertSame( 'a1b2c3d4e5', $batch[0]['object_id'] );
	}

	public function test_an_empty_or_zero_id_is_refused(): void {
		$this->assertFalse( Ledger::record( 'fluent-cart', Resource::PRODUCT, '' ) );
		$this->assertFalse( Ledger::record( 'fluent-cart', Resource::PRODUCT, 0 ) );
		$this->assertSame( 0, Ledger::total() );
	}

	public function test_counts_can_be_scoped_to_one_platform(): void {
		Ledger::record( 'fluent-cart', Resource::PRODUCT, 1 );
		Ledger::record( 'stub-cart', Resource::PRODUCT, 2 );

		$this->assertSame( 1, Ledger::total( 'fluent-cart' ) );
		$this->assertSame( 2, Ledger::total() );
		$this->assertSame( array( 'fluent-cart', 'stub-cart' ), Ledger::platforms() );
	}

	/**
	 * Newest first, so a cleanup undoes the most recent run before older ones — the order
	 * someone clearing up after a mistake expects.
	 */
	public function test_a_batch_returns_the_most_recent_rows_first(): void {
		foreach ( range( 1, 5 ) as $id ) {
			Ledger::record( 'fluent-cart', Resource::PRODUCT, $id );
		}

		$batch = Ledger::batch( 'fluent-cart', Resource::PRODUCT, 2 );

		$this->assertCount( 2, $batch );
		$this->assertSame( array( '5', '4' ), array_column( $batch, 'object_id' ) );
	}

	public function test_a_batch_is_scoped_to_its_platform_and_resource(): void {
		Ledger::record( 'fluent-cart', Resource::PRODUCT, 1 );
		Ledger::record( 'fluent-cart', Resource::ORDER, 2 );
		Ledger::record( 'stub-cart', Resource::PRODUCT, 3 );

		$batch = Ledger::batch( 'fluent-cart', Resource::PRODUCT );

		$this->assertCount( 1, $batch );
		$this->assertSame( '1', $batch[0]['object_id'] );
	}

	public function test_forgetting_drops_only_the_named_records(): void {
		Ledger::record( 'fluent-cart', Resource::PRODUCT, 1 );
		Ledger::record( 'fluent-cart', Resource::PRODUCT, 2 );

		$batch = Ledger::batch( 'fluent-cart', Resource::PRODUCT );

		$this->assertSame( 1, Ledger::forget( array( $batch[0]['id'] ) ) );
		$this->assertSame( 1, Ledger::total() );
	}

	public function test_forgetting_nothing_is_not_an_error(): void {
		$this->assertSame( 0, Ledger::forget( array() ) );
		$this->assertSame( 0, Ledger::forget( array( 0, -1 ) ) );
	}

	public function test_forget_all_can_be_scoped_to_one_platform(): void {
		Ledger::record( 'fluent-cart', Resource::PRODUCT, 1 );
		Ledger::record( 'stub-cart', Resource::PRODUCT, 2 );

		$this->assertSame( 1, Ledger::forget_all( 'stub-cart' ) );
		$this->assertSame( array( 'fluent-cart' ), Ledger::platforms() );
	}

	/**
	 * Installing twice must be harmless: it runs on activation and again from admin_init
	 * whenever the stored version is behind.
	 */
	public function test_installing_again_keeps_what_is_recorded(): void {
		Ledger::record( 'fluent-cart', Resource::PRODUCT, 1 );

		Ledger::install();

		$this->assertSame( 1, Ledger::total() );
		$this->assertSame( Ledger::DB_VERSION, get_option( Ledger::DB_VERSION_OPTION ) );
	}

	public function test_maybe_install_does_nothing_once_the_version_matches(): void {
		update_option( Ledger::DB_VERSION_OPTION, Ledger::DB_VERSION );
		Ledger::record( 'fluent-cart', Resource::PRODUCT, 1 );

		Ledger::maybe_install();

		$this->assertSame( 1, Ledger::total() );
	}
}
