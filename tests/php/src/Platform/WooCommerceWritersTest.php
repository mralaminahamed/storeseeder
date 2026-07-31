<?php
/**
 * Tests for the WooCommerce writers.
 *
 * These go through the real CRUD objects rather than mocking them, because the things worth
 * checking are exactly the ones a mock would let through: that money arrives as a decimal and
 * not a hundredfold, that a status lands as WooCommerce spells it, and that a record comes back
 * out of WooCommerce's own loaders — which is the difference between a row in a table and a
 * product the store can sell.
 *
 * @package StoreSeeder\Tests
 */

namespace StoreSeeder\Tests\Platform;

use Faker\Factory;
use StoreSeeder\Platforms\Drivers\Woo_Commerce\Platform;
use StoreSeeder\Platforms\Resource;
use StoreSeeder\Platforms\Status;
use StoreSeeder\Platforms\Writer;
use StoreSeeder\Tests\StoreSeederUnitTestCase;

/**
 * @covers \StoreSeeder\Platforms\Drivers\Woo_Commerce\Writer
 * @covers \StoreSeeder\Platforms\Drivers\Woo_Commerce\Writers\Product
 * @covers \StoreSeeder\Platforms\Drivers\Woo_Commerce\Writers\Customer
 * @covers \StoreSeeder\Platforms\Drivers\Woo_Commerce\Writers\Order
 * @covers \StoreSeeder\Platforms\Drivers\Woo_Commerce\Writers\Coupon
 */
class WooCommerceWritersTest extends StoreSeederUnitTestCase {

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

	/**
	 * A writer, wired the way Generator::get_writer() wires one.
	 *
	 * @param string $resource_type Canonical resource name.
	 *
	 * @return Writer
	 */
	private function writer( string $resource_type ): Writer {
		$writer = ( new Platform() )->writer( $resource_type );

		$this->assertInstanceOf( Writer::class, $writer, $resource_type );

		// Seeded, so a failure is reproducible rather than a different random string each run.
		$writer->set_faker( Factory::create( 'en_US' ) );
		$writer->set_params( array() );

		return $writer;
	}

	/**
	 * A canonical product entity, as the generator builds one.
	 *
	 * @param array<string, mixed> $overrides Fields to replace.
	 *
	 * @return array<string, mixed>
	 */
	private function product_entity( array $overrides = array() ): array {
		return array_merge(
			array(
				'title'            => 'Test Widget',
				'description'      => 'A widget for testing.',
				// £45.67 in minor units. The number is chosen so a missing division shows up
				// as 4567 rather than as something plausible.
				'price'            => 4567,
				'sku'              => 'TEST-SKU-1',
				'status'           => Status::PUBLISHED,
				'stock'            => 7,
				'fulfillment_type' => 'physical',
			),
			$overrides
		);
	}

	// -----------------------------------------------------------------------------------
	// Products.
	// -----------------------------------------------------------------------------------

	public function test_a_product_is_created_through_the_crud_layer(): void {
		$result = $this->writer( Resource::PRODUCT )->write( $this->product_entity() );

		$this->assertNotWPError( $result );

		$product = wc_get_product( $result['id'] );

		$this->assertInstanceOf( 'WC_Product', $product );
		$this->assertSame( 'Test Widget', $product->get_name() );
		$this->assertSame( 'publish', $product->get_status() );
		$this->assertSame( 7, $product->get_stock_quantity() );
		// Purchasable is the summary assertion: it needs a price, a status and stock all
		// right at once, which is what "a product the store can sell" means.
		$this->assertTrue( $product->is_purchasable() );
	}

	/**
	 * The money rule, at the boundary where it is broken: entities carry integer minor units
	 * and WooCommerce stores decimals.
	 */
	public function test_a_products_price_is_converted_from_minor_units(): void {
		$result = $this->writer( Resource::PRODUCT )->write( $this->product_entity() );

		$this->assertNotWPError( $result );
		$this->assertSame( '45.67', wc_get_product( $result['id'] )->get_regular_price() );
	}

