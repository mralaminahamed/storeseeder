<?php
/**
 * WooCommerce product download writer
 *
 * @since   1.1.0
 * @package StoreSeeder\Platforms\Drivers\Woo_Commerce\Writers
 */

namespace StoreSeeder\Platforms\Drivers\Woo_Commerce\Writers;

use StoreSeeder\Platforms\Drivers\Woo_Commerce\Writer;
use StoreSeeder\Platforms\Resource;
use WC_Product;
use WC_Product_Download;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Attaches a canonical downloadable file to an existing WooCommerce product.
 *
 * WooCommerce has no download *record*: a download is an entry in the product's
 * `_downloadable_files` meta, keyed by a hash the file itself generates. So the id this writer
 * reports is that key, and the cleanup finds the product holding it — see `delete()`.
 *
 * Attaching a file also flips the product to downloadable, and a downloadable product with no
 * file is one WooCommerce refuses to sell, which is why the Products writer leaves that flag
 * off until this runs.
 *
 * @since 1.1.0
 */
final class Product_Download extends Writer {
	/**
	 * The resource this writer persists.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public function resource(): string {
		return Resource::PRODUCT_DOWNLOAD;
	}

	/**
	 * Attach a downloadable file to a product.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $entity Canonical download entity.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function write( array $entity ) {
		if ( ! class_exists( 'WC_Product_Download' ) ) {
			return new WP_Error(
				'missing_woocommerce',
				__( 'WooCommerce is not active on this site.', 'storeseeder' )
			);
		}

		$product = $this->random_product();

		if ( null === $product ) {
			return $this->missing_prerequisite(
				'no_products',
				__( 'No products were found. Generate products before generating downloads.', 'storeseeder' )
			);
		}

		$download = new WC_Product_Download();
		$download->set_name( (string) $entity['title'] );
		$download->set_file( (string) $entity['file_url'] );
		// The generator's identifier rather than WooCommerce's own hash: it is unique per
		// item, and using it means the ledger's id matches the key stored on the product.
		$download->set_id( (string) $entity['download_identifier'] );

		$downloads                        = $product->get_downloads();
		$downloads[ $download->get_id() ] = $download;

		$product->set_downloads( $downloads );
		$product->set_downloadable( true );
		$product->set_download_limit( (int) $entity['download_limit'] );
		// Expiry left unset: a download that stops working after a fixed number of days is a
		// harder fixture to reason about than one that does not.
		$product->save();

		$data = array(
			'id'         => $download->get_id(),
			'product_id' => $product->get_id(),
			'file'       => $download->get_file(),
		);

		$result = array(
			'id'             => $download->get_id(),
			'title'          => $download->get_name(),
			'product'        => $product->get_name(),
			'product_id'     => $product->get_id(),
			'file_name'      => (string) $entity['file_name'],
			'file_url'       => $download->get_file(),
			'download_limit' => (int) $entity['download_limit'],
			'created_at'     => current_time( 'Y-m-d H:i:s' ),
		);

		return $this->filter_result( $result, $download->get_id(), $data );
	}

	/**
	 * Remove a generated download from whichever product holds it.
	 *
	 * The download key is all the ledger has, so the product is found by looking for the key
	 * inside the serialised `_downloadable_files` meta. A LIKE against one meta key is bounded
	 * and beats storing a composite id that every table in the admin would have to render.
	 *
	 * @since 1.1.0
	 *
	 * @param int|string $id The download key.
	 *
	 * @return true|WP_Error
	 */
	public function delete( $id ) {
		global $wpdb;

		$key = (string) $id;

		if ( '' === $key ) {
			return true;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- no core API answers "which product holds this download key".
		$product_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_downloadable_files' AND meta_value LIKE %s LIMIT 1",
				'%' . $wpdb->esc_like( $key ) . '%'
			)
		);

		if ( $product_id <= 0 ) {
			// Already gone, or the product was deleted with it.
			return true;
		}

		$product = wc_get_product( $product_id );

		if ( ! $product instanceof WC_Product ) {
			return true;
		}

		$downloads = $product->get_downloads();
		unset( $downloads[ $key ] );

		$product->set_downloads( $downloads );

		// A product left downloadable with no files cannot be bought, so the flag comes off
		// with the last file — the inverse of what write() does.
		if ( array() === $downloads ) {
			$product->set_downloadable( false );
		}

		$product->save();

		return true;
	}
}
