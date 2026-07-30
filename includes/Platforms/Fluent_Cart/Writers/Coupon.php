<?php
/**
 * Fluent Cart coupon writer
 *
 * @since   1.1.0
 * @package StoreSeeder\Platforms\Fluent_Cart\Writers
 */

namespace StoreSeeder\Platforms\Fluent_Cart\Writers;

use FluentCart\App\Models\Coupon as CouponModel;
use StoreSeeder\Abstracts\Writer;
use StoreSeeder\Platform\Resource;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Persists a canonical coupon into Fluent Cart.
 *
 * @since 1.1.0
 */
final class Coupon extends Writer {
	/**
	 * Canonical discount type to Fluent Cart's spelling.
	 *
	 * CouponRequest allows fixed, percentage, free_shipping and buy_x_get_y.
	 * 'fixed_amount' is not one of them, and DiscountService branches on
	 * `type === 'fixed'`, so a fixed_amount coupon applied no discount at all.
	 *
	 * @since 1.1.0
	 * @var array<string, string>
	 */
	private const DISCOUNT_TYPE = array(
		'percentage'    => 'percentage',
		'fixed'         => 'fixed',
		'free_shipping' => 'free_shipping',
	);

	/**
	 * The resource this writer persists.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public function resource(): string {
		return Resource::COUPON;
	}

	/**
	 * Create a Fluent Cart coupon.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $entity Canonical coupon entity.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function write( array $entity ) {
		if ( ! defined( 'FLUENTCART_VERSION' ) ) {
			return new WP_Error( 'missing_fluent_cart', __( 'Fluent Cart plugin not found. Please ensure Fluent Cart is active.', 'storeseeder' ) );
		}

		$coupon_data = array(
			'code'        => $entity['code'],
			// A fixed discount is compared against the cart subtotal, which Fluent
			// Cart keeps in integer cents (DiscountService::calculateDiscountPercent),
			// so the canonical minor units go straight in. A percentage is a percent
			// on both sides.
			'discount'    => $entity['discount'],
			'type'        => self::DISCOUNT_TYPE[ $entity['type'] ] ?? 'percentage',
			'description' => $entity['description'],
			'usage_limit' => $entity['usage_limit'],
			'status'      => $entity['status'],
			'expires_at'  => $entity['expires_at'],
		);

		$coupon_id = $this->create_coupon( $coupon_data );

		if ( is_wp_error( $coupon_id ) ) {
			return $coupon_id;
		}

		if ( ! $coupon_id ) {
			return new WP_Error( 'coupon_creation_failed', __( 'Failed to create coupon.', 'storeseeder' ) );
		}

		$result = array(
			'id'          => $coupon_id,
			'code'        => $coupon_data['code'],
			'discount'    => $coupon_data['discount'],
			'type'        => $coupon_data['type'],
			'status'      => $coupon_data['status'],
			'usage_limit' => $coupon_data['usage_limit'],
			'usage_count' => 0,
			'created_at'  => current_time( 'Y-m-d H:i:s' ),
		);

		/**
		 * Filters the coupon generation result data.
		 *
		 * Allows developers to modify the returned coupon data after generation.
		 *
		 * @since 1.0.0
		 * @hook  storeseeder_coupon_generation_result
		 *
		 * @param array $result       The coupon generation result data.
		 * @param int   $coupon_id    The created coupon ID.
		 * @param array $coupon_data  The original coupon data used for creation.
		 */
		return apply_filters( 'storeseeder_coupon_generation_result', $result, $coupon_id, $coupon_data );
	}

	/**
	 * Create coupon in Fluent Cart.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $data Fluent-Cart-shaped coupon data.
	 *
	 * @return int|WP_Error|null Created coupon ID or error.
	 */
	private function create_coupon( array $data ) {
		// Check if Fluent Cart Coupon model is available.
		if ( ! class_exists( CouponModel::class ) ) {
			return new WP_Error( 'missing_model', __( 'Fluent Cart Coupon model not found. Please ensure Fluent Cart plugin is active.', 'storeseeder' ) );
		}

		// Prepare coupon data for Fluent Cart Coupon model.
		$coupon_data = array(
			'title'            => $data['code'],
			'code'             => $data['code'],
			'status'           => $data['status'],
			'type'             => $data['type'],
			'amount'           => $data['discount'],
			// fct_coupons has no max_uses column — the limit lives inside the
			// conditions JSON, which is where CanValidateCoupon and
			// DiscountService both read it from. The top-level key was silently
			// dropped by mass-assignment, leaving every coupon unlimited.
			'conditions'       => array(
				'max_uses' => $data['usage_limit'],
			),
			'notes'            => $data['description'],
			// Stored as VARCHAR(3) and compared against the literals 'yes' and
			// 'no'. A boolean writes '1' or '', and '' matches neither — so
			// stackability came out contradictory depending on which check ran.
			'show_on_checkout' => 'yes',
			'stackable'        => 'no',
			'priority'         => 1,
			'use_count'        => 0,
			'end_date'         => $data['expires_at'],
		);

		// Create coupon using Fluent Cart Coupon model.
		$coupon = CouponModel::query()->create( $coupon_data );

		if ( ! $coupon ) {
			return new WP_Error( 'coupon_creation_failed', __( 'Failed to create coupon using Fluent Cart model.', 'storeseeder' ) );
		}

		return $coupon->id;
	}
}
