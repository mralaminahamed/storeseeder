<?php
/**
 * Tax Class Generator Class for StoreSeeder Plugin
 *
 * @since      1.0.0
 * @subpackage Generators
 * @package    StoreSeeder
 */

namespace StoreSeeder\Generators;

use StoreSeeder\Abstracts\Generator;

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
		$name = implode( ' ', (array) $this->get_faker()->words( 2, true ) ) . ' Tax';

		// A percentage, not a currency amount — so this one stays a float. The minor-unit
		// rule is about money, and 8.5% is not money.
		$rate    = $this->get_faker()->randomFloat( 2, 0, 25 );
		$country = $this->get_faker()->countryCode();
		$state   = strtoupper( $this->get_faker()->lexify( '??' ) );

		// The primary row uses the class's own region and rate.
		$rates = array(
			array(
				'country' => $country,
				'state'   => $state,
				'rate'    => $rate,
			),
		);

		// A tax class often spans several regions; add a few more.
		$extra = $this->get_faker()->numberBetween( 0, 2 );
		for ( $i = 0; $i < $extra; $i++ ) {
			$rates[] = array(
				'country' => $this->get_faker()->countryCode(),
				'state'   => strtoupper( $this->get_faker()->lexify( '??' ) ),
				'rate'    => $this->get_faker()->randomFloat( 2, 0, 25 ),
			);
		}

		return array(
			'name'    => $name,
			'rate'    => $rate,
			'country' => $country,
			'state'   => $state,
			'status'  => 'active',
			'rates'   => $rates,
		);
	}
}
