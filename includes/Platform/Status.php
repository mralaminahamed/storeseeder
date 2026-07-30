<?php
/**
 * Canonical status vocabulary
 *
 * Every platform spells its statuses differently — WooCommerce prefixes order statuses
 * with `wc-`, Fluent Cart does not, and each has its own set of edge states. Canonical
 * entities carry these names, and each writer maps them on the way out.
 *
 * The names are deliberately not any one platform's. Picking WooCommerce's spelling as
 * canonical would make three of the four drivers translate and one pass through, which
 * reads as though the pass-through platform were the "real" one.
 *
 * @since   1.1.0
 * @package StoreSeeder\Platform
 */

namespace StoreSeeder\Platform;

/**
 * Status name constants.
 *
 * @since 1.1.0
 */
final class Status {
	/**
	 * Order lifecycle.
	 */
	const PENDING    = 'pending';
	const PROCESSING = 'processing';
	const ON_HOLD    = 'on_hold';
	const COMPLETED  = 'completed';
	const CANCELLED  = 'cancelled';
	const FAILED     = 'failed';
	const REFUNDED   = 'refunded';

	/**
	 * Publication state, for products and other catalogue records.
	 */
	const PUBLISHED = 'published';
	const DRAFT     = 'draft';
	const PRIVATE   = 'private';

	/**
	 * Stock availability.
	 */
	const IN_STOCK     = 'in_stock';
	const OUT_OF_STOCK = 'out_of_stock';
	const ON_BACKORDER = 'on_backorder';

	/**
	 * Every order status.
	 *
	 * @since 1.1.0
	 *
	 * @return array<int, string>
	 */
	public static function order_statuses(): array {
		return array(
			self::PENDING,
			self::PROCESSING,
			self::ON_HOLD,
			self::COMPLETED,
			self::CANCELLED,
			self::FAILED,
			self::REFUNDED,
		);
	}

	/**
	 * Every publication state.
	 *
	 * @since 1.1.0
	 *
	 * @return array<int, string>
	 */
	public static function publication_statuses(): array {
		return array(
			self::PUBLISHED,
			self::DRAFT,
			self::PRIVATE,
		);
	}
}
