<?php
/**
 * MCP Ability: Generate Logs
 *
 * @package StoreSeeder\MCP\Abilities
 * @since   2.1.0
 */

namespace StoreSeeder\MCP\Abilities;

use StoreSeeder\MCP\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Generate_Logs
 *
 * Maps to REST endpoint: POST /storeseeder/v1/logs/generate
 *
 * @since 1.0.0
 */
class Generate_Logs extends Ability {

	const REST_BASE = 'logs';

	/**
	 * {@inheritdoc}
	 */
	public static function label(): string {
		return __( 'Generate Logs', 'storeseeder' );
	}

	/**
	 * {@inheritdoc}
	 */
	public static function description(): string {
		return __( 'Generate activity log entries for orders, products, customers, coupons, refunds, carts, transactions, and system events. Returns log IDs, object types, actions, and severity levels.', 'storeseeder' );
	}

	/**
	 * {@inheritdoc}
	 */
	protected static function input_properties(): array {
		return array(
			'log_types' => array(
				'type'        => 'array',
				'description' => __( 'Log severity types to generate. Default: all types.', 'storeseeder' ),
				'items'       => array(
					'type' => 'string',
					'enum' => array( 'info', 'success', 'warning', 'error' ),
				),
				'default'     => array( 'info', 'success', 'warning', 'error' ),
			),
		);
	}

	/**
	 * {@inheritdoc}
	 */
	protected static function output(): array {
		return array(
			'key'         => 'logs',
			'description' => __( 'Array of generated log objects with id, object, action, type, note, and is_public.', 'storeseeder' ),
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
			'count'  => $input['count'] ?? 10,
			'locale' => $input['locale'] ?? 'en_US',
		);

		if ( isset( $input['seed'] ) ) {
			$payload['seed'] = (int) $input['seed'];
		}

		if ( isset( $input['log_types'] ) ) {
			$payload['log_types'] = (array) $input['log_types'];
		}

		return $payload;
	}
}
