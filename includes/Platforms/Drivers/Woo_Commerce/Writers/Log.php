<?php
/**
 * WooCommerce log writer
 *
 * @since   1.1.0
 * @package StoreSeeder\Platforms\Drivers\Woo_Commerce\Writers
 */

namespace StoreSeeder\Platforms\Drivers\Woo_Commerce\Writers;

use StoreSeeder\Platforms\Drivers\Woo_Commerce\Writer;
use StoreSeeder\Platforms\Resource;
use WC_Log_Handler_DB;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Persists a canonical log entry into WooCommerce's log table.
 *
 * Through `WC_Log_Handler_DB` rather than an insert of our own, so the row matches what
 * WooCommerce's own logging writes — the same level vocabulary, the same serialised context,
 * the same source column the admin's Logs screen filters on.
 *
 * The handler is used directly rather than through `wc_get_logger()`, because a site whose
 * logging is set to files would otherwise write these entries to disk, where the Logs screen's
 * database view cannot show them. Generated logs are for looking at.
 *
 * @since 1.1.0
 */
final class Log extends Writer {
	/**
	 * Canonical severity to a PSR-3 level WooCommerce recognises.
	 *
	 * `success` is not a log level anywhere in WooCommerce — it maps to `info`, which is what
	 * a successful operation is recorded as.
	 *
	 * @since 1.1.0
	 * @var array<string, string>
	 */
	private const LEVEL = array(
		'info'    => 'info',
		'success' => 'info',
		'warning' => 'warning',
		'error'   => 'error',
	);

	/**
	 * The resource this writer persists.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public function resource(): string {
		return Resource::LOG;
	}

	/**
	 * Write a log entry.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $entity Canonical log entity.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function write( array $entity ) {
		global $wpdb;

		if ( ! class_exists( 'WC_Log_Handler_DB' ) ) {
			return new WP_Error(
				'missing_woocommerce',
				__( 'WooCommerce is not active on this site.', 'storeseeder' )
			);
		}

		$level  = self::LEVEL[ (string) $entity['severity'] ] ?? 'info';
		$module = (string) $entity['module'];

		$handler = new WC_Log_Handler_DB();
		$written = $handler->handle(
			time(),
			$level,
			(string) $entity['title'] . ' — ' . (string) $entity['content'],
			array(
				// The source is what the Logs screen groups by, so the canonical module name
				// is used rather than a guessed file name.
				'source'    => 'storeseeder-' . $module,
				'module'    => $module,
				'module_id' => (int) $entity['module_id'],
				'log_type'  => (string) $entity['log_type'],
				'user_id'   => (int) $entity['user_id'],
			)
		);

		if ( ! $written ) {
			return new WP_Error( 'log_creation_failed', __( 'Failed to write the log entry.', 'storeseeder' ) );
		}

		// The handler returns a boolean, so the id comes from the insert it just did. Needed
		// because the cleanup deletes by id, and a log entry has nothing else unique.
		$id = (int) $wpdb->insert_id;

		$data = array(
			'id'     => $id,
			'level'  => $level,
			'source' => 'storeseeder-' . $module,
		);

		$result = array(
			'id'         => $id,
			'title'      => (string) $entity['title'],
			'module'     => $module,
			'severity'   => $level,
			'log_type'   => (string) $entity['log_type'],
			'content'    => (string) $entity['content'],
			'created_at' => current_time( 'Y-m-d H:i:s' ),
		);

		return $this->filter_result( $result, $id, $data );
	}

	/**
	 * Remove a generated log entry.
	 *
	 * @since 1.1.0
	 *
	 * @param int|string $id Log id.
	 *
	 * @return true|WP_Error
	 */
	public function delete( $id ) {
		if ( ! class_exists( 'WC_Log_Handler_DB' ) ) {
			return new WP_Error(
				'storeseeder_delete_unsupported',
				__( 'WooCommerce is not active on this site.', 'storeseeder' )
			);
		}

		// WooCommerce's own bulk-delete helper, so the row goes the way the Logs screen's
		// delete button would remove it.
		WC_Log_Handler_DB::delete( array( (int) $id ) );

		return true;
	}
}
