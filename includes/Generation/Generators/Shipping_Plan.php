<?php
/**
 * Shipping Plan Generator Class for StoreSeeder Plugin
 *
 * @since      1.0.0
 * @subpackage Generators
 * @package    StoreSeeder\Generation\Generators
 */

namespace StoreSeeder\Generation\Generators;

use StoreSeeder\Generation\Generator;

defined( 'ABSPATH' ) || exit;

/**
 * Shipping Plan Generator Class
 *
 * Shapes shipping methods with rates and coverage. Attaching them to a zone is the
 * platform writer's job.
 */
class Shipping_Plan extends Generator {



	/**
	 * Get the resource type name
	 *
	 * @return string Resource type name.
	 */
	protected function get_resource_type(): string {
		return 'shipping_plan';
	}

	/**
	 * Get supported data types for this generator.
	 *
	 * @return array Supported types
	 */
	public function get_supported_types(): array {
		return array(
			'shipping_plans' => __( 'Shipping Plans', 'storeseeder' ),
		);
	}

	/**
	 * Get generator description.
	 *
	 * @return string Description
	 */
	public function get_description(): string {
		return 'Generates shipping plans with different methods, rates, and coverage areas for testing shipping functionality.';
	}

	/**
	 * Build a canonical shipping plan
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, mixed>
	 */
	protected function build_entity() {
		$service = $this->service();
		$type    = 'free' === $service ? 'free_shipping' : 'flat_rate';
		$days    = $this->delivery_window( $service );

		// Integer minor units. Free shipping costs nothing, and drawing an amount for
		// it would both be meaningless and shift every later value in the sequence.
		$amount = 'free_shipping' === $type ? 0 : $this->amount();

		return array(
			// The name a shopper reads at checkout, which is where the service level lives: neither
			// platform has an "express" method *type*, and both have a flat rate that can be called
			// one. A plausible name and a delivery window is what the difference actually is.
			'title'        => $this->title( $service, $days ),
			'type'         => $type,
			'amount'       => $amount,
			'description'  => $this->get_faker()->sentence( 6 ),
			'enabled'      => $this->get_faker()->boolean( 85 ), // 85% chance of being enabled.
			// Which countries the zone covers. Empty means every region the zone covers, which is
			// what a worldwide method is.
			'regions'      => $this->regions(),
			// The delivery estimate, in days. Fluent Cart keeps one in its method settings;
			// WooCommerce's core flat rate has no field for it and reports it under `ignored`.
			'delivery_min' => $days[0],
			'delivery_max' => $days[1],
		);
	}

	/**
	 * Which service level this method offers.
	 *
	 * `shipping_types` was declared on three surfaces and read by none, so every generated method was
	 * a coin toss between a flat rate and free shipping. The enum offered `pickup`, `weight_based` and
	 * `flat_rate` alongside `standard` and `express`, which mixes three different ideas — a service
	 * level, a calculation method and a method type.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	private function service(): string {
		$known     = array( 'standard', 'express', 'overnight', 'free' );
		$requested = (array) ( $this->generation_params['shipping_types'] ?? array() );

		$normalised = array();

		foreach ( array_filter( $requested, 'is_string' ) as $value ) {
			// `flat_rate` is the type rather than a service, and a flat rate with no service level
			// is the standard one. Anything else is dropped.
			$value = 'flat_rate' === $value ? 'standard' : $value;

			if ( in_array( $value, $known, true ) ) {
				$normalised[] = $value;
			}
		}

		$normalised = array_values( array_unique( $normalised ) );

		if ( array() === $normalised ) {
			$normalised = array( 'standard', 'express', 'free' );
		}

		return (string) $this->get_faker()->randomElement( $normalised );
	}

	/**
	 * The delivery window for a service, in days.
	 *
	 * `delivery_timeframes` bounds it; the service narrows it, because an overnight method that
	 * quotes nine days is not a fixture anybody can read.
	 *
	 * @since 1.1.0
	 *
	 * @param string $service Service level.
	 *
	 * @return array{0: int, 1: int}
	 */
	private function delivery_window( string $service ): array {
		$frames = (array) ( $this->generation_params['delivery_timeframes'] ?? array() );
		$floor  = isset( $frames['min_days'] ) ? (int) $frames['min_days'] : 1;
		$cap    = isset( $frames['max_days'] ) ? (int) $frames['max_days'] : 14;

		if ( $cap < $floor ) {
			$cap = $floor;
		}

		$floor = max( 0, $floor );
		$cap   = max( $floor, $cap );

		if ( 'overnight' === $service ) {
			$min = $floor;
			$max = min( $cap, max( $floor, 1 ) );
		} elseif ( 'express' === $service ) {
			// The fast half of whatever range was allowed.
			$min = $floor;
			$max = max( $min, (int) floor( ( $floor + $cap ) / 2 ) );
		} else {
			$min = $this->get_faker()->numberBetween( $floor, $cap );
			$max = $this->get_faker()->numberBetween( $min, $cap );
		}

		return array( $min, $max );
	}

