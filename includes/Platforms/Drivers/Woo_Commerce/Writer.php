<?php
/**
 * Shared behaviour for the WooCommerce writers
 *
 * @since   1.1.0
 * @package StoreSeeder\Platforms\Drivers\Woo_Commerce
 */

namespace StoreSeeder\Platforms\Drivers\Woo_Commerce;

use StoreSeeder\Platforms\Status;
use StoreSeeder\Platforms\Writer as Base_Writer;
use WC_Data;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * The parts every WooCommerce writer needs.
 *
 * Three of them, and each exists because getting it wrong is invisible:
 *
 * **Money.** A canonical entity carries integer minor units; WooCommerce stores decimal
 * strings. Dividing in one place rather than in fifteen is what keeps a £456.78 line from
 * shipping as £4.57 in the one writer that forgot.
 *
 * **Statuses.** The canonical vocabulary is deliberately no platform's spelling, and
 * WooCommerce wants `on-hold` where StoreSeeder says `on_hold`. Mapped here, once.
 *
 * **Foreign keys.** Orders need products, refunds need orders, tax lines need both. Reading
 * to find them is the writer's job — see the note in `Platforms\Writer` — and the queries are
 * identical across writers, so they live here.
 *
 * @since 1.1.0
 */
abstract class Writer extends Base_Writer {
	/**
	 * Canonical order status to WooCommerce's spelling.
	 *
	 * Unprefixed: `WC_Order::set_status()` adds `wc-` itself, and passing a prefixed value
	 * produces `wc-wc-completed`, which no query matches and no screen shows.
	 *
	 * @since 1.1.0
	 * @var array<string, string>
	 */
	protected const ORDER_STATUS = array(
		Status::PENDING    => 'pending',
		Status::PROCESSING => 'processing',
		Status::ON_HOLD    => 'on-hold',
		Status::COMPLETED  => 'completed',
		Status::CANCELLED  => 'cancelled',
		Status::FAILED     => 'failed',
		Status::REFUNDED   => 'refunded',
	);

	/**
	 * Canonical publication status to WordPress post status.
	 *
	 * @since 1.1.0
	 * @var array<string, string>
	 */
	protected const POST_STATUS = array(
		Status::PUBLISHED => 'publish',
		Status::DRAFT     => 'draft',
		Status::PRIVATE   => 'private',
	);

	/**
	 * Remove a row this writer created.
	 *
	 * WooCommerce's CRUD objects know how to delete themselves — a product takes its
	 * variations, meta and term relationships with it, an order its items. Doing it by hand
	 * leaves all of that behind, which is the same reason the writers create through the
	 * CRUD layer rather than inserting posts.
	 *
	 * @since 1.1.0
	 *
	 * @param int|string $id The identifier reported when the row was created.
	 *
	 * @return true|WP_Error
	 */
	public function delete( $id ) {
		return $this->delete_crud( $id );
	}

	/**
	 * Load a WooCommerce object by id and delete it permanently.
	 *
	 * Already gone counts as success: the ledger can outlive what it points at, and treating
	 * a missing row as a failure would block the rest of a cleanup for ever.
	 *
	 * @since 1.1.0
	 *
	 * @param int|string $id     Object id.
	 * @param string     $loader WooCommerce loader function, e.g. `wc_get_product`.
	 *
	 * @return true|WP_Error
	 */
	protected function delete_crud( $id, string $loader = 'wc_get_product' ) {
		if ( ! function_exists( $loader ) ) {
			return new WP_Error(
				'storeseeder_delete_unsupported',
				__( 'WooCommerce is not active on this site.', 'storeseeder' )
			);
		}

		$object = $loader( (int) $id );

		if ( ! $object instanceof WC_Data ) {
			return true;
		}

		// force: true rather than trashing. A trashed test order still holds its number and
		// still shows in the admin's Trash, which is not what "delete the generated data"
		// means.
		$object->delete( true );

		return true;
	}

	/**
	 * Integer minor units to the decimal string WooCommerce stores.
	 *
	 * @since 1.1.0
	 *
	 * @param int $minor Amount in the currency's minor unit.
	 *
	 * @return string
	 */
	protected function to_decimal( int $minor ): string {
		return (string) wc_format_decimal( $minor / 100, wc_get_price_decimals() );
	}

