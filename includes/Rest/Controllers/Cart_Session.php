<?php
/**
 * Cart Session REST Controller
 *
 * @since   1.0.0
 * @package StoreSeeder\Rest\Controllers
 */

namespace StoreSeeder\Rest\Controllers;

use StoreSeeder\Rest\Controller;
use StoreSeeder\Generation\Generators\Cart_Session as CartSessionGenerator;

/**
 * Cart Session REST Controller Class
 *
 * Handles REST API endpoints for cart session generation
 *
 * @since 1.0.0
 */
class Cart_Session extends Controller {


	/**
	 * Get resource type name
	 *
	 * @since 1.0.0
	 *
	 * @return string Resource type.
	 */
	protected function get_resource_type(): string {
		return 'cart_session';
	}

	/**
	 * Get resource type label for cart sessions
	 *
	 * @since 1.0.0
	 *
	 * @return string The translated label for cart session resource type.
	 */
	protected function get_resource_type_label(): string {
		return __( 'Cart Session', 'storeseeder' );
	}

	/**
	 * Get REST base for the endpoint
	 *
	 * @since 1.0.0
	 *
	 * @return string REST base.
	 */
	protected function get_rest_base(): string {
		return 'cart-sessions';
	}

	/**
	 * Get generator instance
	 *
	 * @since 1.0.0
	 *
	 * @return CartSessionGenerator Generator instance.
	 */
	protected function get_generator_instance(): CartSessionGenerator {
		return new CartSessionGenerator();
	}

	/**
	 * Get resource-specific generation parameters
	 *
	 * @since 1.0.0
	 *
	 * @return array Resource-specific parameters.
	 */
	protected function get_resource_specific_params(): array {
		return array(
			// `customer_type` used to enumerate existing / new / mixed / specific / guest_only, and
			// nothing read it. The new-versus-existing split no writer implemented is gone; what was
			// useful survives as `customer_id` and `guest_cart_ratio`, and `guest_only` is still
			// accepted as a way of asking for a ratio of 100.
			'customer_id'         => array(
				'description' => __( 'Attach every cart to this customer.', 'storeseeder' ),
				'type'        => 'integer',
				'minimum'     => 1,
			),
			'guest_cart_ratio'    => array(
				'description'       => __( 'Percentage of carts belonging to a guest rather than an account.', 'storeseeder' ),
				'type'              => 'integer',
				'minimum'           => 0,
				'maximum'           => 100,
				'default'           => 30,
				'sanitize_callback' => 'absint',
			),
			'abandonment_rate'    => array(
				'description'       => __( 'Percentage of carts left abandoned. The rest split between still-active and converted.', 'storeseeder' ),
				'type'              => 'integer',
				'minimum'           => 0,
				'maximum'           => 100,
				'default'           => 30,
				'sanitize_callback' => 'absint',
			),
			'status_distribution' => array(
				// `cancelled` was offered here and is not a cart stage anywhere: a cart nobody came
				// back to is abandoned, and one deliberately emptied leaves no row behind.
				'description' => __( 'Cart stage weights, which win over abandonment_rate where given.', 'storeseeder' ),
				'type'        => 'object',
				'properties'  => array(
					'pending'   => array(
						'description' => __( 'Weight for carts still being filled.', 'storeseeder' ),
						'type'        => 'integer',
						'minimum'     => 0,
						'maximum'     => 100,
					),
					'abandoned' => array(
						'description' => __( 'Weight for carts that reached checkout and stopped.', 'storeseeder' ),
						'type'        => 'integer',
						'minimum'     => 0,
						'maximum'     => 100,
					),
					'completed' => array(
						'description' => __( 'Weight for carts that converted to an order.', 'storeseeder' ),
						'type'        => 'integer',
						'minimum'     => 0,
						'maximum'     => 100,
					),
				),
			),
			'items_per_cart'      => array(
				'description' => __( 'Number of items per cart session.', 'storeseeder' ),
				'type'        => 'object',
				'properties'  => array(
					'min' => array(
						'description' => __( 'Minimum items per cart.', 'storeseeder' ),
						'type'        => 'integer',
						'minimum'     => 1,
						'default'     => 1,
					),
					'max' => array(
						'description' => __( 'Maximum items per cart.', 'storeseeder' ),
						'type'        => 'integer',
						'minimum'     => 1,
						'maximum'     => 15,
						'default'     => 5,
					),
				),
			),
		);
	}

	/**
	 * Get resource-specific schema properties
	 *
	 * @since 1.0.0
	 *
	 * @return array Resource-specific properties.
	 */
	protected function get_resource_specific_properties(): array {
		return array(
			'cart_sessions' => array(
				'description' => __( 'Generated cart sessions with items and customer data.', 'storeseeder' ),
				'type'        => 'array',
				'context'     => array( 'view' ),
				'readonly'    => true,
				'items'       => array(
					'type'       => 'object',
					'properties' => array(
						'hash'           => array(
							'type' => 'string',
						),
						'user_id'        => array(
							'type' => 'integer',
						),
						'status'         => array(
							'type' => 'string',
						),
						'items_count'    => array(
							'type' => 'integer',
						),
						'total_amount'   => array(
							'type' => 'number',
						),
						'customer_email' => array(
							'type' => 'string',
						),
						'customer_name'  => array(
							'type' => 'string',
						),
						'reminders'      => array(
							'type' => 'integer',
						),
						'created_at'     => array(
							'type' => 'string',
						),
						'updated_at'     => array(
							'type' => 'string',
						),
						'items'          => array(
							'type' => 'object',
						),
						'addresses'      => array(
							'type' => 'object',
						),
					),
				),
			),
		);
	}
}
