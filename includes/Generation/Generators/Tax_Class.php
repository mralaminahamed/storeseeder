<?php
/**
 * Tax Class Generator Class for StoreSeeder Plugin
 *
 * @since      1.0.0
 * @subpackage Generators
 * @package    StoreSeeder\Generation\Generators
 */

namespace StoreSeeder\Generation\Generators;

use StoreSeeder\Generation\Generator;

defined( 'ABSPATH' ) || exit;

/**
 * Tax Class Generator Class
 *
 * Shapes a tax class together with the geographic rate rows that make it applicable.
 */
class Tax_Class extends Generator {


	/**
	 * Get the resource type name
	 *
	 * @return string Resource type name.
	 */
	protected function get_resource_type(): string {
		return 'tax_class';
	}

	/**
	 * Get supported data types for this generator.
	 *
	 * @return array Supported types
	 */
	public function get_supported_types(): array {
		return array(
			'tax_classes' => __( 'Tax Classes', 'storeseeder' ),
		);
	}

	/**
	 * Get generator description.
	 *
	 * @return string Description
	 */
	public function get_description(): string {
		return 'Generates tax classes with rates and rules for testing tax calculation functionality.';
	}

	/**
	 * Build a canonical tax class
	 *
	 * The rate rows come with it rather than being the writer's invention: a tax class
	 * with no rate is inert on every platform, and which regions it covers is generated
	 * data, not a platform detail.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, mixed>
	 */
	protected function build_entity() {
		$type    = $this->tax_type();
		$rate    = $this->rate_for( $type );
		$country = $this->country();
		$state   = $this->state( $country );

		// The primary row uses the class's own region and rate.
		$rates = array( $this->rate_row( $country, $state, $rate ) );

		// A tax class often spans several regions; add a few more.
		foreach ( range( 1, $this->get_faker()->numberBetween( 0, 2 ) ) as $extra ) {
			if ( 0 === $extra ) {
				continue;
			}

			$next = $this->country();

			$rates[] = $this->rate_row( $next, $this->state( $next ), $this->rate_for( $type ) );
		}

		return array(
			// Named for what it is. `tax_types` was declared on three surfaces and read by none, so
			// every class came out as two random words — "Corrupti Quia Tax" tells a reader nothing
			// about whether it is the reduced rate or the zero one.
			'name'    => $this->name( $type ),
			'type'    => $type,
			// A percentage, not a currency amount — so this one stays a float. The minor-unit
			// rule is about money, and 8.5% is not money.
			'rate'    => $rate,
			'country' => $country,
			'state'   => $state,
			'status'  => 'active',
			'rates'   => $rates,
		);
	}

	/**
	 * One rate row.
	 *
	 * `jurisdictions` decides how precise a row is: a country-level rate carries a country and
	 * nothing else, and a postcode-level one carries all four. It was declared and ignored, so every
	 * rate was country-and-state whatever was asked, and the city and postcode columns both platforms
	 * have went unused.
	 *
	 * @since 1.1.0
	 *
	 * @param string $country Two-letter country code.
	 * @param string $state   State code, or '' where the country has none.
	 * @param float  $rate    The percentage.
	 *
	 * @return array<string, mixed>
	 */
	private function rate_row( string $country, string $state, float $rate ): array {
		$levels = $this->jurisdictions();

		$row = array(
			'country'  => $country,
			'state'    => in_array( 'state', $levels, true ) ? $state : '',
			'city'     => in_array( 'city', $levels, true ) ? $this->get_faker()->city() : '',
			'postcode' => in_array( 'postcode', $levels, true ) ? $this->get_faker()->postcode() : '',
			'rate'     => $rate,
			// A compound rate stacks on top of the ones before it, which is how a state tax on top of
			// a federal one is modelled. Both platforms have the column; nothing set it.
			'compound' => $this->compound_allowed() && $this->get_faker()->boolean( 20 ),
			// A more precise rate has to win over a broader one, or the country-level row matches
			// first and the postcode row never applies.
			'priority' => count( array_intersect( $levels, array( 'state', 'city', 'postcode' ) ) ) + 1,
		);

		return $row;
	}