	public function test_a_digital_product_is_virtual_and_not_yet_downloadable(): void {
		$result = $this->writer( Resource::PRODUCT )->write(
			$this->product_entity(
				array(
					'fulfillment_type' => 'digital',
					'sku'              => 'TEST-SKU-DIGITAL',
				)
			)
		);

		$this->assertNotWPError( $result );

		$product = wc_get_product( $result['id'] );

		$this->assertTrue( $product->is_virtual() );
		// Downloadable with no file is a product WooCommerce refuses to sell, so the flag
		// waits for the Product Downloads generator.
		$this->assertFalse( $product->is_downloadable() );
	}

	/**
	 * WooCommerce throws on a duplicate SKU rather than saving one, so this is the difference
	 * between a batch that completes and one that fails from the second item on.
	 */
	public function test_a_duplicate_sku_is_resolved_rather_than_fataling(): void {
		$writer = $this->writer( Resource::PRODUCT );

		$first  = $writer->write( $this->product_entity( array( 'sku' => 'CLASH' ) ) );
		$second = $writer->write( $this->product_entity( array( 'sku' => 'CLASH' ) ) );

		$this->assertNotWPError( $first );
		$this->assertNotWPError( $second );
		$this->assertNotSame(
			wc_get_product( $first['id'] )->get_sku(),
			wc_get_product( $second['id'] )->get_sku()
		);
	}

	public function test_a_product_is_deleted_permanently_rather_than_trashed(): void {
		$writer = $this->writer( Resource::PRODUCT );
		$result = $writer->write( $this->product_entity( array( 'sku' => 'TEST-DELETE' ) ) );

		$this->assertNotWPError( $result );
		$this->assertTrue( $writer->delete( $result['id'] ) );
		$this->assertFalse( wc_get_product( $result['id'] ) );
	}

	/**
	 * The ledger can outlive the row it points at — a database restored from elsewhere, or a
	 * product deleted by hand — and a permanent failure there would block every later cleanup.
	 */
	public function test_deleting_something_already_gone_is_a_success(): void {
		$this->assertTrue( $this->writer( Resource::PRODUCT )->delete( 999999 ) );
	}

	// -----------------------------------------------------------------------------------
	// Customers.
	// -----------------------------------------------------------------------------------

	public function test_a_customer_is_a_wordpress_user_with_addresses(): void {
		$result = $this->writer( Resource::CUSTOMER )->write(
			array(
				'email'            => 'writer-test@example.test',
				'first_name'       => 'Ada',
				'last_name'        => 'Lovelace',
				'full_name'        => 'Ada Lovelace',
				'username_base'    => 'ada',
				'with_account'     => true,
				'notes'            => 'A note.',
				'meta'             => array(
					'total_spent'  => 120.50,
					'total_orders' => 3,
				),
				'billing_address'  => array(
					'name'      => 'Ada Lovelace',
					'address_1' => '1 Test Street',
					'address_2' => '',
					'city'      => 'London',
					'state'     => 'LDN',
					'postcode'  => 'E1 1AA',
					'country'   => 'GB',
					'phone'     => '01234 567890',
				),
				'shipping_address' => array(),
			)
		);

		$this->assertNotWPError( $result );

		$customer = new \WC_Customer( $result['id'] );

		$this->assertSame( 'writer-test@example.test', $customer->get_email() );
		$this->assertSame( 'London', $customer->get_billing_city() );
		// An empty shipping address falls back to billing rather than shipping nowhere.
		$this->assertSame( 'London', $customer->get_shipping_city() );
		$this->assertSame( '01234 567890', $customer->get_billing_phone() );
		$this->assertContains( 'customer', (array) $customer->get_role() ? array( $customer->get_role() ) : array() );
	}

	public function test_a_duplicate_email_is_refused_rather_than_throwing(): void {
		$writer = $this->writer( Resource::CUSTOMER );
		$entity = array(
			'email'            => 'duplicate@example.test',
			'first_name'       => 'Grace',
			'last_name'        => 'Hopper',
			'full_name'        => 'Grace Hopper',
			'username_base'    => 'grace',
			'with_account'     => true,
			'notes'            => '',
			'meta'             => array(
				'total_spent'  => 0,
				'total_orders' => 0,
			),
			'billing_address'  => array( 'city' => 'Boston' ),
			'shipping_address' => array(),
		);

		$this->assertNotWPError( $writer->write( $entity ) );

		$second = $writer->write( $entity );

		$this->assertWPError( $second );
		$this->assertSame( 'email_exists', $second->get_error_code() );
	}

