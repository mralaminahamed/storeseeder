<?php
/**
 * Product Download REST Controller
 *
 * @since   2.4.0
 * @package FluentCartFakerPress\Controllers
 */

namespace FluentCartFakerPress\Controllers;

use FluentCartFakerPress\Abstracts\Controller;
use FluentCartFakerPress\Generators\Product_Download as ProductDownloadGenerator;

/**
 * Product Download REST Controller Class
 *
 * Handles REST API endpoints for product download generation.
 *
 * @since 2.4.0
 */
class Product_Download extends Controller {


	/**
	 * Get resource type name
	 *
	 * @return string Resource type.
	 */
	protected function get_resource_type(): string {
		return 'product_download';
	}

	/**
	 * Get resource type label
	 *
	 * @return string The translated label for the resource type.
	 */
	protected function get_resource_type_label(): string {
		return __( 'Product Download', 'fluent-cart-fakerpress' );
	}

	/**
	 * Get REST base for the endpoint
	 *
	 * @return string REST base.
	 */
	protected function get_rest_base(): string {
		return 'product_downloads';
	}

	/**
	 * Get generator instance
	 *
	 * @return ProductDownloadGenerator Generator instance.
	 */
	protected function get_generator_instance(): ProductDownloadGenerator {
		return new ProductDownloadGenerator();
	}
}
