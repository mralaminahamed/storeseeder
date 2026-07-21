<?php
/**
 * Coupon Generator Class for StoreSeeder Plugin
 *
 * @since      1.0.0
 * @subpackage Generators
 * @package    StoreSeeder
 */

namespace StoreSeeder\Generators;

use FluentCart\App\Models\Coupon as CouponModel;
use StoreSeeder\Abstracts\Generator;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Coupon Generator Class
 *
 * Generates realistic fake coupon data for Fluent Cart testing and development.
 */
class Coupon extends Generator {


	/**
	 * Get the resource type name
	 *
	 * @return string Resource type name.
	 */
	protected function get_resource_type(): string {
		return 'coupon';
	}

	/**
	 * Get supported data types for this generator.
	 *
	 * @return array Supported types
	 */
	public function get_supported_types(): array {
		return array(
			'coupons' => 'Fluent Cart Coupons',
		);
	}

	/**
	 * Get generator description.
	 *
	 * @return string Description
	 */
	public function get_description(): string {
		return 'Generates discount coupons with various types and rules for testing Fluent Cart promotional functionality.';
	}

	/**
	 * Generate a single coupon
	 *
	 * @return WP_Error|array Single coupon data, error, or false on failure.
	 */
	protected function generate_single_item() {
		// Check if Fluent Cart is active.
		if ( ! defined( 'FLUENTCART_VERSION' ) ) {
			return new WP_Error( 'missing_fluent_cart', __( 'Fluent Cart plugin not found. Please ensure Fluent Cart is active.', 'storeseeder' ) );
		}

		$coupon_data = $this->generate_coupon_data();
		$coupon_id   = $this->create_coupon( $coupon_data );

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
	 * Generate coupon data
	 *
	 * @return array Coupon data
	 */
	private function generate_coupon_data(): array {
		// CouponRequest allows fixed, percentage, free_shipping and buy_x_get_y.
		// 'fixed_amount' is not one of them, and DiscountService branches on
		// `type === 'fixed'`, so a fixed_amount coupon applied no discount at
		// all. buy_x_get_y is omitted because it needs buy/get product lists.
		$types = array( 'percentage', 'fixed', 'free_shipping' );
		$type  = $this->get_faker()->randomElement( $types );

		$code = strtoupper( $this->get_faker()->bothify( 'SAVE####' ) );

		$discount = 0;
		if ( 'percentage' === $type ) {
			// A percentage, not a money amount — used directly as a percent.
			$discount = $this->get_faker()->numberBetween( 5, 50 );
		} elseif ( 'fixed' === $type ) {
			// Compared against the cart subtotal, which is in integer cents
			// (DiscountService::calculateDiscountPercent). Storing dollars
			// turns "$50 off" into 50 cents off.
			$discount = (int) round( $this->get_faker()->randomFloat( 2, 5, 100 ) * 100 );
		}

		return array(
			'code'        => $code,
			'discount'    => $discount,
			'type'        => $type,
			'description' => $this->get_faker()->sentence( 8 ),
			'usage_limit' => $this->get_faker()->numberBetween( 10, 1000 ),
			'status'      => 'active',
			'expires_at'  => $this->get_faker()->dateTimeBetween( '+1 week', '+6 months' )->format( 'Y-m-d H:i:s' ),
		);
	}

	/**
	 * Create coupon in Fluent Cart
	 *
	 * @param array $data Coupon data.
	 *
	 * @return int|WP_Error|null Created coupon ID or error
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
