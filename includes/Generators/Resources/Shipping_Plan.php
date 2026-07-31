<?php
/**
 * Shipping Plan Generator Class for StoreSeeder Plugin
 *
 * @since      1.0.0
 * @subpackage Generators
 * @package    StoreSeeder\Generators\Resources
 */

namespace StoreSeeder\Generators\Resources;

use StoreSeeder\Generators\Generator;

defined( 'ABSPATH' ) || exit;

/**
 * Shipping Plan Generator Class
 *
 * Shapes shipping methods with rates and coverage. Attaching them to a zone is the
 * platform writer's job.
 */
class Shipping_Plan extends Generator {


	/**
	 * Shipping method types, in canonical spelling.
	 *
	 * Both are near-universal concepts. Each platform names them its own way — Fluent
	 * Cart calls flat rate 'fixed' — so its writer translates.
	 *
	 * @var string[]
	 */
	private const TYPES = array( 'flat_rate', 'free_shipping' );

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
		$type = $this->get_faker()->randomElement( self::TYPES );

		// Integer minor units. Free shipping costs nothing, and drawing an amount for
		// it would both be meaningless and shift every later value in the sequence.
		$amount = 0;
		if ( 'free_shipping' !== $type ) {
			$amount = (int) round( $this->get_faker()->randomFloat( 2, 5, 50 ) * 100 );
		}

		return array(
			'title'       => implode( ' ', (array) $this->get_faker()->words( 3, true ) ) . ' Shipping',
			'type'        => $type,
			'amount'      => $amount,
			'description' => $this->get_faker()->sentence( 6 ),
			'enabled'     => $this->get_faker()->boolean( 85 ), // 85% chance of being enabled.
			// Empty means every region the zone covers.
			'regions'     => array(),
		);
	}
}
