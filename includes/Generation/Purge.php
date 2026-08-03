<?php
/**
 * Deleting what StoreSeeder generated
 *
 * Walks the ledger, hands each recorded id to the writer that created it, and forgets the
 * rows that are gone. It never queries the store's tables for candidates — only ids the
 * plugin recorded are ever passed to a writer, which is what keeps "delete generated data"
 * from meaning "delete data that looks generated".
 *
 * @since   1.1.0
 * @package StoreSeeder\Generation
 */

namespace StoreSeeder\Generation;

use StoreSeeder\Platforms\Registry as Platform_Registry;
use StoreSeeder\Platforms\Resource;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * The cleanup.
 *
 * @since 1.1.0
 */
final class Purge {
	/**
	 * Rows deleted per request by default.
	 *
	 * A cap rather than "everything": deleting an order takes six child queries, so a site
	 * with thousands of recorded rows would time out mid-way and report nothing. The caller
	 * gets what is left and calls again, which also gives the admin a progress figure
	 * instead of a spinner.
	 *
	 * @since 1.1.0
	 * @var int
	 */
	const BATCH = 100;

	/**
	 * Delete a batch of generated rows.
	 *
	 * @since 1.1.0
	 *
	 * @param string $only   Canonical resource to limit to, or '' for every resource.
	 * @param int    $limit  Maximum rows to delete in this call.
	 * @param string $run_id Limit to one recipe run, or '' for every recorded row.
	 *
	 * @return array{deleted: int, remaining: int, by_resource: array<string, int>, errors: array<int, string>}
	 */
	public static function run( string $only = '', int $limit = self::BATCH, string $run_id = '' ): array {
		$limit     = max( 1, $limit );
		$deleted   = 0;
		$by_scope  = array();
		$errors    = array();
		$platforms = Ledger::platforms();

		foreach ( $platforms as $platform_id ) {
			$platform = Platform_Registry::instance()->get( $platform_id );

			// A driver can disappear — the plugin that registered it was deactivated, or a
			// third-party platform was removed. Its rows stay recorded and stay in the
			// store, and saying so is more useful than dropping the records and pretending.
			if ( null === $platform ) {
				$errors[] = sprintf(
					/* translators: %s: platform id, e.g. fluent-cart. */
					__( 'No driver is active for %s, so rows generated for it were left alone.', 'storeseeder' ),
					$platform_id
				);
				continue;
			}

			foreach ( self::order( $only ) as $resource_type ) {
				if ( $deleted >= $limit ) {
					break 2;
				}

				$batch = Ledger::batch( $platform_id, $resource_type, $limit - $deleted, $run_id );

				if ( array() === $batch ) {
					continue;
				}

				$writer = $platform->writer( $resource_type );

				if ( null === $writer ) {
					$errors[] = sprintf(
						/* translators: 1: resource name, 2: platform label. */
						__( '%1$s records cannot be removed from %2$s automatically.', 'storeseeder' ),
						$resource_type,
						$platform->label()
					);
					continue;
				}

				$forget = array();

				foreach ( $batch as $row ) {
					$result = $writer->delete( $row['object_id'] );

					if ( is_wp_error( $result ) ) {
						// One message per resource, not per row: a missing plugin fails all
						// two hundred identically, and two hundred identical lines is not a
						// more informative error.
						$message = $result->get_error_message();

						if ( ! in_array( $message, $errors, true ) ) {
							$errors[] = $message;
						}

						break;
					}

					$forget[] = $row['id'];
					++$deleted;

					$key              = $resource_type;
					$by_scope[ $key ] = ( $by_scope[ $key ] ?? 0 ) + 1;
				}

				Ledger::forget( $forget );
			}
		}

		return array(
			'deleted'     => $deleted,
			'remaining'   => '' === $run_id ? self::remaining( $only ) : Ledger::count_for_run( $run_id ),
			'by_resource' => $by_scope,
			'errors'      => $errors,
		);
	}

	/**
	 * How many recorded rows are still waiting.
	 *
	 * @since 1.1.0
	 *
	 * @param string $only Canonical resource, or '' for every resource.
	 *
	 * @return int
	 */
	public static function remaining( string $only = '' ): int {
		$counts = Ledger::counts();

		if ( '' === $only ) {
			return array_sum( $counts );
		}

		return (int) ( $counts[ $only ] ?? 0 );
	}

	/**
	 * The order resources are deleted in.
	 *
	 * Children before parents, which is the reverse of the order they can be generated in:
	 * a transaction deleted after its order would already have been taken out as one of the
	 * order's children, and its ledger row would then be a permanent failure.
	 *
	 * @since 1.1.0
	 *
	 * @param string $only Limit to one resource, or '' for all of them.
	 *
	 * @return array<int, string>
	 */
	public static function order( string $only = '' ): array {
		if ( '' !== $only ) {
			return Resource::exists( $only ) ? array( $only ) : array();
		}

		// Internal resources go last. A product carries its image as an attachment id, so the
		// attachment outliving the product for the length of one batch is harmless, while the
		// reverse would leave every product pointing at a file that is already gone.
		$order = array_merge( array_reverse( Resource::all() ), Resource::internal() );

		/**
		 * Filters the order generated resources are deleted in.
		 *
		 * Children must come before their parents. A platform of your own with a resource
		 * that has to go first — a queue row referencing an order, say — reorders it here.
		 *
		 * @since 1.1.0
		 * @hook  storeseeder_purge_order
		 *
		 * @param array<int, mixed> $order Canonical resource names, in deletion order. Typed
		 *                                 loosely because a filter can return anything, and
		 *                                 what comes back is validated rather than trusted.
		 */
		$filtered = (array) apply_filters( 'storeseeder_purge_order', $order );

		// Anything the filter invented is dropped rather than handed to a writer that has
		// no such resource, and anything it forgot is appended so nothing becomes
		// undeletable by omission. The is_string() is not redundant despite the declared
		// type: a filter returns whatever it likes.
		$valid = array();

		foreach ( $filtered as $candidate ) {
			if ( is_string( $candidate ) && Resource::exists( $candidate ) ) {
				$valid[] = $candidate;
			}
		}

		return array_values( array_unique( array_merge( $valid, $order ) ) );
	}
}
