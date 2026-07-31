<?php
/**
 * Tests for the generated-data cleanup.
 *
 * Driven through a stub platform whose writer records what it was asked to delete, so these
 * assert the orchestration — order, batching, what happens to a refusal — without depending
 * on any store's tables. The one thing they must prove is that a writer only ever hears about
 * ids the ledger recorded.
 *
 * @package StoreSeeder\Tests
 */

namespace StoreSeeder\Tests\Generation;

use StoreSeeder\Generation\Ledger;
use StoreSeeder\Generation\Purge;
use StoreSeeder\Platforms\Registry as Platform_Registry;
use StoreSeeder\Platforms\Resource;
use StoreSeeder\Platforms\Writer;
use StoreSeeder\Tests\Platform\StubPlatform;
use StoreSeeder\Tests\StoreSeederUnitTestCase;
use WP_Error;

/**
 * A writer that remembers, and can be told to refuse.
 */
class Recording_Writer extends Writer {
	/**
	 * Ids this writer was asked to delete.
	 *
	 * @var array<int, string>
	 */
	public array $deleted = array();

	/**
	 * Whether delete() refuses.
	 *
	 * @var bool
	 */
	public bool $refuse = false;

	/**
	 * The resource it claims.
	 *
	 * @var string
	 */
	private string $resource_type;

	/**
	 * @param string $resource_type Canonical resource name.
	 * @param bool   $refuse        Whether to refuse deletion.
	 */
	public function __construct( string $resource_type, bool $refuse = false ) {
		$this->resource_type = $resource_type;
		$this->refuse        = $refuse;
	}

	public function resource(): string {
		return $this->resource_type;
	}

	/**
	 * @param array<string, mixed> $entity Canonical entity.
	 *
	 * @return array<string, mixed>
	 */
	public function write( array $entity ) {
		return array( 'id' => 1 );
	}

	/**
	 * @param int|string $id Recorded id.
	 *
	 * @return true|WP_Error
	 */
	public function delete( $id ) {
		if ( $this->refuse ) {
			return new WP_Error( 'nope', 'The stub refuses.' );
		}

		$this->deleted[] = (string) $id;

		return true;
	}
}

/**
 * @covers \StoreSeeder\Generation\Purge
 */
class PurgeTest extends StoreSeederUnitTestCase {

	/**
	 * The stub's product writer, kept so tests can read what it deleted.
	 *
	 * @var Recording_Writer|null
	 */
	private $products = null;

	public function setUp(): void {
		parent::setUp();

		Ledger::install();
		Ledger::forget_all();

		$this->products = new Recording_Writer( Resource::PRODUCT );
	}

	public function tearDown(): void {
		Ledger::forget_all();
		remove_all_filters( 'storeseeder_platforms' );
		remove_all_filters( 'storeseeder_purge_order' );
		Platform_Registry::reset();
		parent::tearDown();
	}

	/**
	 * Register a stub platform with the writers given.
	 *
	 * @param array<string, Writer> $writers Resource => writer.
	 * @param string                $id      Platform id.
	 *
	 * @return void
	 */
	private function with_platform( array $writers, string $id = 'stub-cart' ): void {
		add_filter(
			'storeseeder_platforms',
			static function ( array $platforms ) use ( $writers, $id ): array {
				$platforms[] = new StubPlatform( $id, true, array( Resource::PRODUCT => true ), $writers );
				return $platforms;
			}
		);

		Platform_Registry::reset();
	}

	public function test_it_deletes_what_the_ledger_recorded(): void {
		$this->with_platform( array( Resource::PRODUCT => $this->products ) );

		Ledger::record( 'stub-cart', Resource::PRODUCT, 101 );
		Ledger::record( 'stub-cart', Resource::PRODUCT, 102 );

		$result = Purge::run();

		$this->assertSame( 2, $result['deleted'] );
		$this->assertSame( 0, $result['remaining'] );
		$this->assertSame( array( '102', '101' ), $this->products->deleted );
		$this->assertSame( array( Resource::PRODUCT => 2 ), $result['by_resource'] );
		$this->assertSame( array(), $result['errors'] );
	}

	public function test_a_deleted_row_is_forgotten(): void {
		$this->with_platform( array( Resource::PRODUCT => $this->products ) );

		Ledger::record( 'stub-cart', Resource::PRODUCT, 101 );

		Purge::run();

		$this->assertSame( 0, Ledger::total() );
	}

	/**
	 * The batch cap is what keeps a site with thousands of rows from timing out having
	 * reported nothing, so the response has to say what is left.
	 */
	public function test_it_stops_at_the_limit_and_reports_the_rest(): void {
		$this->with_platform( array( Resource::PRODUCT => $this->products ) );

		foreach ( range( 1, 5 ) as $id ) {
			Ledger::record( 'stub-cart', Resource::PRODUCT, $id );
		}

		$result = Purge::run( '', 2 );

		$this->assertSame( 2, $result['deleted'] );
		$this->assertSame( 3, $result['remaining'] );
		$this->assertSame( 3, Ledger::total() );
	}

