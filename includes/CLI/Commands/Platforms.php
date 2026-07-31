<?php
/**
 * `wp storeseeder platforms`
 *
 * @since   1.1.0
 * @package StoreSeeder\CLI
 */

namespace StoreSeeder\CLI\Commands;

use StoreSeeder\CLI\Command;

defined( 'ABSPATH' ) || exit;

/**
 * List the platforms this site could seed, and set the target.
 *
 * @since 1.1.0
 */
final class Platforms extends Command {
	const NAME = 'platforms';

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public static function shortdesc(): string {
		return __( 'List platforms, or set the site-wide target.', 'storeseeder' );
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
				'type'        => 'assoc',
				'name'        => 'set',
				'description' => __( 'Platform id to write to from now on, or auto.', 'storeseeder' ),
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
		);
	}

	/**
	 * Run it.
	 *
	 * ## EXAMPLES
	 *
	 *     wp storeseeder platforms --user=1
	 *     wp storeseeder platforms --set=fluent-cart --user=1
	 *     wp storeseeder platforms --set=auto --user=1
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

		if ( isset( $assoc_args['set'] ) ) {
			$result = $this->dispatch(
				'POST',
				'/platforms/target',
				array( 'platform' => $assoc_args['set'] )
			);

			if ( is_wp_error( $result ) ) {
				\WP_CLI::error( $result->get_error_message() );
				// Unreachable: WP_CLI::error() exits. Said explicitly so static
				// analysis knows the error paths do not fall through — without it
				// every line below reads as operating on a WP_Error.
				return;
			}

			\WP_CLI::success(
				sprintf(
					/* translators: %s: platform id, or "auto". */
					__( 'Target platform set to %s.', 'storeseeder' ),
					'' === $result['stored'] ? 'auto' : $result['stored']
				)
			);

			return;
		}

		$state = $this->dispatch( 'GET', '/platforms' );

		if ( is_wp_error( $state ) ) {
			\WP_CLI::error( $state->get_error_message() );
			// Unreachable; see above.
			return;
		}

		$rows = self::rows( $state );

		if ( array() === $rows ) {
			\WP_CLI::warning(
				__( 'No platform drivers are registered, so there is nowhere to write to.', 'storeseeder' )
			);

			return;
		}

		\WP_CLI\Utils\format_items(
			isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table',
			$rows,
			array( 'id', 'label', 'active', 'version', 'target', 'resources' )
		);

		// Said outside the table because it is the answer to "where will my next run go?",
		// which the rows only imply.
		if ( ! empty( $state['ambiguous'] ) ) {
			\WP_CLI::warning(
				__( 'More than one platform is active and none is chosen, so a run will refuse rather than guess. Set one with --set=<id>.', 'storeseeder' )
			);
		}
	}

	/**
	 * One row per platform, with the supported-resource count folded in.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $state The /platforms payload.
	 *
	 * @return array<int, array<string, string>>
	 */
	public static function rows( array $state ): array {
		if ( ! isset( $state['platforms'] ) || ! is_array( $state['platforms'] ) ) {
			return array();
		}

		$stored   = isset( $state['stored'] ) ? (string) $state['stored'] : '';
		$resolved = isset( $state['resolved'] ) ? (string) $state['resolved'] : '';
		$rows     = array();

		foreach ( $state['platforms'] as $platform ) {
			if ( ! is_array( $platform ) || ! isset( $platform['id'] ) ) {
				continue;
			}

			$id       = (string) $platform['id'];
			$supports = isset( $platform['supports'] ) && is_array( $platform['supports'] )
				? $platform['supports']
				: array();

			$supported = 0;

			foreach ( $supports as $capability ) {
				if ( is_array( $capability ) && ! empty( $capability['supported'] ) ) {
					++$supported;
				}
			}

			// Three states worth distinguishing: chosen explicitly, chosen by auto, or not
			// the target at all.
			if ( $stored === $id ) {
				$target = __( 'yes', 'storeseeder' );
			} elseif ( '' === $stored && $resolved === $id ) {
				$target = __( 'auto', 'storeseeder' );
			} else {
				$target = '';
			}

			$rows[] = array(
				'id'        => $id,
				'label'     => isset( $platform['label'] ) ? (string) $platform['label'] : $id,
				'active'    => empty( $platform['active'] ) ? __( 'no', 'storeseeder' ) : __( 'yes', 'storeseeder' ),
				'version'   => isset( $platform['version'] ) && null !== $platform['version']
					? (string) $platform['version']
					: '—',
				'target'    => $target,
				'resources' => sprintf( '%d/%d', $supported, count( $supports ) ),
			);
		}

		return $rows;
	}
}
