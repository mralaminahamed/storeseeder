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
 * @package StoreSeeder\Platforms
 */

namespace StoreSeeder\Platforms;

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
	/**
	 * Licence states.
	 *
	 * A licence is not an order: it is issued, it may lapse, and it may be withdrawn. The
	 * three read differently to a support person looking at a record, so they are distinct
	 * rather than folded into the order vocabulary.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	const ACTIVE   = 'active';
	const EXPIRED  = 'expired';
	const DISABLED = 'disabled';

	/**
	 * Transaction states.
	 *
	 * `AUTHORIZED` and `DISPUTED` have no order-status counterpart, and the four that read like one
	 * are deliberately the same words: a completed transaction and a completed order mean the same
	 * thing to a reader, and two vocabularies for one idea is what this class exists to prevent.
	 * Fluent Cart spells completed `succeeded` and disputed `dispute_lost`; its writer maps them.
	 *
	 * @since 1.1.0
	 */
	const AUTHORIZED = 'authorized';
	const DISPUTED   = 'disputed';

	/**
	 * Transaction types.
	 *
	 * @since 1.1.0
	 */
	const CHARGE  = 'charge';
	const REFUND  = 'refund';
	const DISPUTE = 'dispute';

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
	 * Every transaction state.
	 *
	 * @since 1.1.0
	 *
	 * @return array<int, string>
	 */
	public static function transaction_statuses(): array {
		return array(
			self::PENDING,
			self::AUTHORIZED,
			self::COMPLETED,
			self::FAILED,
			self::REFUNDED,
			self::DISPUTED,
		);
	}

	/**
	 * Every transaction type.
	 *
	 * Charge, refund and dispute are the three every gateway models. A seeder has no business
	 * inventing others: the Refund generator filters on charge, so a transaction with an
	 * unrecognised type is invisible to it.
	 *
	 * @since 1.1.0
	 *
	 * @return array<int, string>
	 */
	public static function transaction_types(): array {
		return array(
			self::CHARGE,
			self::REFUND,
			self::DISPUTE,
		);
	}

	/**
	 * Every licence state.
	 *
	 * @since 1.1.0
	 *
	 * @return array<int, string>
	 */
	public static function license_statuses(): array {
		return array(
			self::ACTIVE,
			self::EXPIRED,
			self::DISABLED,
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
