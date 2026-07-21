<?php
/**
 * Product Download Generator Class for Fluent Cart FakerPress Plugin
 *
 * @since      2.4.0
 * @subpackage Generators
 * @package    FluentCartFakerPress
 */

namespace FluentCartFakerPress\Generators;

use FluentCart\App\Models\Order as OrderModel;
use FluentCart\App\Models\OrderDownloadPermission as OrderDownloadPermissionModel;
use FluentCart\App\Models\ProductDownload as ProductDownloadModel;
use FluentCart\App\Models\ProductVariation as ProductVariationModel;
use FluentCartFakerPress\Abstracts\Generator;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Product Download Generator Class
 *
 * Generates downloadable files for products, and grants download permissions on
 * existing orders, for testing Fluent Cart digital-product fulfillment.
 */
class Product_Download extends Generator {


	/**
	 * Get the resource type name
	 *
	 * @return string Resource type name.
	 */
	protected function get_resource_type(): string {
		return 'product_download';
	}

	/**
	 * Get supported data types for this generator.
	 *
	 * @return array Supported types
	 */
	public function get_supported_types(): array {
		return array(
			'product_downloads' => 'Fluent Cart Product Downloads',
		);
	}

	/**
	 * Get generator description.
	 *
	 * @return string Description
	 */
	public function get_description(): string {
		return 'Generates downloadable files for products and grants download permissions on existing orders for testing Fluent Cart digital fulfillment.';
	}

	/**
	 * Generate a single product download
	 *
	 * @return WP_Error|array Single product download data, error, or false on failure.
	 */
	protected function generate_single_item() {
		if ( ! defined( 'FLUENTCART_VERSION' ) || ! class_exists( ProductDownloadModel::class ) ) {
			return new WP_Error( 'missing_fluent_cart', __( 'Fluent Cart ProductDownload model not found. Please ensure Fluent Cart is active.', 'fluent-cart-fakerpress' ) );
		}

		// A download hangs off a product; the variation gives both the parent
		// post_id and a concrete variation to grant later.
		$variation = ProductVariationModel::query()->with( 'product' )->inRandomOrder()->first();

		if ( ! $variation ) {
			return new WP_Error(
				'no_products',
				__( 'No product variations were found. Generate products before generating downloads.', 'fluent-cart-fakerpress' )
			);
		}

		$data     = $this->generate_download_data( (int) $variation->post_id, (int) $variation->id );
		$download = $this->create_download( $data );

		if ( ! $download ) {
			return new WP_Error( 'product_download_creation_failed', __( 'Failed to create product download.', 'fluent-cart-fakerpress' ) );
		}

		$granted = $this->grant_permission( (int) $download->id, (int) $variation->id );

		$result = array(
			'id'                  => $download->id,
			'post_id'             => $download->post_id,
			'title'               => $data['title'],
			'download_identifier' => $data['download_identifier'],
			'permission_granted'  => $granted,
			'created_at'          => current_time( 'Y-m-d H:i:s' ),
		);

		/**
		 * Filters the product download generation result data.
		 *
		 * @since 2.4.0
		 * @hook  fluent_cart_fakerpress_product_download_generation_result
		 *
		 * @param array $result The generation result data.
		 * @param int   $id     The created download ID.
		 * @param array $data   The original data used for creation.
		 */
		return apply_filters( 'fluent_cart_fakerpress_product_download_generation_result', $result, $download->id, $data );
	}

	/**
	 * Build the download data.
	 *
	 * @param int $post_id      Parent product post ID.
	 * @param int $variation_id Variation the file belongs to.
	 *
	 * @return array Download data.
	 */
	private function generate_download_data( int $post_id, int $variation_id ): array {
		$slug = $this->get_faker()->slug( 3 );

		return array(
			'post_id'              => $post_id,
			// Stored as a JSON array of variation IDs via a mutator; scoping to
			// the drawn variation keeps the file tied to something real.
			'product_variation_id' => array( $variation_id ),
			'download_identifier'  => $this->unique_identifier(),
			'title'                => ucwords( str_replace( '-', ' ', $slug ) ),
			'type'                 => 'file',
			'driver'               => 'local',
			'file_name'            => $slug . '.zip',
			'file_url'             => 'https://downloads.example.test/' . $slug . '.zip',
			'file_size'            => (string) $this->get_faker()->numberBetween( 51200, 52428800 ),
			'serial'               => 1,
		);
	}

	/**
	 * Build a download identifier that is not already taken.
	 *
	 * The fct_product_downloads table carries a UNIQUE index on download_identifier.
	 *
	 * @return string Unused identifier.
	 */
	private function unique_identifier(): string {
		do {
			$identifier = md5( $this->get_faker()->unique()->uuid() );
		} while ( ProductDownloadModel::query()->where( 'download_identifier', $identifier )->exists() );

		return $identifier;
	}

	/**
	 * Grant a download permission against a random existing order.
	 *
	 * @param int $download_id  Download ID.
	 * @param int $variation_id Variation ID.
	 *
	 * @return bool Whether a permission was granted.
	 */
	private function grant_permission( int $download_id, int $variation_id ): bool {
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
				// Null download_limit means unlimited; a small cap is more typical.
				'download_limit' => $this->get_faker()->numberBetween( 1, 5 ),
			)
		);

		return true;
	}

	/**
	 * Create the download in Fluent Cart.
	 *
	 * @param array $data Download data.
	 *
	 * @return ProductDownloadModel|null Created instance.
	 */
	private function create_download( array $data ): ?ProductDownloadModel {
		try {
			return ProductDownloadModel::query()->create( $data );
		} catch ( \Exception $e ) {
			return null;
		}
	}
}
