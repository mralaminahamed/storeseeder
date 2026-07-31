<?php
/**
 * `wp storeseeder cleanup`
 *
 * @since   1.1.0
 * @package StoreSeeder\CLI
 */

namespace StoreSeeder\CLI\Commands;

use StoreSeeder\CLI\Command;

defined( 'ABSPATH' ) || exit;

/**
 * Report or delete the data StoreSeeder generated.
 *
 * Deletes only rows the plugin recorded creating. It never looks for rows that resemble test
 * data, because on a staging site restored from production that guess eventually takes out
 * something real.
 *
 * The command is the reason the batching in the REST layer matters: a fixture script that
 * generates and clears repeatedly wants one call that finishes the job, so this loops until
 * nothing is left rather than making the caller do it.
 *
 * @since 1.1.0
 */
final class Cleanup extends Command {
	const NAME = 'cleanup';

	/**
	 * Loop guard.
	 *
	 * A round that deletes nothing while rows remain means every remaining row is failing —
	 * a deactivated plugin, most likely. Without this the loop would spin on it for ever.
	 *
	 * @since 1.1.0
	 * @var int
	 */
	const MAX_ROUNDS = 500;

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public static function shortdesc(): string {
		return __( 'Report or delete the data StoreSeeder generated.', 'storeseeder' );
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
				'description' => __( 'status, delete, or forget.', 'storeseeder' ),
				'optional'    => true,
				'default'     => 'status',
				'options'     => array( 'status', 'delete', 'forget' ),
			),
			array(
				'type'        => 'assoc',
				'name'        => 'resource',
				'description' => __( 'Limit to one canonical resource, e.g. product or cart_session.', 'storeseeder' ),
				'optional'    => true,
			),
			array(
				'type'        => 'assoc',
				'name'        => 'limit',
				'description' => __( 'Rows per batch (1–500). The command loops until nothing is left.', 'storeseeder' ),
				'optional'    => true,
			),
			array(
				'type'        => 'flag',
				'name'        => 'yes',
				'description' => __( 'Skip the confirmation prompt.', 'storeseeder' ),
				'optional'    => true,
			),
		);
	}

	/**
	 * Run it.
	 *
	 * ## EXAMPLES
	 *
	 *     wp storeseeder cleanup --user=1
	 *     wp storeseeder cleanup delete --user=1
	 *     wp storeseeder cleanup delete --resource=product --user=1
	 *     wp storeseeder cleanup forget --yes --user=1
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

		$status = $this->dispatch( 'GET', '/generated' );

		if ( is_wp_error( $status ) ) {
			\WP_CLI::error( $status->get_error_message() );
			// Unreachable: WP_CLI::error() exits. Said explicitly so static analysis does
			// not read every line below as operating on a WP_Error.
			return;
		}

		if ( 'status' === $action ) {
			$this->report( $status );

			return;
		}

		$total = isset( $status['total'] ) ? (int) $status['total'] : 0;

		if ( 0 === $total ) {
			\WP_CLI::success( __( 'Nothing recorded — StoreSeeder has generated no data on this site.', 'storeseeder' ) );

			return;
		}

		if ( 'forget' === $action ) {
			$this->forget( $total, isset( $assoc_args['yes'] ) );

			return;
		}

		$this->delete(
			isset( $assoc_args['resource'] ) ? (string) $assoc_args['resource'] : '',
			isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : 100,
			isset( $assoc_args['yes'] )
		);
	}

	/**
	 * Print what is recorded.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $status The /generated payload.
	 *
	 * @return void
	 */
	private function report( array $status ): void {
		$rows = self::rows( $status );

		if ( array() === $rows ) {
			\WP_CLI::line( __( 'Nothing recorded — StoreSeeder has generated no data on this site.', 'storeseeder' ) );

			return;
		}

		\WP_CLI\Utils\format_items( 'table', $rows, array( 'resource', 'rows' ) );

		\WP_CLI::line(
			sprintf(
				/* translators: %d: number of recorded rows. */
				__( '%d rows recorded in total.', 'storeseeder' ),
				isset( $status['total'] ) ? (int) $status['total'] : 0
			)
		);
	}

	/**
	 * Delete in batches until nothing is left.
	 *
	 * @since 1.1.0
	 *
	 * @param string $only    Canonical resource, or '' for everything.
	 * @param int    $limit   Rows per batch.
	 * @param bool   $assumed Whether --yes was passed.
	 *
	 * @return void
	 */
	private function delete( string $only, int $limit, bool $assumed ): void {
		if ( ! $assumed ) {
			\WP_CLI::confirm(
				'' === $only
					? __( 'Permanently delete every row StoreSeeder generated on this site?', 'storeseeder' )
					: sprintf(
						/* translators: %s: canonical resource name. */
						__( 'Permanently delete every generated %s row on this site?', 'storeseeder' ),
						$only
					)
			);
		}

		$deleted  = 0;
		$reported = array();

		for ( $round = 0; $round < self::MAX_ROUNDS; $round++ ) {
			$result = $this->dispatch(
				'DELETE',
				'/generated',
				array(
					'resource' => $only,
					'limit'    => $limit,
				)
			);

			if ( is_wp_error( $result ) ) {
				\WP_CLI::error( $result->get_error_message() );
				// Unreachable; see above.
				return;
			}

			$deleted += isset( $result['deleted'] ) ? (int) $result['deleted'] : 0;

			foreach ( (array) ( $result['errors'] ?? array() ) as $error ) {
				if ( is_string( $error ) && ! in_array( $error, $reported, true ) ) {
					$reported[] = $error;
					\WP_CLI::warning( $error );
				}
			}

			$remaining = isset( $result['remaining'] ) ? (int) $result['remaining'] : 0;

			if ( 0 === $remaining ) {
				break;
			}

			// Nothing deleted while rows remain: every one of them is failing, and another
			// round would fail identically.
			if ( empty( $result['deleted'] ) ) {
				\WP_CLI::warning(
					sprintf(
						/* translators: %d: number of rows left recorded. */
						__( '%d recorded rows could not be deleted and are still listed. Run `wp storeseeder cleanup forget` to drop the records if the rows are already gone.', 'storeseeder' ),
						$remaining
					)
				);
				break;
			}

			\WP_CLI::log(
				sprintf(
					/* translators: 1: rows deleted so far, 2: rows left. */
					__( 'Deleted %1$d, %2$d to go…', 'storeseeder' ),
					$deleted,
					$remaining
				)
			);
		}

		\WP_CLI::success(
			sprintf(
				/* translators: %d: number of rows deleted. */
				_n( 'Deleted %d generated row.', 'Deleted %d generated rows.', $deleted, 'storeseeder' ),
				$deleted
			)
		);
	}

	/**
	 * Drop the records without touching the store.
	 *
	 * @since 1.1.0
	 *
	 * @param int  $total   How many records there are.
	 * @param bool $assumed Whether --yes was passed.
	 *
	 * @return void
	 */
	private function forget( int $total, bool $assumed ): void {
		if ( ! $assumed ) {
			\WP_CLI::confirm(
				sprintf(
					/* translators: %d: number of records. */
					__( 'Forget %d records without deleting the rows they point at? The data stays in the store and StoreSeeder will no longer offer to remove it.', 'storeseeder' ),
					$total
				)
			);
		}

		$result = $this->dispatch( 'DELETE', '/generated', array( 'forget' => true ) );

		if ( is_wp_error( $result ) ) {
			\WP_CLI::error( $result->get_error_message() );
			// Unreachable; see above.
			return;
		}

		\WP_CLI::success(
			sprintf(
				/* translators: %d: number of records dropped. */
				_n( 'Forgot %d record. The row it pointed at is untouched.', 'Forgot %d records. The rows they pointed at are untouched.', (int) ( $result['forgotten'] ?? 0 ), 'storeseeder' ),
				isset( $result['forgotten'] ) ? (int) $result['forgotten'] : 0
			)
		);
	}

	/**
	 * The ledger as table rows.
	 *
	 * Static and free of WP_CLI so it can be tested — the formatting is where a count ends
	 * up attributed to the wrong resource.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $status The /generated payload.
	 *
	 * @return array<int, array{resource: string, rows: int}>
	 */
	public static function rows( array $status ): array {
		$rows = array();

		foreach ( (array) ( $status['resources'] ?? array() ) as $entry ) {
			if ( ! is_array( $entry ) || ! isset( $entry['resource'] ) ) {
				continue;
			}

			$rows[] = array(
				'resource' => (string) $entry['resource'],
				'rows'     => isset( $entry['count'] ) ? (int) $entry['count'] : 0,
			);
		}

		return $rows;
	}
}
