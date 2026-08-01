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
		$type      = $this->type();
		$days      = $this->validity_days();
		$starts_in = $this->get_faker()->boolean( 20 ) ? $this->get_faker()->numberBetween( 1, 14 ) : 0;
		$limits    = (array) ( $this->generation_params['usage_limits'] ?? array() );
		$limited   = ! isset( $limits['set_usage_limits'] ) || (bool) $limits['set_usage_limits'];
		// Drawn once, because the per-customer cap is compared against this exact number — drawing
		// again would cap against a limit the coupon does not have.
		$uses = $limited ? $this->max_uses( $limits ) : null;

		return array(
			'code'                 => strtoupper( $this->get_faker()->bothify( 'SAVE####' ) ),
			'discount'             => $this->discount( $type ),
			'type'                 => $type,
			'description'          => $this->get_faker()->sentence( 8 ),
			// Null is an unlimited coupon, which is a different fixture from one limited to a
			// large number: the validation path that rejects an exhausted coupon never runs.
			'usage_limit'          => $uses,
			'usage_limit_per_user' => null === $uses ? null : $this->max_uses_per_user( $limits, $uses ),
			'status'               => 'active',
			// A coupon that has not started yet is the case a checkout test needs and no fixture
			// had. Null is one valid from the moment it exists.
			'starts_at'            => $starts_in > 0
				? gmdate( 'Y-m-d H:i:s', time() + $starts_in * DAY_IN_SECONDS )
				: null,
			'expires_at'           => gmdate( 'Y-m-d H:i:s', time() + ( $starts_in + $days ) * DAY_IN_SECONDS ),
			// Cart thresholds in minor units, like every other amount. Null is no threshold at
			// all rather than a threshold of zero, which reads as "any cart" and is the same
			// thing only until somebody sorts on the column.
			'minimum_amount'       => $this->restriction( 'minimum_spend', true )
				? (int) round( $this->get_faker()->numberBetween( 20, 200 ) ) * 100
				: null,
			'maximum_amount'       => $this->restriction( 'maximum_spend', false )
				? (int) round( $this->get_faker()->numberBetween( 300, 1000 ) ) * 100
				: null,
			'exclude_sale_items'   => $this->restriction( 'exclude_sale_items', false ),
			// Most coupons combine; some do not. A store where every coupon stacks cannot be used
			// to test the case where one refuses to.
			'stackable'            => $this->get_faker()->boolean( 70 ),
			// How many products to restrict the coupon to. Which products is the writer's
			// business, since only the platform knows what exists.
			'product_count'        => $this->restriction( 'product_restrictions', true )
				? $this->get_faker()->numberBetween( 1, 3 )
				: 0,
		);
	}

	/**
	 * The discount type for this coupon.
	 *
	 * `discount_types` was declared on all three surfaces and read by none, so asking for
	 * percentage coupons got a spread across three types. The two lists also disagreed on the
	 * spelling of a fixed discount and offered `buy_x_get_y` and `products`, neither of which
	 * anything generates.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	private function type(): string {
		$known     = array( 'percentage', 'fixed', 'free_shipping' );
		$requested = (array) ( $this->generation_params['discount_types'] ?? array() );
		$allowed   = array_values( array_intersect( array_filter( $requested, 'is_string' ), $known ) );

		if ( array() === $allowed ) {
			$allowed = $known;
		}

		return (string) $this->get_faker()->randomElement( $allowed );
	}

	/**
	 * What this coupon takes off, in percent or in minor units.
	 *
	 * @since 1.1.0
	 *
	 * @param string $type Discount type.
	 *
	 * @return int
	 */
	private function discount( string $type ): int {
		$range = (array) ( $this->generation_params['discount_range'] ?? array() );

		if ( 'percentage' === $type ) {
			$min = isset( $range['min_percentage'] ) ? (int) $range['min_percentage'] : 5;
			$max = isset( $range['max_percentage'] ) ? (int) $range['max_percentage'] : 50;

			return $this->get_faker()->numberBetween( min( $min, $max ), max( $min, $max ) );
		}

		if ( 'fixed' === $type ) {
			$min = isset( $range['min_fixed'] ) ? (float) $range['min_fixed'] : 5.0;
			$max = isset( $range['max_fixed'] ) ? (float) $range['max_fixed'] : 100.0;

			// Integer minor units. Storing major units here turns "$50 off" into fifty cents off
			// on any platform that compares against a cents subtotal.
			return (int) round( $this->get_faker()->randomFloat( 2, min( $min, $max ), max( $min, $max ) ) * 100 );
		}

		// Free shipping carries no amount.
		return 0;
	}

	/**
	 * How long this coupon is valid for, in days.
	 *
	 * @since 1.1.0
	 *
	 * @return int
	 */
	private function validity_days(): int {
		$period = (array) ( $this->generation_params['validity_period'] ?? array() );
		$min    = isset( $period['min_days'] ) ? (int) $period['min_days'] : 7;
		$max    = isset( $period['max_days'] ) ? (int) $period['max_days'] : 180;

		return $this->get_faker()->numberBetween( max( 1, min( $min, $max ) ), max( 1, $min, $max ) );
	}

	/**
	 * The total use limit.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $limits The `usage_limits` parameter.
	 *
	 * @return int
	 */
	private function max_uses( array $limits ): int {
		$max = isset( $limits['max_uses'] ) ? (int) $limits['max_uses'] : 100;

		return $this->get_faker()->numberBetween( max( 1, (int) ceil( $max / 10 ) ), max( 1, $max ) );
	}

	/**
	 * The per-customer use limit, which can never exceed the total.
	 *
	 * Fluent Cart rejects a coupon whose per-customer limit is above its total limit, and it is a
	 * nonsense either way — so the generator does not produce one.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $limits The `usage_limits` parameter.
	 * @param int                  $uses   The total limit this coupon carries.
	 *
	 * @return int
	 */
	private function max_uses_per_user( array $limits, int $uses ): int {
		$per_user = isset( $limits['max_uses_per_user'] ) ? (int) $limits['max_uses_per_user'] : 1;

		return max( 1, min( $per_user, $uses ) );
	}

	/**
	 * Read one `restrictions` switch.
	 *
	 * @since 1.1.0
	 *
	 * @param string $name     Switch name.
	 * @param bool   $fallback Value when the caller sent none.
	 *
	 * @return bool
	 */
	private function restriction( string $name, bool $fallback ): bool {
		$restrictions = (array) ( $this->generation_params['restrictions'] ?? array() );

		if ( ! isset( $restrictions[ $name ] ) ) {
			return $fallback;
		}

		return (bool) $restrictions[ $name ];
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
		$faker = $this->get_faker();

		// The generator's own helpers, not a second set of literals. This row invented its type
		// from a coin toss, its percentage from 5–50 and its cash amount from 5–100, so a caller
		// who narrowed `discount_range` — or picked a recipe that did — watched the preview ignore
		// them and reasonably concluded the parameter was decoration.
		$type     = $this->type();
		$discount = $this->discount( $type );
		$limits   = (array) ( $this->generation_params['usage_limits'] ?? array() );

		return array(
			'code'   => array(
				'v'    => strtoupper( $faker->lexify( '????' ) ) . $faker->numberBetween( 10, 99 ),
				'kind' => 'mono',
			),
			'type'   => array(
				'v'    => $type,
				'kind' => 'badge',
			),
			'amount' => array(
				// `discount()` answers a whole percentage for one type and minor units for the
				// other, so the two are formatted apart rather than divided the same way.
				'v'    => 'percentage' === $type
					? $discount . '%'
					: '$' . number_format( $discount / 100, 2 ),
				'kind' => 'money',
			),
			'limit'  => array(
				'v'    => $this->max_uses( $limits ),
				'kind' => 'num',
			),
			'status' => array(
				'v'    => $faker->randomElement( array( 'active', 'expired', 'scheduled' ) ),
				'kind' => 'status',
			),
		);
	}
}
