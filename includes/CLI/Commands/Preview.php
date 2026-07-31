<?php
/**
 * `wp storeseeder preview <resource>`
 *
 * @since   1.1.0
 * @package StoreSeeder\CLI
 */

namespace StoreSeeder\CLI\Commands;

use StoreSeeder\CLI\Command;

defined( 'ABSPATH' ) || exit;

/**
 * Show what a run would create, without creating it.
 *
 * The same route the admin's live preview uses. It writes nothing and needs no target
 * platform, which makes it the safe way to check a set of flags before committing to a
 * hundred rows.
 *
 * @since 1.1.0
 */
final class Preview extends Command {
	const NAME = 'preview';

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public static function shortdesc(): string {
		return __( 'Preview rows a generator would create, without writing them.', 'storeseeder' );
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
				'description' => __( 'How many rows to preview.', 'storeseeder' ),
				'optional'    => true,
				'default'     => '5',
			),
			array(
				'type'        => 'assoc',
				'name'        => 'locale',
				'description' => __( 'Locale to preview in.', 'storeseeder' ),
				'optional'    => true,
			),
			array(
				'type'        => 'assoc',
				'name'        => 'format',
				'description' => __( 'Output format.', 'storeseeder' ),
				'optional'    => true,
				'default'     => 'table',
				'options'     => array( 'table', 'json', 'csv', 'yaml' ),
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
		);
	}

	/**
	 * Run it.
	 *
	 * ## EXAMPLES
	 *
	 *     wp storeseeder preview products --count=3 --user=1
	 *     wp storeseeder preview orders --locale=ja_JP --format=json --user=1
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
			$payload['count'] = 5;
		}

		$result = $this->dispatch( 'POST', '/' . $rest_base . '/preview', $payload );

		if ( is_wp_error( $result ) ) {
			\WP_CLI::error( $result->get_error_message() );
			// Unreachable; see above.
			return;
		}

		$rows = self::flatten_rows( $result );

		if ( array() === $rows ) {
			\WP_CLI::warning( __( 'The preview returned no rows.', 'storeseeder' ) );

			return;
		}

		\WP_CLI\Utils\format_items(
			isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table',
			$rows,
			array_keys( $rows[0] )
		);
	}

	/**
	 * Flatten a preview response into rows a table can print.
	 *
	 * The REST shape is `{ columns: [ { key, label } ], rows: [ { key: { v, kind } } ] }` —
	 * built for a React table, so each cell is an object carrying its own display hint.
	 * A terminal wants the value.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $result The response data.
	 *
	 * @return array<int, array<string, string>>
	 */
	public static function flatten_rows( array $result ): array {
		if ( ! isset( $result['rows'] ) || ! is_array( $result['rows'] ) ) {
			return array();
		}

		$labels = array();

		if ( isset( $result['columns'] ) && is_array( $result['columns'] ) ) {
			foreach ( $result['columns'] as $column ) {
				if ( is_array( $column ) && isset( $column['key'], $column['label'] ) ) {
					$labels[ (string) $column['key'] ] = (string) $column['label'];
				}
			}
		}

		$rows = array();

		foreach ( $result['rows'] as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$flat = array();

			foreach ( $row as $key => $cell ) {
				$label          = isset( $labels[ $key ] ) ? $labels[ $key ] : (string) $key;
				$value          = is_array( $cell ) && isset( $cell['v'] ) ? $cell['v'] : $cell;
				$flat[ $label ] = is_scalar( $value ) ? (string) $value : (string) wp_json_encode( $value );
			}

			$rows[] = $flat;
		}

		return $rows;
	}
}