	/**
	 * Canonical order status to WooCommerce's.
	 *
	 * @since 1.1.0
	 *
	 * @param string $status Canonical status.
	 *
	 * @return string
	 */
	protected function map_order_status( string $status ): string {
		return self::ORDER_STATUS[ $status ] ?? 'pending';
	}

	/**
	 * Canonical publication status to a post status.
	 *
	 * @since 1.1.0
	 *
	 * @param string $status Canonical status.
	 *
	 * @return string
	 */
	protected function map_post_status( string $status ): string {
		return self::POST_STATUS[ $status ] ?? 'publish';
	}

	/**
	 * Ids of existing published products.
	 *
	 * @since 1.1.0
	 *
	 * @param int    $limit How many to return.
	 * @param string $type  Product type to restrict to, or '' for any.
	 *
	 * @return array<int, int>
	 */
	protected function product_ids( int $limit = 20, string $type = '' ): array {
		$args = array(
			'limit'   => max( 1, $limit ),
			'status'  => 'publish',
			'return'  => 'ids',
			'orderby' => 'rand',
		);

		if ( '' !== $type ) {
			$args['type'] = $type;
		}

		return array_map( 'intval', (array) wc_get_products( $args ) );
	}

	/**
	 * One existing product, or null when the store has none.
	 *
	 * @since 1.1.0
	 *
	 * @param string $type Product type to restrict to, or '' for any.
	 *
	 * @return \WC_Product|null
	 */
	protected function random_product( string $type = '' ): ?\WC_Product {
		$ids = $this->product_ids( 20, $type );

		if ( array() === $ids ) {
			return null;
		}

		$product = wc_get_product( $this->faker()->randomElement( $ids ) );

		return $product instanceof \WC_Product ? $product : null;
	}

	/**
	 * One existing order, restricted to the statuses given.
	 *
	 * @since 1.1.0
	 *
	 * @param array<int, string> $statuses WooCommerce statuses, unprefixed.
	 *
	 * @return \WC_Order|null
	 */
	protected function random_order( array $statuses = array() ): ?\WC_Order {
		$ids = wc_get_orders(
			array(
				'limit'   => 20,
				'orderby' => 'rand',
				'return'  => 'ids',
				// Orders only. Without this, `wc_get_orders()` also returns refunds — they
				// are orders in WooCommerce's data model — and a refund is a
				// `WC_Order_Refund`, which does *not* extend `WC_Order`. The instanceof
				// below would then reject it and the caller would be told the store has no
				// orders while it plainly has.
				'type'    => 'shop_order',
				'status'  => array() === $statuses ? array_values( self::ORDER_STATUS ) : $statuses,
			)
		);

		if ( ! is_array( $ids ) || array() === $ids ) {
			return null;
		}

		$order = wc_get_order( $this->faker()->randomElement( $ids ) );

		return $order instanceof \WC_Order ? $order : null;
	}

	/**
	 * The id of one existing customer, or 0 for a guest.
	 *
	 * Zero is a real answer, not a failure: a guest order is a case worth testing, and a
	 * store with no customer accounts should still be able to generate orders.
	 *
	 * @since 1.1.0
	 *
	 * @return int
	 */
	protected function random_customer_id(): int {
		$ids = get_users(
			array(
				'role__in' => array( 'customer', 'subscriber' ),
				'number'   => 20,
				'fields'   => 'ID',
				'orderby'  => 'rand',
			)
		);

		if ( array() === $ids ) {
			return 0;
		}

		return (int) $this->faker()->randomElement( $ids );
	}

	/**
	 * The error a writer returns when the store is missing what it needs first.
	 *
	 * Names the generator to run instead of failing opaquely — the reason
	 * `Generation\Generator` collects per-item errors rather than aborting.
	 *
	 * @since 1.1.0
	 *
	 * @param string $code    Error code.
	 * @param string $message What to generate first.
	 *
	 * @return WP_Error
	 */
	protected function missing_prerequisite( string $code, string $message ): WP_Error {
		return new WP_Error( $code, $message );
	}
}
