<?php
/**
 * Fluent Cart media writer
 *
 * @since   1.3.0
 * @package StoreSeeder\Platforms\Drivers\Fluent_Cart\Writers
 */

namespace StoreSeeder\Platforms\Drivers\Fluent_Cart\Writers;

use StoreSeeder\Platforms\Resource;
use StoreSeeder\Platforms\Writer;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Removes attachments generated for product imagery.
 *
 * Identical in behaviour to the WooCommerce writer of the same name, because an attachment is a
 * WordPress row rather than a Fluent Cart one — both drivers hang product images off the media
 * library. The duplication is the driver boundary doing its job: a platform that stored images
 * somewhere else would replace this file and nothing else.
 *
 * There is no generator behind it. `write()` is unreachable by design; the writer exists so the
 * purge can resolve a deleter by resource name, and an attachment nobody can delete is a PNG
 * left in `uploads/` after the store it belonged to is gone.
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

		wp_delete_attachment( $id, true );

		return true;
	}
}
