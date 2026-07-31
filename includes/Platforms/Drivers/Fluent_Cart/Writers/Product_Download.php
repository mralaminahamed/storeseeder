<?php
/**
 * Fluent Cart product download writer
 *
 * @since   1.1.0
 * @package StoreSeeder\Platforms\Drivers\Fluent_Cart\Writers
 */

namespace StoreSeeder\Platforms\Drivers\Fluent_Cart\Writers;

use Exception;
use FluentCart\App\Models\Order as OrderModel;
use FluentCart\App\Models\OrderDownloadPermission as OrderDownloadPermissionModel;
use FluentCart\App\Models\ProductDownload as ProductDownloadModel;
use FluentCart\App\Models\ProductVariation as ProductVariationModel;
use StoreSeeder\Platforms\Writer;
use StoreSeeder\Platforms\Resource;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Persists a canonical product download into Fluent Cart.
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
	 * Create a Fluent Cart product download and grant a permission for it.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $entity Canonical product download entity.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function write( array $entity ) {
		if ( ! defined( 'FLUENTCART_VERSION' ) || ! class_exists( ProductDownloadModel::class ) ) {
			return new WP_Error( 'missing_fluent_cart', __( 'Fluent Cart ProductDownload model not found. Please ensure Fluent Cart is active.', 'storeseeder' ) );
		}

		// A download hangs off a product; the variation gives both the parent
		// post_id and a concrete variation to grant later.
		$variation = ProductVariationModel::query()->with( 'product' )->inRandomOrder()->first();

		if ( ! $variation ) {
			return new WP_Error(
				'no_products',
				__( 'No product variations were found. Generate products before generating downloads.', 'storeseeder' )
			);
		}

		$data = array(
			'post_id'              => (int) $variation->post_id,
			// Stored as a JSON array of variation IDs via a mutator; scoping to
			// the drawn variation keeps the file tied to something real.
			'product_variation_id' => array( (int) $variation->id ),
			'download_identifier'  => $this->unique_identifier( (string) $entity['download_identifier'] ),
			'title'                => $entity['title'],
			'type'                 => $entity['type'],
			'driver'               => 'local',
			'file_name'            => $entity['file_name'],
			'file_url'             => $entity['file_url'],
			'file_size'            => $entity['file_size'],
			'serial'               => 1,
		);

		$download = $this->create_download( $data );

		if ( ! $download ) {
			return new WP_Error( 'product_download_creation_failed', __( 'Failed to create product download.', 'storeseeder' ) );
		}

		$granted = $this->grant_permission(
			(int) $download->id,
			(int) $variation->id,
			(int) $entity['download_limit']
		);

		$result = array(
			'id'                  => $download->id,
			'post_id'             => $download->post_id,
			'title'               => $data['title'],
			'download_identifier' => $data['download_identifier'],
			'permission_granted'  => $granted,
			'created_at'          => current_time( 'Y-m-d H:i:s' ),
		);

		return $this->filter_result( $result, $download->id, $data );
	}

	/**
	 * Ensure a download identifier is not already taken.
	 *
	 * The fct_product_downloads table carries a UNIQUE index on download_identifier.
	 *
	 * @since 1.1.0
	 *
	 * @param string $candidate Identifier proposed by the generator.
	 *
	 * @return string Unused identifier.
	 */
	private function unique_identifier( string $candidate ): string {
		$identifier = $candidate;

		while ( ProductDownloadModel::query()->where( 'download_identifier', $identifier )->exists() ) {
			$identifier = md5( $this->faker()->unique()->uuid() );
		}

		return $identifier;
	}

	/**
	 * Grant a download permission against a random existing order.
	 *
	 * @since 1.1.0
	 *
	 * @param int $download_id  Download ID.
	 * @param int $variation_id Variation ID.
	 * @param int $limit        Download limit to record.
	 *
	 * @return bool Whether a permission was granted.
	 */
	private function grant_permission( int $download_id, int $variation_id, int $limit ): bool {
		if ( ! class_exists( OrderDownloadPermissionModel::class ) ) {
			return false;
		}

		$order = OrderModel::query()->whereNotNull( 'customer_id' )->inRandomOrder()->first();

		if ( ! $order ) {
			return false;
		}

		OrderDownloadPermissionModel::query()->create(
			array(
				'order_id'       => (int) $order->id,
				'variation_id'   => $variation_id,
				'download_id'    => $download_id,
				'customer_id'    => (int) $order->customer_id,
				'download_count' => 0,
				'download_limit' => $limit,
			)
		);

		return true;
	}

	/**
	 * Create the download in Fluent Cart.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $data Fluent-Cart-shaped download data.
	 *
	 * @return ProductDownloadModel|null Created instance.
	 */
	private function create_download( array $data ): ?ProductDownloadModel {
		try {
			$created = ProductDownloadModel::query()->create( $data );

			// Eloquent's create() is reached through __callStatic, so its declared type is
			// Builder|Model rather than this model. Narrowing here is what makes the
			// nullable return type above true rather than merely intended.
			return $created instanceof ProductDownloadModel ? $created : null;
		} catch ( Exception $e ) {
			return null;
		}
	}

	/**
	 * Remove a generated downloadable file, and the permissions granting access to it.
	 *
	 * @since 1.1.0
	 *
	 * @param int|string $id The identifier reported when the row was created.
	 *
	 * @return true|WP_Error
	 */
	public function delete( $id ) {
		return $this->delete_model(
			ProductDownloadModel::class,
			$id,
			array( OrderDownloadPermissionModel::class => 'download_id' )
		);
	}
}
