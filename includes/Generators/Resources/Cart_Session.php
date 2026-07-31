<?php
/**
 * Cart Session Generator Class for StoreSeeder Plugin
 *
 * @since      1.0.0
 * @subpackage Generators
 * @package    StoreSeeder\Generators\Resources
 */

namespace StoreSeeder\Generators\Resources;

use StoreSeeder\Generators\Generator;

defined( 'ABSPATH' ) || exit;

/**
 * Cart Session Generator Class
 *
 * Shapes cart sessions: how many items, at what price and quantity, from which device.
 * Which products those items point at, and which customer owns the cart, are existing
 * rows the writer draws.
 */
class Cart_Session extends Generator {


	/**
	 * Get the resource type name
	 *
	 * @return string Resource type name.
	 */
	protected function get_resource_type(): string {
		return 'cart_session';
	}

	/**
	 * Get supported data types for this generator.
	 *
	 * @return array Supported types
	 */
	public function get_supported_types(): array {
		return array(
			'cart_sessions' => __( 'Cart Sessions', 'storeseeder' ),
		);
	}

	/**
	 * Get generator description.
	 *
	 * @return string Description
	 */
	public function get_description(): string {
		return 'Generates cart sessions with items, customer data, and abandonment tracking for testing cart functionality.';
	}

	/**
	 * Build a canonical cart session
	 *
	 * The item list is generated at full length and the writer pairs as many entries as
	 * it can find products for. A store with fewer products than the drawn item count
	 * therefore gets a shorter cart — the alternative, asking the platform how many
	 * products exist before shaping anything, would put a database read back into the
	 * generator.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, mixed>
	 */
	protected function build_entity() {
		// 'draft' is the resting state, 'intended' means the cart entered checkout,
		// 'completed' means it converted. Every platform models these three.
		$stages = array( 'draft', 'intended', 'completed' );
		$stage  = $this->get_faker()->randomElement( $stages );

		$logged_in = $this->get_faker()->boolean( 70 ); // 70% logged-in users.

		$items       = array();
		$items_count = $this->get_faker()->numberBetween( 1, 5 );

		for ( $i = 0; $i < $items_count; $i++ ) {
			$items[] = array(
				// Integer minor units, same as order items.
				'unit_price' => (int) round( $this->get_faker()->randomFloat( 2, 10, 500 ) * 100 ),
				'quantity'   => $this->get_faker()->numberBetween( 1, 3 ),
			);
		}

		$entity = array(
			'stage'      => $stage,
			'logged_in'  => $logged_in,
			'items'      => $items,
			'user_agent' => $this->get_faker()->userAgent(),
			'ip_address' => $this->get_faker()->ipv4(),
		);

		// A guest cart carries its own contact details, because there is no account
		// to read them from.
		if ( ! $logged_in ) {
			$entity['guest'] = array(
				'email'      => $this->get_faker()->email(),
				'first_name' => $this->get_faker()->firstName(),
				'last_name'  => $this->get_faker()->lastName(),
			);
		}

		// Only a cart that reached checkout has checkout data.
		if ( 'intended' === $stage ) {
			$entity['checkout'] = array(
				'full_name' => $this->get_faker()->name(),
				'email'     => $this->get_faker()->email(),
				'address_1' => $this->get_faker()->streetAddress(),
				'city'      => $this->get_faker()->city(),
				'state'     => $this->get_faker()->stateAbbr(),
				'postcode'  => $this->get_faker()->postcode(),
				'country'   => 'US',
			);
		}

		return $entity;
	}
}