	/**
	 * Which class this is.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	private function tax_type(): string {
		$known     = array( 'standard', 'reduced', 'zero', 'exempt', 'digital' );
		$requested = array_values(
			array_intersect(
				array_filter( (array) ( $this->generation_params['tax_types'] ?? array() ), 'is_string' ),
				$known
			)
		);

		if ( array() === $requested ) {
			$requested = array( 'standard', 'reduced', 'zero' );
		}

		return (string) $this->get_faker()->randomElement( $requested );
	}

	/**
	 * The rate band for a class, as a percentage.
	 *
	 * @since 1.1.0
	 *
	 * @param string $type Tax class type.
	 *
	 * @return float
	 */
	private function rate_for( string $type ): float {
		// A zero-rated class is zero-rated and an exempt one is exempt. Drawing a percentage for
		// either produces a class whose name contradicts its rate.
		if ( in_array( $type, array( 'zero', 'exempt' ), true ) ) {
			return 0.0;
		}

		$ranges   = (array) ( $this->generation_params['rate_ranges'] ?? array() );
		$band     = (array) ( $ranges[ $type ] ?? array() );
		$defaults = array(
			'standard' => array( 5.0, 25.0 ),
			'reduced'  => array( 1.0, 10.0 ),
			'digital'  => array( 5.0, 25.0 ),
		);

		list( $floor, $cap ) = $defaults[ $type ] ?? $defaults['standard'];

		$min = isset( $band['min'] ) ? (float) $band['min'] : $floor;
		$max = isset( $band['max'] ) ? (float) $band['max'] : $cap;

		return $this->get_faker()->randomFloat( 2, min( $min, $max ), max( $min, $max ) );
	}

	/**
	 * The name a shopkeeper reads.
	 *
	 * @since 1.1.0
	 *
	 * @param string $type Tax class type.
	 *
	 * @return string
	 */
	private function name( string $type ): string {
		$names = array(
			'standard' => __( 'Standard Rate', 'storeseeder' ),
			'reduced'  => __( 'Reduced Rate', 'storeseeder' ),
			'zero'     => __( 'Zero Rate', 'storeseeder' ),
			'exempt'   => __( 'Tax Exempt', 'storeseeder' ),
			'digital'  => __( 'Digital Goods', 'storeseeder' ),
		);

		$name = $names[ $type ] ?? $names['standard'];

		// Suffixed with a region, because a store has one standard rate per jurisdiction and the
		// platform owns the unique name — two classes called "Standard Rate" would collide.
		return $name . ' (' . strtoupper( (string) $this->get_faker()->lexify( '???' ) ) . ')';
	}

	/**
	 * Which jurisdiction levels a rate row carries.
	 *
	 * @since 1.1.0
	 *
	 * @return array<int, string>
	 */
	private function jurisdictions(): array {
		// `county` was in the enum and has a column on neither platform, so it is dropped rather
		// than silently treated as a state.
		$known     = array( 'country', 'state', 'city', 'postcode' );
		$requested = array_values(
			array_intersect(
				array_filter( (array) ( $this->generation_params['jurisdictions'] ?? array() ), 'is_string' ),
				$known
			)
		);

		if ( array() === $requested ) {
			$requested = array( 'country', 'state' );
		}

		return $requested;
	}

	/**
	 * A country to write rates for.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	private function country(): string {
		$coverage  = (array) ( $this->generation_params['location_coverage'] ?? array() );
		$countries = array_values(
			array_filter( (array) ( $coverage['countries'] ?? array() ), 'is_string' )
		);

		if ( array() === $countries ) {
			return strtoupper( (string) $this->get_faker()->countryCode() );
		}

		return strtoupper( (string) $this->get_faker()->randomElement( $countries ) );
	}

	/**
	 * A state code for a country, where the country has states.
	 *
	 * @since 1.1.0
	 *
	 * @param string $country Two-letter country code.
	 *
	 * @return string
	 */
	private function state( string $country ): string {
		// Two random letters is what this used to be, which produces states like "QK" that match
		// nothing at checkout. Only the countries FakerPHP has real abbreviations for get one.
		if ( 'US' !== $country ) {
			return '';
		}

		return strtoupper( (string) $this->get_faker()->stateAbbr() );
	}

	/**
	 * Whether compound rates were asked for.
	 *
	 * @since 1.1.0
	 *
	 * @return bool
	 */
	private function compound_allowed(): bool {
		$coverage = (array) ( $this->generation_params['location_coverage'] ?? array() );

		if ( ! isset( $coverage['include_compound'] ) ) {
			return true;
		}

		return (bool) $coverage['include_compound'];
	}
}