	// -----------------------------------------------------------------------------------
	// Orders.
	// -----------------------------------------------------------------------------------

	/**
	 * A canonical order entity.
	 *
	 * @param string               $status Canonical status.
	 * @param array<string, mixed> $extra  Fields merged over the defaults.
	 *
	 * @return array<string, mixed>
	 */
	private function order_entity( string $status = Status::COMPLETED, array $extra = array() ): array {
		$address = array(
			'name'      => 'Ada Lovelace',
			'company'   => '',
			'address_1' => '1 Test Street',
			'address_2' => '',
			'city'      => 'London',
			'state'     => 'LDN',
			'postcode'  => 'E1 1AA',
			'country'   => 'GB',
			'phone'     => '01234 567890',
		);

		return array_merge(
			array(
				'items'          => array(
					array(
						'unit_price' => 1000,
						'quantity'   => 2,
					),
				),
				'tax_rate'       => 0,
				'use_coupon'     => false,
				'discount_total' => 0,
				'shipping_total' => 0,
				'currency'       => 'USD',
				'status'         => $status,
				'payment_method' => 'stripe',
				'invoice_no'     => 'INV-1',
				'receipt_number' => 'RCP-1',
				'customer_note'  => '',
				'ip_address'     => '198.51.100.7',
				'user_agent'     => 'StoreSeeder/1.0',
				'paid_at'        => null,
				'completed_at'   => null,
				'addresses'      => array(
					'billing'  => $address,
					'shipping' => $address,
				),
				'created_at'     => '2026-01-01 10:00:00',
			),
			$extra
		);
	}

	public function test_an_order_carries_line_items_from_real_products(): void {
		$product = $this->writer( Resource::PRODUCT )->write( $this->product_entity( array( 'sku' => 'ORDER-ITEM' ) ) );
		$this->assertNotWPError( $product );

		$result = $this->writer( Resource::ORDER )->write( $this->order_entity() );

		$this->assertNotWPError( $result );

		$order = wc_get_order( $result['id'] );

		$this->assertInstanceOf( 'WC_Order', $order );
		$this->assertNotEmpty( $order->get_items() );
		// Totalled by WooCommerce from the lines, not copied off the entity.
		$this->assertGreaterThan( 0, (float) $order->get_total() );
		$this->assertSame( 'storeseeder', $order->get_created_via() );
	}

	/**
	 * The canonical vocabulary is deliberately no platform's spelling: WooCommerce wants
	 * `on-hold` where StoreSeeder says `on_hold`, and an unmapped status silently becomes
	 * `wc-pending`.
	 */
	public function test_canonical_statuses_map_to_woocommerce_spelling(): void {
		$this->assertNotWPError( $this->writer( Resource::PRODUCT )->write( $this->product_entity( array( 'sku' => 'STATUS-MAP' ) ) ) );

		foreach ( array( Status::ON_HOLD => 'on-hold', Status::COMPLETED => 'completed', Status::CANCELLED => 'cancelled' ) as $canonical => $expected ) {
			$result = $this->writer( Resource::ORDER )->write( $this->order_entity( $canonical ) );

			$this->assertNotWPError( $result );
			$this->assertSame( $expected, wc_get_order( $result['id'] )->get_status(), $canonical );
		}
	}

	public function test_an_order_without_any_products_says_what_to_generate_first(): void {
		// The store made to look empty by filtering the product query, rather than by deleting
		// every product: deleting them takes a CRUD round-trip each and leaves the rest of the
		// suite without a catalogue, which is the pollution that makes a suite order-dependent.
		add_filter(
			'woocommerce_product_data_store_cpt_get_products_query',
			static function ( array $query ): array {
				$query['post__in'] = array( 0 );
				return $query;
			}
		);

		$result = $this->writer( Resource::ORDER )->write( $this->order_entity() );

		remove_all_filters( 'woocommerce_product_data_store_cpt_get_products_query' );

		$this->assertWPError( $result );
		$this->assertSame( 'no_products', $result->get_error_code() );
		$this->assertStringContainsString( 'products', $result->get_error_message() );
	}

