<?php
/**
 * MCP Ability: Generate Refunds
 *
 * @package FluentCartFakerPress\MCP\Abilities
 * @since   2.1.0
 */

namespace FluentCartFakerPress\MCP\Abilities;

use FluentCartFakerPress\Abstracts\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Generate_Refunds
 *
 * Maps to REST endpoint: POST /fluent-cart-fakerpress/v1/refunds/generate
 *
 * @since 2.1.0
 */
class Generate_Refunds extends Ability {

	const REST_BASE = 'refunds';

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