	/**
	 * What this method costs, in minor units.
	 *
	 * @since 1.1.0
	 *
	 * @return int
	 */
	private function amount(): int {
		$range = (array) ( $this->generation_params['cost_range'] ?? array() );
		$min   = isset( $range['min'] ) ? (float) $range['min'] : 5.0;
		$max   = isset( $range['max'] ) ? (float) $range['max'] : 50.0;

		return (int) round(
			$this->get_faker()->randomFloat( 2, max( 0.0, min( $min, $max ) ), max( 0.0, $min, $max ) ) * 100
		);
	}

	/**
	 * The countries this method's zone covers.
	 *
	 * `coverage_areas` was declared and ignored, so every zone covered everywhere. A worldwide zone is
	 * still the empty list, because that is how both platforms spell "no restriction".
	 *
	 * @since 1.1.0
	 *
	 * @return array<int, string>
	 */
	private function regions(): array {
		$known     = array( 'domestic', 'international', 'regional', 'worldwide' );
		$requested = array_values(
			array_intersect(
				array_filter( (array) ( $this->generation_params['coverage_areas'] ?? array() ), 'is_string' ),
				$known
			)
		);

		if ( array() === $requested ) {
			$requested = array( 'domestic', 'international' );
		}

		switch ( (string) $this->get_faker()->randomElement( $requested ) ) {
			case 'domestic':
				// The store's own country, which only the platform knows — so the writer resolves
				// the placeholder rather than the generator guessing at a locale.
				return array( 'store_country' );

			case 'regional':
				return (array) $this->get_faker()->randomElements( array( 'DE', 'FR', 'NL', 'BE', 'AT' ), 3 );

			case 'international':
				return (array) $this->get_faker()->randomElements( array( 'US', 'CA', 'GB', 'AU', 'JP', 'BR' ), 4 );

			default:
				return array();
		}
	}

	/**
	 * The name a shopper sees.
	 *
	 * @since 1.1.0
	 *
	 * @param string                $service Service level.
	 * @param array{0: int, 1: int} $days The delivery window.
	 *
	 * @return string
	 */
	private function title( string $service, array $days ): string {
		$names = array(
			'standard'  => __( 'Standard Shipping', 'storeseeder' ),
			'express'   => __( 'Express Shipping', 'storeseeder' ),
			'overnight' => __( 'Overnight Shipping', 'storeseeder' ),
			'free'      => __( 'Free Shipping', 'storeseeder' ),
		);

		$name = $names[ $service ] ?? $names['standard'];

		if ( $days[0] === $days[1] ) {
			return sprintf(
				/* translators: 1: method name, 2: number of days. */
				_n( '%1$s (%2$d day)', '%1$s (%2$d days)', $days[1], 'storeseeder' ),
				$name,
				$days[1]
			);
		}

		return sprintf(
			/* translators: 1: method name, 2: minimum days, 3: maximum days. */
			__( '%1$s (%2$d–%3$d days)', 'storeseeder' ),
			$name,
			$days[0],
			$days[1]
		);
	}
}