	/**
	 * Shipping is a line item in WooCommerce, not a column. Setting `set_shipping_total()` before
	 * `calculate_totals()` looks right and is discarded — the totals run recomputes shipping from
	 * the order's shipping lines, of which there were none.
	 */
	public function test_shipping_becomes_a_line_item_and_survives_the_totals_run(): void {
		$this->assertNotWPError( $this->writer( Resource::PRODUCT )->write( $this->product_entity( array( 'sku' => 'ORDER-SHIP' ) ) ) );

		$result = $this->writer( Resource::ORDER )->write( $this->order_entity( Status::COMPLETED, array( 'shipping_total' => 675 ) ) );
		$this->assertNotWPError( $result );

		$order = wc_get_order( $result['id'] );

		$this->assertSame( '6.75', $order->get_shipping_total() );
		$this->assertCount( 1, $order->get_items( 'shipping' ) );
	}

	/**
	 * Free shipping is still a shipping line, worth nothing and named for the method — which is what
	 * a real WooCommerce order looks like when a free-shipping zone matched. Omitting the line would
	 * make the order look as though shipping was never considered.
	 */
	public function test_free_shipping_is_a_zero_line_named_for_the_method(): void {
		$this->assertNotWPError( $this->writer( Resource::PRODUCT )->write( $this->product_entity( array( 'sku' => 'ORDER-FREE' ) ) ) );

		$result = $this->writer( Resource::ORDER )->write( $this->order_entity( Status::COMPLETED, array( 'shipping_total' => 0 ) ) );
		$this->assertNotWPError( $result );

		$order = wc_get_order( $result['id'] );

		$this->assertSame( '0', $order->get_shipping_total() );

		$lines = $order->get_items( 'shipping' );
		$this->assertCount( 1, $lines );

		$line = reset( $lines );
		$this->assertSame( 'free_shipping', $line->get_method_id() );
		// `0.00` on the line, `0` on the order: WooCommerce formats a line total to the currency's
		// decimals and an order total to the shortest form. Compared numerically rather than
		// asserting one of the two spellings.
		$this->assertSame( 0.0, (float) $line->get_total() );
	}

	/**
	 * A discount set before the totals run is wiped by it, which is why it is applied afterwards.
	 * The assertion is on the total rather than on a discount column, because that is where a lost
	 * discount actually shows.
	 */
	public function test_a_discount_reduces_the_total(): void {
		$this->assertNotWPError( $this->writer( Resource::PRODUCT )->write( $this->product_entity( array( 'sku' => 'ORDER-DISC' ) ) ) );

		$full = $this->writer( Resource::ORDER )->write( $this->order_entity() );
		$cut  = $this->writer( Resource::ORDER )->write( $this->order_entity( Status::COMPLETED, array( 'discount_total' => 500 ) ) );

		$this->assertNotWPError( $full );
		$this->assertNotWPError( $cut );

		$this->assertSame(
			round( (float) wc_get_order( $full['id'] )->get_total() - 5, 2 ),
			round( (float) wc_get_order( $cut['id'] )->get_total(), 2 )
		);
	}

	public function test_an_order_carries_the_shopper_metadata(): void {
		$this->assertNotWPError( $this->writer( Resource::PRODUCT )->write( $this->product_entity( array( 'sku' => 'ORDER-META' ) ) ) );

		$result = $this->writer( Resource::ORDER )->write(
			$this->order_entity(
				Status::COMPLETED,
				array(
					'customer_note' => 'Leave it with the neighbour.',
					'ip_address'    => '203.0.113.9',
					'user_agent'    => 'StoreSeeder/Test',
					'paid_at'       => '2026-01-02 11:00:00',
					'completed_at'  => '2026-01-03 12:00:00',
				)
			)
		);
		$this->assertNotWPError( $result );

		$order = wc_get_order( $result['id'] );

		$this->assertSame( 'Leave it with the neighbour.', $order->get_customer_note() );
		$this->assertSame( '203.0.113.9', $order->get_customer_ip_address() );
		$this->assertSame( 'StoreSeeder/Test', $order->get_customer_user_agent() );
		$this->assertNotNull( $order->get_date_paid() );
		$this->assertNotNull( $order->get_date_completed() );
	}

