<?php
/**
 * MCP Ability: Generate Attributes
 *
 * @package StoreSeeder\MCP\Abilities
 * @since   2.1.0
 */

namespace StoreSeeder\MCP\Abilities;

use StoreSeeder\MCP\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Generate_Attributes
 *
 * Maps to REST endpoint: POST /storeseeder/v1/attributes/generate
 *
 * @since 1.0.0
 */
class Generate_Attributes extends Ability {

	const REST_BASE = 'attributes';

	/**
	 * {@inheritdoc}
	 */
	public static function label(): string {
		return __( 'Generate Attributes', 'storeseeder' );
	}

	/**
	 * {@inheritdoc}
	 */
	public static function description(): string {
		return __( 'Generate Fluent Cart product attributes (Color, Size, Material, etc.) with option values. Returns an array of created attribute IDs, names, types, and values.', 'storeseeder' );
	}

	/**
	 * {@inheritdoc}
	 */
	protected static function output(): array {
		return array(
			'key'         => 'attributes',
			'description' => __( 'Array of generated attribute objects with id, name, type, slug, and values array.', 'storeseeder' ),
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

		return $payload;
	}
}
