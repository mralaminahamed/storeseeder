<?php
/**
 * Cart Session Generator Class for Fluent Cart FakerPress Plugin
 *
 * @since      1.0.0
 * @subpackage Generators
 * @package    FluentCartFakerPress
 */

namespace FluentCartFakerPress\Generators;

use FluentCart\App\Models\Cart as CartModel;
use FluentCartFakerPress\Abstracts\Generator;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Cart Session Generator Class
 *
 * Generates realistic fake cart session data for Fluent Cart testing and development.
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
			'cart_sessions' => 'Fluent Cart Cart Sessions',
		);
	}

	/**
	 * Get generator description.
	 *
	 * @return string Description
	 */
	public function get_description(): string {
		return 'Generates cart sessions with items, customer data, and abandonment tracking for testing Fluent Cart cart functionality.';
	}

	/**
	 * Generate a single cart session
	 *
	 * @return WP_Error|array Single cart session data, error, or false on failure.
	 */
	protected function generate_single_item() {
		// Check if Fluent Cart Cart model is available.
		if ( ! class_exists( CartModel::class ) ) {
			return new WP_Error( 'missing_model', __( 'Fluent Cart Cart model not found. Please ensure Fluent Cart plugin is active.', 'fluent-cart-fakerpress' ) );
		}

		$session_data = $this->generate_cart_session_data();
		$cart         = $this->create_cart_session( $session_data );

		if ( ! $cart ) {
			return new WP_Error( 'cart_session_creation_failed', __( 'Failed to create cart session.', 'fluent-cart-fakerpress' ) );
		}

		$result = array(
			// fct_carts has no id column — the primary key is cart_hash, and
			// the model sets $incrementing = false. $cart->id was always null.
			'id'             => $cart->cart_hash,
			'cart_hash'      => $cart->cart_hash,
			'user_id'        => $cart->user_id,
			'stage'          => $cart->stage,
			'items_count'    => count( $cart->cart_data ?? array() ),
			'customer_email' => $cart->email,
			'created_at'     => $cart->created_at,
		);

		/**
		 * Filters the cart session generation result data.
		 *
		 * Allows developers to modify the returned cart session data after generation.
		 *
		 * @since 1.0.0
		 * @hook  fluent_cart_fakerpress_cart_session_generation_result
		 *
		 * @param array $result         The cart session generation result data.
		 * @param int   $cart_id        The created cart ID.
		 * @param array $session_data   The original cart session data used for creation.
		 */
		return apply_filters( 'fluent_cart_fakerpress_cart_session_generation_result', $result, $cart->id, $session_data );
	}

	/**
	 * Generate cart session data
	 *
	 * @return array Cart session data
	 */
	/**
	 * Draw real product variation IDs for the cart contents.
	 *
	 * cart_data[].object_id is a foreign key into fct_product_variations.
	 * Random integers produce carts full of products that do not exist.
	 *
	 * @since 2.1.0
	 *
	 * @param int $count How many are wanted.
	 *
	 * @return array<int, int> Variation IDs, possibly fewer than requested.
	 */
	private function random_variation_ids( int $count ): array {
		$ids = $this->wpdb->get_col(
			$this->wpdb->prepare(
				"SELECT id FROM {$this->wpdb->prefix}fct_product_variations ORDER BY RAND() LIMIT %d",
				$count
			)
		);

		return array_map( 'intval', is_array( $ids ) ? $ids : array() );
	}

	/**
	 * Draw a real customer ID.
	 *
	 * @since 2.1.0
	 *
	 * @return int|null Customer ID, or null when the store has no customers.
	 */
	private function random_customer_id(): ?int {
		$customer_id = $this->wpdb->get_var(
			"SELECT id FROM {$this->wpdb->prefix}fct_customers ORDER BY RAND() LIMIT 1"
		);

		return null === $customer_id ? null : (int) $customer_id;
	}

	/**
	 * Draw a real WordPress user ID.
	 *
	 * @since 2.1.0
	 *
	 * @return int|null User ID, or null when no users match.
	 */
	private function random_user_id(): ?int {
		$user_id = $this->wpdb->get_var(
			"SELECT ID FROM {$this->wpdb->users} ORDER BY RAND() LIMIT 1"
		);

		return null === $user_id ? null : (int) $user_id;
	}

	private function generate_cart_session_data(): array {
		// Fluent Cart only ever writes 'draft' (the column default), 'intended'
		// and 'completed'. 'checkout' and 'cart' are not stages it recognises,
		// so those carts matched no abandoned-cart or recovery query.
		$stages = array( 'draft', 'intended', 'completed' );
		$stage  = $this->get_faker()->randomElement( $stages );

		$user_id = $this->get_faker()->boolean( 70 ) ? $this->random_user_id() : null; // 70% logged in users

		$cart_items  = array();
		$items_count = $this->get_faker()->numberBetween( 1, 5 );

		foreach ( $this->random_variation_ids( $items_count ) as $variation_id ) {
			$cart_items[] = array(
				'object_id'   => $variation_id,
				'object_type' => 'product_variation',
				// Cart money is in integer cents, same as order items.
				'unit_price'  => (int) round( $this->get_faker()->randomFloat( 2, 10, 500 ) * 100 ),
				'quantity'    => $this->get_faker()->numberBetween( 1, 3 ),
				'line_total'  => 0, // Will be calculated by Fluent Cart.
				'other_info'  => array(),
			);
		}

		$data = array(
			'customer_id' => $user_id ? $this->random_customer_id() : null,
			'user_id'     => $user_id,
			'cart_data'   => $cart_items,
			'stage'       => $stage,
			// 'global' is the column default Fluent Cart uses; 'default' is not
			// a group it ever reads.
			'cart_group'  => 'global',
			'user_agent'  => $this->get_faker()->userAgent(),
			'ip_address'  => $this->get_faker()->ipv4(),
		);

		// Add customer info if not logged in.
		if ( ! $user_id ) {
			$data['email']      = $this->get_faker()->email();
			$data['first_name'] = $this->get_faker()->firstName();
			$data['last_name']  = $this->get_faker()->lastName();
		}

		// Add checkout data for checkout stage.
		if ( 'checkout' === $stage ) {
			$data['checkout_data'] = array(
				'form_data' => array(
					'billing_full_name' => $this->get_faker()->name(),
					'billing_email'     => $this->get_faker()->email(),
					'billing_address_1' => $this->get_faker()->streetAddress(),
					'billing_city'      => $this->get_faker()->city(),
					'billing_state'     => $this->get_faker()->stateAbbr(),
					'billing_postcode'  => $this->get_faker()->postcode(),
					'billing_country'   => 'US',
				),
			);
		}

		return $data;
	}

	/**
	 * Create cart session in Fluent Cart
	 *
	 * @param array $data Cart session data.
	 *
	 * @return CartModel|null Created cart instance
	 */
	private function create_cart_session( array $data ): ?CartModel {
		try {
			$cart = CartModel::query()->create( $data );
			return $cart;
		} catch ( \Exception $e ) {
			return null;
		}
	}
}