	/**
	 * The dates are set after the status, because `set_status( 'completed' )` stamps both itself and
	 * would overwrite a date set before it.
	 */
	public function test_an_unpaid_order_has_no_payment_date(): void {
		$this->assertNotWPError( $this->writer( Resource::PRODUCT )->write( $this->product_entity( array( 'sku' => 'ORDER-UNPAID' ) ) ) );

		$result = $this->writer( Resource::ORDER )->write( $this->order_entity( Status::ON_HOLD ) );
		$this->assertNotWPError( $result );

		$order = wc_get_order( $result['id'] );

		$this->assertNull( $order->get_date_paid() );
		$this->assertNull( $order->get_date_completed() );
	}

	public function test_a_company_reaches_both_addresses(): void {
		$this->assertNotWPError( $this->writer( Resource::PRODUCT )->write( $this->product_entity( array( 'sku' => 'ORDER-CO' ) ) ) );

		$entity = $this->order_entity();
		$entity['addresses']['billing']['company']  = 'Analytical Engines Ltd';
		$entity['addresses']['shipping']['company'] = 'Analytical Engines Ltd';

		$result = $this->writer( Resource::ORDER )->write( $entity );
		$this->assertNotWPError( $result );

		$order = wc_get_order( $result['id'] );

		$this->assertSame( 'Analytical Engines Ltd', $order->get_billing_company() );
		$this->assertSame( 'Analytical Engines Ltd', $order->get_shipping_company() );
	}

	// -----------------------------------------------------------------------------------
	// Coupons.
	// -----------------------------------------------------------------------------------

	public function test_a_percentage_coupon_keeps_its_percent(): void {
		$result = $this->writer( Resource::COUPON )->write(
			array(
				'code'        => 'PCT20',
				'discount'    => 20,
				'type'        => 'percentage',
				'description' => 'Twenty off.',
				'usage_limit' => 5,
				'status'      => 'active',
				'expires_at'  => '2027-01-01 00:00:00',
			)
		);

		$this->assertNotWPError( $result );

		$coupon = new \WC_Coupon( $result['id'] );

		$this->assertSame( 'percent', $coupon->get_discount_type() );
		$this->assertSame( '20', $coupon->get_amount() );
	}

	/**
	 * A fixed discount arrives in minor units, like every other amount.
	 */
	public function test_a_fixed_coupon_is_converted_from_minor_units(): void {
		$result = $this->writer( Resource::COUPON )->write(
			array(
				'code'        => 'FIXED10',
				'discount'    => 1000,
				'type'        => 'fixed',
				'description' => 'Ten off.',
				'usage_limit' => 5,
				'status'      => 'active',
				'expires_at'  => '2027-01-01 00:00:00',
			)
		);

		$this->assertNotWPError( $result );

		$coupon = new \WC_Coupon( $result['id'] );

		$this->assertSame( 'fixed_cart', $coupon->get_discount_type() );
		// Padded to the store's price decimals, which is how WooCommerce stores an amount —
		// unlike a percentage, which is a bare number.
		$this->assertSame( '10.00', $coupon->get_amount() );
	}

	/**
	 * WooCommerce has no free-shipping discount *type* — it is a flag. A coupon that stored
	 * the canonical type verbatim would be ignored by every calculation.
	 */
	public function test_free_shipping_becomes_a_flag_not_a_type(): void {
		$result = $this->writer( Resource::COUPON )->write(
			array(
				'code'        => 'FREESHIP',
				'discount'    => 0,
				'type'        => 'free_shipping',
				'description' => 'Free shipping.',
				'usage_limit' => 5,
				'status'      => 'active',
				'expires_at'  => '2027-01-01 00:00:00',
			)
		);

		$this->assertNotWPError( $result );

		$coupon = new \WC_Coupon( $result['id'] );

		$this->assertTrue( $coupon->get_free_shipping() );
		$this->assertSame( 'fixed_cart', $coupon->get_discount_type() );
	}

	public function test_a_duplicate_coupon_code_is_resolved(): void {
		$writer = $this->writer( Resource::COUPON );
		$entity = array(
			'code'        => 'SAME',
			'discount'    => 15,
			'type'        => 'percentage',
			'description' => '',
			'usage_limit' => 1,
			'status'      => 'active',
			'expires_at'  => '2027-01-01 00:00:00',
		);

		$first  = $writer->write( $entity );
		$second = $writer->write( $entity );

		$this->assertNotWPError( $first );
		$this->assertNotWPError( $second );
		$this->assertNotSame( $first['code'], $second['code'] );
	}
}
