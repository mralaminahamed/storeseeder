<?php
/**
 * Coupon Generator Class for StoreSeeder Plugin
 *
 * @since      1.0.0
 * @subpackage Generators
 * @package    StoreSeeder\Generation\Generators
 */

namespace StoreSeeder\Generation\Generators;

use StoreSeeder\Generation\Generator;

defined( 'ABSPATH' ) || exit;

/**
 * Coupon Generator Class
 *
 * Shapes discount coupons. Persisting them is a platform writer's job.
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
			'coupons' => __( 'Coupons', 'storeseeder' ),
		);
	}

	/**
	 * Get generator description.
	 *
	 * @return string Description
	 */
	public function get_description(): string {
		return 'Generates discount coupons with various types and rules for testing promotional functionality.';
	}

	/**
	 * Build a canonical coupon
	 *
	 * `discount` carries whichever unit the type implies — integer minor units for a
	 * fixed amount, a whole percent for a percentage, zero for free shipping. That
	 * doubling-up is not a shortcut: every platform stores one amount column beside a
	 * type column, so splitting it here would only mean rejoining it in four writers.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, mixed>
	 */
	protected function build_entity() {
		// buy_x_get_y is omitted because it needs buy/get product lists.
		$types = array( 'percentage', 'fixed', 'free_shipping' );
		$type  = $this->get_faker()->randomElement( $types );

		$code = strtoupper( $this->get_faker()->bothify( 'SAVE####' ) );

		$discount = 0;
		if ( 'percentage' === $type ) {
			$discount = $this->get_faker()->numberBetween( 5, 50 );
		} elseif ( 'fixed' === $type ) {
			// Integer minor units. Storing major units here turns "$50 off" into
			// fifty cents off on any platform that compares against a cents subtotal.
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
	 * Preview columns for coupons
	 *
	 * @since 1.0.1
	 *
	 * @return array<int, array{key: string, label: string}>
	 */
	protected function get_preview_columns(): array {
		return array(
			array(
				'key'   => 'code',
				'label' => __( 'Code', 'storeseeder' ),
			),
			array(
				'key'   => 'type',
				'label' => __( 'Type', 'storeseeder' ),
			),
			array(
				'key'   => 'amount',
				'label' => __( 'Amount', 'storeseeder' ),
			),
			array(
				'key'   => 'limit',
				'label' => __( 'Usage limit', 'storeseeder' ),
			),
			array(
				'key'   => 'status',
				'label' => __( 'Status', 'storeseeder' ),
			),
		);
	}

	/**
	 * Build a coupon preview row
	 *
	 * @since 1.0.1
	 *
	 * @return array<string, array{v: mixed, kind: string}>
	 */
	protected function build_preview_row(): array {
		$faker      = $this->get_faker();
		$percentage = (bool) $faker->boolean();

		return array(
			'code'   => array(
				'v'    => strtoupper( $faker->lexify( '????' ) ) . $faker->numberBetween( 10, 99 ),
				'kind' => 'mono',
			),
			'type'   => array(
				'v'    => $percentage ? 'percentage' : 'fixed',
				'kind' => 'badge',
			),
			'amount' => array(
				'v'    => $percentage ? $faker->numberBetween( 5, 50 ) . '%' : '$' . number_format( $faker->randomFloat( 2, 5, 100 ), 2 ),
				'kind' => 'money',
			),
			'limit'  => array(
				'v'    => $faker->numberBetween( 1, 500 ),
				'kind' => 'num',
			),
			'status' => array(
				'v'    => $faker->randomElement( array( 'active', 'expired', 'scheduled' ) ),
				'kind' => 'status',
			),
		);
	}
}
