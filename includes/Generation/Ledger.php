<?php
/**
 * A record of what StoreSeeder created
 *
 * The reason this table exists: "delete the test data" is only a safe offer if the plugin
 * can tell its own rows from the store's. Deleting every product, or everything created after
 * a date, would eventually take out something real — on a staging site restored from
 * production, that is someone's actual catalogue. So each successful write is recorded here,
 * and deletion walks this list rather than the store's tables.
 *
 * Ids are stored as strings because not every resource has an integer one — a Fluent Cart
 * cart session is identified by its hash.
 *
 * @since   1.1.0
 * @package StoreSeeder\Generation
 */

namespace StoreSeeder\Generation;

defined( 'ABSPATH' ) || exit;

/**
 * The generated-row ledger.
 *
 * @since 1.1.0
 */
final class Ledger {
	/**
	 * Table name without the site prefix.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	const TABLE = 'storeseeder_generated';

	/**
	 * Schema version, bumped when the table changes.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	const DB_VERSION = '1';

	/**
	 * Option holding the installed schema version.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	const DB_VERSION_OPTION = 'storeseeder_ledger_db_version';

	/**
	 * The prefixed table name.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public static function table(): string {
		global $wpdb;

		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Create or update the table.
	 *
	 * Runs on activation, and again from `maybe_install()` on any request that finds the
	 * stored version behind — a plugin updated by uploading a zip never fires the activation
	 * hook, and an absent ledger would mean generated rows quietly stopped being recorded.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table();
		$collate = $wpdb->get_charset_collate();

		// The (platform, resource) index is what makes the per-resource counts the admin
		// shows a cheap query rather than a scan.
		dbDelta(
			"CREATE TABLE {$table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				platform varchar(50) NOT NULL DEFAULT '',
				resource varchar(50) NOT NULL DEFAULT '',
				object_id varchar(191) NOT NULL DEFAULT '',
				created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY  (id),
				KEY platform_resource (platform, resource),
				KEY object (object_id)
			) {$collate};"
		);

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, true );
	}

	/**
	 * Install the table if it is missing or out of date.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public static function maybe_install(): void {
		if ( self::DB_VERSION === (string) get_option( self::DB_VERSION_OPTION, '' ) ) {
			return;
		}

		self::install();
	}

	/**
	 * Record one created row.
	 *
	 * Failures are swallowed. A row that generated fine but could not be recorded is a row
	 * the cleanup will miss, which is worth a debug line and not worth failing a run for —
	 * the alternative is telling the user their products were not created when they were.
	 *
	 * @since 1.1.0
	 *
	 * @param string     $platform      Platform id the row was written to.
	 * @param string     $resource_type Canonical resource name.
	 * @param int|string $object_id     The created row's identifier.
	 *
	 * @return bool Whether the row was recorded.
	 */
	public static function record( string $platform, string $resource_type, $object_id ): bool {
		global $wpdb;

		$object_id = (string) $object_id;

		if ( '' === $object_id || '0' === $object_id ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- own table, no core API for it.
		$written = $wpdb->insert(
			self::table(),
			array(
				'platform'   => $platform,
				'resource'   => $resource_type,
				'object_id'  => $object_id,
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%s' )
		);

		return false !== $written;
	}

	/**
	 * How many rows are recorded per resource, for one platform or all of them.
	 *
	 * @since 1.1.0
	 *
	 * @param string $platform Platform id, or '' for every platform.
	 *
	 * @return array<string, int> Resource name => count, highest first.
	 */
	public static function counts( string $platform = '' ): array {
		global $wpdb;

		// %i for the table name: an identifier placeholder, supported since WordPress 6.2 and
		// well under the 6.5 floor. Interpolating the name would be safe here — it comes from
		// $wpdb->prefix — but it also trips the sniff that catches the cases where it is not.
		if ( '' === $platform ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results(
				$wpdb->prepare( 'SELECT resource, COUNT(*) AS total FROM %i GROUP BY resource ORDER BY total DESC', self::table() ),
				ARRAY_A
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results(
				$wpdb->prepare( 'SELECT resource, COUNT(*) AS total FROM %i WHERE platform = %s GROUP BY resource ORDER BY total DESC', self::table(), $platform ),
				ARRAY_A
			);
		}

		$counts = array();

		foreach ( (array) $rows as $row ) {
			$counts[ (string) $row['resource'] ] = (int) $row['total'];
		}

		return $counts;
	}

	/**
	 * Total rows recorded.
	 *
	 * @since 1.1.0
	 *
	 * @param string $platform Platform id, or '' for every platform.
	 *
	 * @return int
	 */
	public static function total( string $platform = '' ): int {
		return array_sum( self::counts( $platform ) );
	}

	/**
	 * The platforms that have recorded rows.
	 *
	 * Reported so the admin can say which store the rows are in — a site that switched
	 * target platform has rows in two, and deleting them needs the driver that wrote them.
	 *
	 * @since 1.1.0
	 *
	 * @return array<int, string>
	 */
	public static function platforms(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT platform FROM %i WHERE platform <> ''", self::table() ) );

		return array_map( 'strval', (array) $ids );
	}

	/**
	 * A batch of recorded rows for one platform and resource.
	 *
	 * Newest first, so deletion undoes the most recent run before older ones — the order
	 * someone clearing up after a mistaken run expects.
	 *
	 * @since 1.1.0
	 *
	 * @param string $platform      Platform id.
	 * @param string $resource_type Canonical resource name.
	 * @param int    $limit         How many rows to return.
	 *
	 * @return array<int, array{id: int, object_id: string}>
	 */
	public static function batch( string $platform, string $resource_type, int $limit = 100 ): array {
		global $wpdb;

		$limit = max( 1, $limit );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, object_id FROM %i WHERE platform = %s AND resource = %s ORDER BY id DESC LIMIT %d',
				self::table(),
				$platform,
				$resource_type,
				$limit
			),
			ARRAY_A
		);

		return array_map(
			static function ( array $row ): array {
				return array(
					'id'        => (int) $row['id'],
					'object_id' => (string) $row['object_id'],
				);
			},
			(array) $rows
		);
	}

	/**
	 * Drop ledger rows by their own ids.
	 *
	 * Called after the store rows they point at are gone. Separate from deletion on purpose:
	 * a row that failed to delete must stay recorded, or the next attempt would not know to
	 * try it again.
	 *
	 * @since 1.1.0
	 *
	 * @param array<int, int> $ids Ledger row ids.
	 *
	 * @return int How many were dropped.
	 */
	public static function forget( array $ids ): int {
		global $wpdb;

		$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );

		if ( array() === $ids ) {
			return 0;
		}

		// One statement per id rather than an IN clause built by string concatenation. The
		// clause would need an interpolated list of placeholders, which is the shape the
		// sniff exists to catch, and forgetting is not on any hot path — it follows a delete
		// that already cost several queries per row.
		$deleted = 0;

		foreach ( $ids as $id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$dropped = $wpdb->delete( self::table(), array( 'id' => $id ), array( '%d' ) );

			if ( false !== $dropped ) {
				$deleted += (int) $dropped;
			}
		}

		return $deleted;
	}

	/**
	 * Forget every recorded row without deleting anything from the store.
	 *
	 * The escape hatch for a ledger that no longer matches reality — a database restored
	 * from elsewhere, or rows removed by hand. It is offered separately from deletion, and
	 * labelled as forgetting rather than as deleting, because the two are opposite mistakes
	 * to make.
	 *
	 * @since 1.1.0
	 *
	 * @param string $platform Platform id, or '' for every platform.
	 *
	 * @return int How many records were dropped.
	 */
	public static function forget_all( string $platform = '' ): int {
		global $wpdb;

		if ( '' === $platform ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$deleted = $wpdb->query( $wpdb->prepare( 'DELETE FROM %i', self::table() ) );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$deleted = $wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE platform = %s', self::table(), $platform ) );
		}

		return false === $deleted ? 0 : (int) $deleted;
	}
}
