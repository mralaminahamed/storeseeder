<?php
/**
 * `wp storeseeder generate <resource>`
 *
 * @since   1.1.0
 * @package StoreSeeder\CLI
 */

namespace StoreSeeder\CLI\Commands;

use StoreSeeder\CLI\Command;

defined( 'ABSPATH' ) || exit;

/**
 * Generate test data from the command line.
 *
 * @since 1.1.0
 */
final class Generate extends Command {
	const NAME = 'generate';

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public static function shortdesc(): string {
		return __( 'Generate test data for one resource.', 'storeseeder' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function synopsis(): array {
		return array(
			array(
				'type'        => 'positional',
				'name'        => 'resource',
				'description' => __( 'Resource or REST base, e.g. products or cart_session.', 'storeseeder' ),
				'optional'    => false,
			),
			array(
				'type'        => 'assoc',
				'name'        => 'count',
				'description' => __( 'How many items to create, 1-100.', 'storeseeder' ),
				'optional'    => true,
				'default'     => '10',
			),
			array(
				'type'        => 'assoc',
				'name'        => 'locale',
				'description' => __( 'Locale to generate in, e.g. de_DE. Run `wp storeseeder locales` for the list.', 'storeseeder' ),
				'optional'    => true,
			),
			array(
				'type'        => 'assoc',
				'name'        => 'seed',
				'description' => __( 'Integer seed, for a reproducible run.', 'storeseeder' ),
				'optional'    => true,
			),
			array(
				'type'        => 'assoc',
				'name'        => 'platform',
				'description' => __( 'Platform id to write to, or auto. Defaults to the site-wide target.', 'storeseeder' ),
				'optional'    => true,
			),
			array(
				// WP-CLI rejects any flag the synopsis does not declare, which would put
				// every resource-specific parameter out of reach — the whole point of
				// reading the endpoint's own schema. `generic` lets them through; anything
				// the endpoint does not accept is then rejected by build_payload(), with a
				// message that lists what it does accept.
				'type'        => 'generic',
				'description' => __( 'Any other parameter the resource accepts, e.g. --product_type=digital.', 'storeseeder' ),
				'optional'    => true,
			),
			array(
				'type'        => 'flag',
				'name'        => 'porcelain',
				'description' => __( 'Output only the number of items created.', 'storeseeder' ),
				'optional'    => true,
			),
		);
	}

	/**
	 * Run it.
	 *
	 * ## OPTIONS
	 *
	 * Any parameter the resource's REST endpoint accepts can be passed as a flag. A list
	 * is written `--payment_methods=stripe,paypal`, and a nested object as JSON:
	 * `--price_range='{"min":5,"max":500}'`.
	 *
	 * ## EXAMPLES
	 *
	 *     wp storeseeder generate products --count=20 --locale=de_DE --user=1
	 *     wp storeseeder generate orders --count=100 --seed=42 --user=admin
	 *     wp storeseeder generate cart_session --count=5 --platform=fluent-cart --user=1
	 *
	 * @since 1.1.0
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Flags.
	 *
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ) {
		$this->require_access();

		$rest_base = self::resolve_resource( isset( $args[0] ) ? $args[0] : '' );

		if ( is_wp_error( $rest_base ) ) {
			\WP_CLI::error( $rest_base->get_error_message() );
			// Unreachable: WP_CLI::error() exits. Said explicitly so static
			// analysis knows the error paths do not fall through — without it
			// every line below reads as operating on a WP_Error.
			return;
		}

		$payload = self::build_payload( $rest_base, $assoc_args );

		if ( is_wp_error( $payload ) ) {
			\WP_CLI::error( $payload->get_error_message() );
			// Unreachable; see above.
			return;
		}

		if ( ! isset( $payload['count'] ) ) {
			$payload['count'] = 10;
		}

		$result = $this->dispatch( 'POST', '/' . $rest_base . '/generate', $payload );

		if ( is_wp_error( $result ) ) {
			// The REST layer's own message: it knows whether this was a validation
			// failure, an unsupported resource on this platform, or a missing target.
			\WP_CLI::error( $result->get_error_message() );
			// Unreachable; see above.
			return;
		}

		$created = $this->count_created( $result );

		if ( isset( $assoc_args['porcelain'] ) ) {
			\WP_CLI::line( (string) $created );

			return;
		}

		\WP_CLI::success(
			isset( $result['message'] ) && is_string( $result['message'] )
				? $result['message']
				: sprintf(
					/* translators: 1: number of items, 2: resource name. */
					__( 'Generated %1$d %2$s.', 'storeseeder' ),
					$created,
					$rest_base
				)
		);
	}

	/**
	 * How many items a generate response reports.
	 *
	 * The envelope is `{ message, <resource>: [ … ] }`, and the resource key differs per
	 * endpoint, so the count comes from the one array in the body rather than a fixed key.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $result The response data.
	 *
	 * @return int
	 */
	public static function count_created( array $result ): int {
		if ( isset( $result['generated'] ) && is_numeric( $result['generated'] ) ) {
			return (int) $result['generated'];
		}

		foreach ( $result as $key => $value ) {
			if ( 'message' !== $key && is_array( $value ) ) {
				return count( $value );
			}
		}

		return 0;
	}
}
