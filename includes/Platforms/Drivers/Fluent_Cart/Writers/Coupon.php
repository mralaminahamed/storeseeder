<?php
/**
 * Fluent Cart coupon writer
 *
 * @since   1.1.0
 * @package StoreSeeder\Platforms\Drivers\Fluent_Cart\Writers
 */

namespace StoreSeeder\Platforms\Drivers\Fluent_Cart\Writers;

use FluentCart\App\Models\Coupon as CouponModel;
use FluentCart\App\Models\ProductVariation as ProductVariationModel;
use StoreSeeder\Platforms\Writer;
use StoreSeeder\Platforms\Resource;
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
			'code'                 => $entity['code'],
			// A fixed discount is compared against the cart subtotal, which Fluent
			// Cart keeps in integer cents (DiscountService::calculateDiscountPercent),
			// so the canonical minor units go straight in. A percentage is a percent
			// on both sides.
			'discount'             => $entity['discount'],
			'type'                 => self::DISCOUNT_TYPE[ $entity['type'] ] ?? 'percentage',
			'description'          => $entity['description'],
			'usage_limit'          => $entity['usage_limit'],
			'usage_limit_per_user' => $entity['usage_limit_per_user'] ?? null,
			'minimum_amount'       => $entity['minimum_amount'] ?? null,
			'stackable'            => (bool) ( $entity['stackable'] ?? true ),
			'product_count'        => (int) ( $entity['product_count'] ?? 0 ),
			'status'               => $entity['status'],
			'starts_at'            => $entity['starts_at'] ?? null,
			'expires_at'           => $entity['expires_at'],
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

		return $this->filter_result( $result, $coupon_id, $coupon_data );
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
		// fct_coupons has no column for any of these — they live inside the conditions JSON,
		// which is where CanValidateCoupon and DiscountService both read them from. A top-level
		// key is silently dropped by mass-assignment, which is how every generated coupon came
		// out unlimited.
		$conditions = array(
			'max_uses' => $data['usage_limit'],
		);

		if ( null !== $data['usage_limit_per_user'] ) {
			$conditions['max_per_customer'] = (int) $data['usage_limit_per_user'];
		}

		if ( null !== $data['minimum_amount'] ) {
			// Cents. `DiscountService` compares `cartAmount / 100` against
			// `min_purchase_amount / 100`, and the admin form runs this field through the same
			// money helper as `amount` — which is cents for a fixed coupon. So the canonical
			// minor units go straight in; dollars here would make a $57 threshold read as $0.57.
			$conditions['min_purchase_amount'] = (int) $data['minimum_amount'];
			$conditions['min_amount_basis']    = 'subtotal';
		}

		$products = $this->restricted_product_ids( (int) $data['product_count'] );

		if ( array() !== $products ) {
			$conditions['included_products'] = $products;
		}

		$coupon_data = array(
			'title'            => $data['code'],
			'code'             => $data['code'],
			'status'           => $data['status'],
			'type'             => $data['type'],
			'amount'           => $data['discount'],
			'conditions'       => $conditions,
			'notes'            => $data['description'],
			// Stored as VARCHAR(3) and compared against the literals 'yes' and
			// 'no'. A boolean writes '1' or '', and '' matches neither — so
			// stackability came out contradictory depending on which check ran.
			'show_on_checkout' => 'yes',
			'stackable'        => $data['stackable'] ? 'yes' : 'no',
			'priority'         => 1,
			'use_count'        => 0,
			// Required whenever an end date is set, per Fluent Cart's own validation. A coupon
			// with no start date begins the moment it exists.
			'start_date'       => $data['starts_at'] ?? current_time( 'Y-m-d H:i:s' ),
			'end_date'         => $data['expires_at'],
		);

		// Create coupon using Fluent Cart Coupon model.
		$coupon = CouponModel::query()->create( $coupon_data );

		if ( ! $coupon instanceof CouponModel ) {
			return new WP_Error( 'coupon_creation_failed', __( 'Failed to create coupon using Fluent Cart model.', 'storeseeder' ) );
		}

		return $coupon->id;
	}

	/**
	 * Remove a generated coupon.
	 *
	 * Applied-coupon rows are left alone: they belong to the orders that used the coupon,
	 * and removing an order's history because a coupon was cleaned up would rewrite it.
	 *
	 * @since 1.1.0
	 *
	 * @param int|string $id The identifier reported when the row was created.
	 *
	 * @return true|WP_Error
	 */
	public function delete( $id ) {
		return $this->delete_model( CouponModel::class, $id );
	}

	/**
	 * Draw the variations a coupon is restricted to.
	 *
	 * Fluent Cart matches `included_products` against *variation* ids — both
	 * CanValidateCoupon and DiscountService compare it to an order item's `object_id` — where
	 * WooCommerce matches product ids. The entity says how many, each platform decides what
	 * counts as a product, which is the whole reason the count is canonical and the ids are not.
	 *
	 * @since 1.1.0
	 *
	 * @param int $count How many to restrict to.
	 *
	 * @return array<int, int>
	 */
	private function restricted_product_ids( int $count ): array {
		if ( $count < 1 || ! class_exists( ProductVariationModel::class ) ) {
			return array();
		}

		$variations = ProductVariationModel::query()
			->inRandomOrder()
			->limit( $count )
			->get();

		$ids = array();

		foreach ( $variations as $variation ) {
			$ids[] = (int) $variation->id;
		}

		return $ids;
	}
}
