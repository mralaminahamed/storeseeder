<?php
/**
 * MCP Ability: Generate Cart Sessions
 *
 * @package StoreSeeder\MCP\Abilities
 * @since   2.1.0
 */

namespace StoreSeeder\MCP\Abilities;

use StoreSeeder\MCP\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Generate_Cart_Sessions
 *
 * Maps to REST endpoint: POST /storeseeder/v1/cart-sessions/generate
 *
 * @since 1.0.0
 */
class Generate_Cart_Sessions extends Ability {

	const REST_BASE = 'cart-sessions';

	/**
	 * {@inheritdoc}
	 */
	public static function label(): string {
		return __( 'Generate Cart Sessions', 'storeseeder' );
	}

	/**
	 * {@inheritdoc}
	 */
	public static function description(): string {
		return __( 'Generate realistic shopping cart sessions including pending, abandoned, completed, and cancelled states. Useful for testing abandoned-cart recovery workflows and marketing-automation integrations.', 'storeseeder' );
	}

	/**
	 * {@inheritdoc}
	 */
	protected static function input_properties(): array {
		return array(
			'abandonment_rate' => array(
				'type'        => 'integer',
				'description' => __( 'Percentage of carts left abandoned — reached checkout and stopped. The rest split between still-active and converted. Default: 30.', 'storeseeder' ),
				'minimum'     => 0,
				'maximum'     => 100,
				'default'     => 30,
			),
			'stage_weights'    => array(
				'type'        => 'object',
				'description' => __( 'Cart stage weights, which win over abandonment_rate where given.', 'storeseeder' ),
				'properties'  => array(
					'active'    => array( 'type' => 'integer' ),
					'abandoned' => array( 'type' => 'integer' ),
					'converted' => array( 'type' => 'integer' ),
				),
			),
			'guest_cart_ratio' => array(
				'type'        => 'integer',
				'description' => __( 'Percentage of carts belonging to a guest rather than an account (0–100). Default: 30.', 'storeseeder' ),
				'minimum'     => 0,
				'maximum'     => 100,
				'default'     => 30,
			),
			'items_per_cart'   => array(
				'type'        => 'object',
				'description' => __( 'Lines per cart, as a min and max. Default: 1 to 5.', 'storeseeder' ),
				'properties'  => array(
					'min' => array( 'type' => 'integer' ),
					'max' => array( 'type' => 'integer' ),
				),
			),
			'customer_id'      => array(
				'type'        => 'integer',
				'description' => __( 'Attach every cart to this customer.', 'storeseeder' ),
				'minimum'     => 1,
			),
		);
	}

	/**
	 * {@inheritdoc}
	 */
	protected static function output(): array {
		return array(
			'key'         => 'cart_sessions',
			'description' => __( 'Array of generated cart session objects with hash, user_id, status, items_count, total_amount, customer details, and timestamps.', 'storeseeder' ),
		);
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param array<string, mixed> $input Validated input from the MCP client.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function execute( array $input = array() ) {
		return static::dispatch( static::build_payload( $input ) );
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param array<string, mixed> $input Raw MCP input.
	 * @return array<string, mixed>
	 */
	protected static function build_payload( array $input ): array {
		$payload = array(
			'count'  => $input['count'] ?? 5,
			'locale' => $input['locale'] ?? 'en_US',
		);

		if ( isset( $input['seed'] ) ) {
			$payload['seed'] = (int) $input['seed'];
		}

		foreach ( array( 'abandonment_rate', 'guest_cart_ratio', 'customer_id' ) as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$payload[ $key ] = (int) $input[ $key ];
			}
		}

		if ( isset( $input['items_per_cart'] ) ) {
			$payload['items_per_cart'] = (array) $input['items_per_cart'];
		}

		// `stage_weights` for a client, `status_distribution` for the generator, which is the name
		// the admin form and the endpoint both use. The generator accepts the canonical stage names
		// as keys, so no translation is needed beyond the wrapper.
		if ( isset( $input['stage_weights'] ) ) {
			$payload['status_distribution'] = (array) $input['stage_weights'];
		}

		return $payload;
	}
}