	public function test_it_can_be_limited_to_one_resource(): void {
		$orders = new Recording_Writer( Resource::ORDER );

		$this->with_platform(
			array(
				Resource::PRODUCT => $this->products,
				Resource::ORDER   => $orders,
			)
		);

		Ledger::record( 'stub-cart', Resource::PRODUCT, 1 );
		Ledger::record( 'stub-cart', Resource::ORDER, 2 );

		$result = Purge::run( Resource::ORDER );

		$this->assertSame( 1, $result['deleted'] );
		$this->assertSame( array( '2' ), $orders->deleted );
		$this->assertSame( array(), $this->products->deleted );
		// remaining is scoped to the same resource, so the caller can loop on it.
		$this->assertSame( 0, $result['remaining'] );
		$this->assertSame( 1, Ledger::total() );
	}

	/**
	 * A refusal must keep the record. Forgetting it would leave the row in the store with
	 * nothing left that knows StoreSeeder put it there.
	 */
	public function test_a_refusal_is_reported_once_and_keeps_the_record(): void {
		$this->with_platform( array( Resource::PRODUCT => new Recording_Writer( Resource::PRODUCT, true ) ) );

		Ledger::record( 'stub-cart', Resource::PRODUCT, 1 );
		Ledger::record( 'stub-cart', Resource::PRODUCT, 2 );

		$result = Purge::run();

		$this->assertSame( 0, $result['deleted'] );
		$this->assertSame( 2, $result['remaining'] );
		$this->assertSame( array( 'The stub refuses.' ), $result['errors'] );
		$this->assertSame( 2, Ledger::total() );
	}

	/**
	 * A driver that ships no writer for a resource it recorded — possible when a platform
	 * withdrew support, or the rows predate the writer — says so rather than silently
	 * leaving a count that never moves.
	 */
	public function test_a_missing_writer_is_named(): void {
		$this->with_platform( array() );

		Ledger::record( 'stub-cart', Resource::PRODUCT, 1 );

		$result = Purge::run();

		$this->assertSame( 0, $result['deleted'] );
		$this->assertNotEmpty( $result['errors'] );
		$this->assertStringContainsString( Resource::PRODUCT, $result['errors'][0] );
	}

	/**
	 * Rows recorded for a platform whose driver is gone stay put. Deleting them is
	 * impossible and dropping the records would lose the only trace of them.
	 */
	public function test_rows_for_an_absent_driver_are_left_alone(): void {
		Ledger::record( 'departed-cart', Resource::PRODUCT, 1 );

		$result = Purge::run();

		$this->assertSame( 0, $result['deleted'] );
		$this->assertSame( 1, Ledger::total() );
		$this->assertStringContainsString( 'departed-cart', $result['errors'][0] );
	}

	// -----------------------------------------------------------------------------------
	// Deletion order.
	// -----------------------------------------------------------------------------------

	/**
	 * Children before parents. An order's transactions go first, because deleting the order
	 * takes its transaction rows with it and their ledger entries would then fail for ever.
	 */
	public function test_children_are_deleted_before_their_parents(): void {
		$order = Purge::order();

		$this->assertLessThan(
			array_search( Resource::ORDER, $order, true ),
			array_search( Resource::TRANSACTION, $order, true )
		);
		$this->assertLessThan(
			array_search( Resource::PRODUCT, $order, true ),
			array_search( Resource::PRODUCT_VARIATION, $order, true )
		);
	}

	public function test_order_covers_every_resource_exactly_once(): void {
		$order = Purge::order();

		$this->assertCount( count( Resource::all() ), $order );
		$this->assertSame( $order, array_unique( $order ) );
	}

	public function test_order_can_be_limited_to_one_resource(): void {
		$this->assertSame( array( Resource::PRODUCT ), Purge::order( Resource::PRODUCT ) );
		$this->assertSame( array(), Purge::order( 'widgets' ) );
	}

	public function test_the_order_filter_can_move_a_resource_to_the_front(): void {
		add_filter(
			'storeseeder_purge_order',
			static function (): array {
				return array( Resource::PRODUCT, 'not-a-resource', 42 );
			}
		);

		$order = Purge::order();

		$this->assertSame( Resource::PRODUCT, $order[0] );
		// Junk dropped, and everything the filter forgot appended rather than lost.
		$this->assertNotContains( 'not-a-resource', $order );
		$this->assertCount( count( Resource::all() ), $order );
	}
}
