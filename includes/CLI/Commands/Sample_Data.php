<?php
/**
 * `wp storeseeder sample-data`
 *
 * @since   1.1.0
 * @package StoreSeeder\CLI
 */

namespace StoreSeeder\CLI\Commands;

use StoreSeeder\CLI\Command;

defined( 'ABSPATH' ) || exit;

/**
 * Report and download the optional sample data.
 *
 * Consent is not bypassed here. The download only happens once an administrator has
 * accepted the prompt — the same record the admin page writes — because that prompt is the
 * only thing that grants permission, and a command that quietly fetched from GitHub would
 * make the disclosure in readme.txt untrue. What this adds is the ability to *act on* a
 * decision already made, which is what a deploy script needs.
 *
 * @since 1.1.0
 */
final class Sample_Data extends Command {
	const NAME = 'sample-data';

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public static function shortdesc(): string {
		return __( 'Report or sync the optional sample data.', 'storeseeder' );
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
				'name'        => 'action',
				'description' => __( 'status or sync.', 'storeseeder' ),
				'optional'    => true,
				'default'     => 'status',
				'options'     => array( 'status', 'sync' ),
			),
			array(
				'type'        => 'flag',
				'name'        => 'force',
				'description' => __( 'Re-download even when the files are already present.', 'storeseeder' ),
				'optional'    => true,
			),
		);
	}

	/**
	 * Run it.
	 *
	 * ## EXAMPLES
	 *
	 *     wp storeseeder sample-data --user=1
	 *     wp storeseeder sample-data sync --user=1
	 *     wp storeseeder sample-data sync --force --user=1
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

		$action = isset( $args[0] ) ? $args[0] : 'status';

		if ( 'sync' === $action ) {
			$this->sync( isset( $assoc_args['force'] ) );

			return;
		}

		$status = $this->dispatch( 'GET', '/download-sample' );

		if ( is_wp_error( $status ) ) {
			\WP_CLI::error( $status->get_error_message() );
			// Unreachable: WP_CLI::error() exits. Said explicitly so static
			// analysis knows the error paths do not fall through — without it
			// every line below reads as operating on a WP_Error.
			return;
		}

		foreach ( self::status_lines( $status ) as $line ) {
			\WP_CLI::line( $line );
		}
	}

	/**
	 * Download, or explain why it cannot.
	 *
	 * @since 1.1.0
	 *
	 * @param bool $force Whether to re-download files already present.
	 *
	 * @return void
	 */
	private function sync( bool $force ): void {
		$status = $this->dispatch( 'GET', '/download-sample' );

		if ( is_wp_error( $status ) ) {
			\WP_CLI::error( $status->get_error_message() );
			// Unreachable; see above.
			return;
		}

		// Refusing here rather than letting the request fail gives the actionable answer:
		// the prompt lives on the admin page, and nothing on the command line can stand in
		// for an administrator agreeing to an outbound request.
		if ( 'granted' !== ( isset( $status['consent'] ) ? $status['consent'] : null ) ) {
			\WP_CLI::error(
				__( 'Sample data has not been consented to on this site. An administrator has to accept the prompt on the StoreSeeder admin page first; generators work from built-in defaults until then.', 'storeseeder' )
			);
			// Unreachable; see above.
			return;
		}

		$result = $this->dispatch( 'POST', '/download-sample', array( 'force' => $force ) );

		if ( is_wp_error( $result ) ) {
			\WP_CLI::error( $result->get_error_message() );
			// Unreachable; see above.
			return;
		}

		\WP_CLI::success(
			isset( $result['message'] ) && is_string( $result['message'] )
				? $result['message']
				: __( 'Sample data synced.', 'storeseeder' )
		);
	}

	/**
	 * The status as lines of text.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $status The /download-sample payload.
	 *
	 * @return array<int, string>
	 */
	public static function status_lines( array $status ): array {
		$consent = isset( $status['consent'] ) ? $status['consent'] : null;

		if ( 'granted' === $consent ) {
			$consent_line = __( 'granted', 'storeseeder' );
		} elseif ( 'declined' === $consent ) {
			$consent_line = __( 'declined', 'storeseeder' );
		} else {
			$consent_line = __( 'never asked', 'storeseeder' );
		}

		$lines = array(
			sprintf(
				/* translators: %s: yes or no. */
				__( 'Downloaded:  %s', 'storeseeder' ),
				empty( $status['exists'] ) ? __( 'no', 'storeseeder' ) : __( 'yes', 'storeseeder' )
			),
			sprintf(
				/* translators: %s: granted, declined, or never asked. */
				__( 'Consent:     %s', 'storeseeder' ),
				$consent_line
			),
		);

		if ( ! empty( $status['last_synced'] ) ) {
			$lines[] = sprintf(
				/* translators: %s: ISO 8601 date. */
				__( 'Last synced: %s', 'storeseeder' ),
				(string) $status['last_synced']
			);
		}

		if ( ! empty( $status['repo_url'] ) ) {
			$lines[] = sprintf(
				/* translators: %s: repository URL. */
				__( 'Source:      %s', 'storeseeder' ),
				(string) $status['repo_url']
			);
		}

		return $lines;
	}
}
