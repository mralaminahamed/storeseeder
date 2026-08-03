<?php
/**
 * WooCommerce media writer
 *
 * @since   1.3.0
 * @package StoreSeeder\Platforms\Drivers\Woo_Commerce\Writers
 */

namespace StoreSeeder\Platforms\Drivers\Woo_Commerce\Writers;

use StoreSeeder\Platforms\Drivers\Woo_Commerce\Writer;
use StoreSeeder\Platforms\Resource;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Removes attachments generated for product imagery.
 *
 * There is no generator behind this and `write()` is unreachable by design — attachments are
 * created by `Platforms\Media` as a side effect of writing a product, never asked for on their
 * own. The writer exists because the purge resolves a deleter by resource name, and an
 * attachment nobody can delete is a PNG left in `uploads/` after the store it belonged to is
 * gone.
 *
 * @since 1.3.0
 */
final class Media extends Writer {

	/**
	 * The resource this writer persists.
	 *
	 * @since 1.3.0
	 *
	 * @return string
	 */
	public function resource(): string {
		return Resource::MEDIA;
	}

	/**
	 * Media is never written through the generation pipeline.
	 *
	 * @since 1.3.0
	 *
	 * @param array<string, mixed> $entity Unused; no generator produces one.
	 *
	 * @return WP_Error
	 */
	public function write( array $entity ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- the signature is the writer contract.
		return new WP_Error(
			'storeseeder_media_not_generated',
			__( 'Media attachments are created alongside products, not generated on their own.', 'storeseeder' )
		);
	}

	/**
	 * Delete a generated attachment and the files behind it.
	 *
	 * Already gone counts as success, for the same reason it does elsewhere: the ledger can
	 * outlive what it points at, and a missing row must not block the rest of a cleanup.
	 *
	 * @since 1.3.0
	 *
	 * @param int|string $id Attachment id.
	 *
	 * @return true|WP_Error
	 */
	public function delete( $id ) {
		$id = (int) $id;

		if ( $id < 1 || null === get_post( $id ) ) {
			return true;
		}

		// `true` forces the file off disk as well. Leaving the file and dropping the row is
		// the one outcome worse than doing nothing, because nothing then records where it went.
		wp_delete_attachment( $id, true );

		return true;
	}
}
