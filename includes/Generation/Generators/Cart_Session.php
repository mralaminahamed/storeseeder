<?php
/**
 * Cart Session Generator Class for StoreSeeder Plugin
 *
 * @since      1.0.0
 * @subpackage Generators
 * @package    StoreSeeder\Generation\Generators
 */

namespace StoreSeeder\Generation\Generators;

use StoreSeeder\Generation\Generator;
use StoreSeeder\Platforms\Status;

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
		$stage     = $this->stage();
		$logged_in = $this->get_faker()->boolean( 100 - $this->guest_ratio() );

		$items = array();

		foreach ( range( 1, $this->items_per_cart() ) as $ignored ) {
			$items[] = array(
				// Integer minor units, same as order items. A fallback: the writer prices a cart
				// line from the variation it points at, the way the order writer does.
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
			// When the cart was started. Left to the platform, every abandoned cart in the store was
			// abandoned today — and the age of the cart is the whole input to a recovery report.
			'created_at' => gmdate(
				'Y-m-d H:i:s',
				time() - $this->get_faker()->numberBetween( 0, 30 ) * DAY_IN_SECONDS
			),
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

		// Only a cart that got as far as checkout has checkout data — the abandoned ones and the
		// converted ones, since a cart cannot convert without passing through it. A still-active
		// cart has none, and inventing an address for one makes every checkout funnel read the same
		// number twice.
		if ( Status::CART_ACTIVE !== $stage ) {
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

	/**
	 * Which stage this cart is at.
	 *
	 * `abandonment_rate` and `status_distribution` were both declared and read by nothing, so a run
	 * asking for a store full of abandoned carts got an even third of each stage. The rate is the
	 * simple form; the distribution is weights, and wins where it is given.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	private function stage(): string {
		$weights = $this->stage_weights();
		$total   = array_sum( $weights );

		if ( $total <= 0 ) {
			return Status::CART_ABANDONED;
		}

		$roll = $this->get_faker()->numberBetween( 1, $total );

		foreach ( $weights as $stage => $weight ) {
			$roll -= $weight;

			if ( $roll <= 0 ) {
				return (string) $stage;
			}
		}

		return Status::CART_ABANDONED;
	}

	/**
	 * How often each stage should come up.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, int>
	 */
	private function stage_weights(): array {
		$distribution = (array) ( $this->generation_params['status_distribution'] ?? array() );

		if ( array() !== $distribution ) {
			// `pending` and `completed` are the names the schema shipped with; the canonical stages
			// are accepted too, so a caller who read the vocabulary can use it.
			$weights = array(
				Status::CART_ACTIVE    => (int) ( $distribution['pending'] ?? ( $distribution[ Status::CART_ACTIVE ] ?? 0 ) ),
				Status::CART_ABANDONED => (int) ( $distribution['abandoned'] ?? 0 ),
				Status::CART_CONVERTED => (int) ( $distribution['completed'] ?? ( $distribution[ Status::CART_CONVERTED ] ?? 0 ) ),
			);

			if ( array_sum( $weights ) > 0 ) {
				return $weights;
			}
		}

		$abandoned = isset( $this->generation_params['abandonment_rate'] )
			? (int) $this->generation_params['abandonment_rate']
			: 30;

		$abandoned = max( 0, min( 100, $abandoned ) );

		// The rest splits between a cart still being filled and one that converted. Half each is
		// arbitrary, and stated rather than hidden: the parameter only speaks about abandonment.
		$remainder = 100 - $abandoned;

		return array(
			Status::CART_ACTIVE    => (int) floor( $remainder / 2 ),
			Status::CART_ABANDONED => $abandoned,
			Status::CART_CONVERTED => (int) ceil( $remainder / 2 ),
		);
	}

	/**
	 * How many carts belong to a guest, as a percentage.
	 *
	 * @since 1.1.0
	 *
	 * @return int
	 */
	private function guest_ratio(): int {
		// `customer_type: guest_only` is the older way of asking for the same thing.
		if ( 'guest_only' === ( $this->generation_params['customer_type'] ?? '' ) ) {
			return 100;
		}

		$ratio = isset( $this->generation_params['guest_cart_ratio'] )
			? (int) $this->generation_params['guest_cart_ratio']
			: 30;

		return max( 0, min( 100, $ratio ) );
	}

	/**
	 * How many lines this cart carries.
	 *
	 * @since 1.1.0
	 *
	 * @return int
	 */
	private function items_per_cart(): int {
		$range = (array) ( $this->generation_params['items_per_cart'] ?? array() );
		$min   = isset( $range['min'] ) ? (int) $range['min'] : 1;
		$max   = isset( $range['max'] ) ? (int) $range['max'] : 5;

		// A maximum below the minimum collapses to the minimum, the same way `items_per_order`
		// does. Widening to the pair's range instead would quietly give a caller who asked for
		// five items carts of two.
		if ( $max < $min ) {
			$max = $min;
		}

		return $this->get_faker()->numberBetween( max( 1, $min ), max( 1, $max ) );
	}
}
