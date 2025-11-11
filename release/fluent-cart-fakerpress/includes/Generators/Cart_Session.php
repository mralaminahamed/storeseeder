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
			'id'             => $cart->id,
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
	private function generate_cart_session_data(): array {
		$stages = array( 'checkout', 'cart', 'completed' );
		$stage  = $this->get_faker()->randomElement( $stages );

		$user_id = $this->get_faker()->boolean( 70 ) ? $this->get_faker()->numberBetween( 1, 100 ) : null; // 70% logged in users

		$cart_items  = array();
		$items_count = $this->get_faker()->numberBetween( 1, 5 );

		for ( $i = 0; $i < $items_count; $i++ ) {
			$cart_items[] = array(
				'object_id'   => $this->get_faker()->numberBetween( 1, 1000 ),
				'object_type' => 'product_variation',
				'unit_price'  => $this->get_faker()->randomFloat( 2, 10, 500 ),
				'quantity'    => $this->get_faker()->numberBetween( 1, 3 ),
				'line_total'  => 0, // Will be calculated by Fluent Cart.
				'other_info'  => array(),
			);
		}

		$data = array(
			'customer_id' => $user_id ? $this->get_faker()->numberBetween( 1, 100 ) : null,
			'user_id'     => $user_id,
			'cart_data'   => $cart_items,
			'stage'       => $stage,
			'cart_group'  => 'default',
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
					'billing_state'     => $this->get_faker()->stateAbbr,
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
