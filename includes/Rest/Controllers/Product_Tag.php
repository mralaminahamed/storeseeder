<?php
/**
 * Product Tag REST Controller
 *
 * @since   1.1.0
 * @package StoreSeeder\Rest\Controllers
 */

namespace StoreSeeder\Rest\Controllers;

use StoreSeeder\Rest\Controller;
use StoreSeeder\Generation\Generators\Product_Tag as ProductTagGenerator;

/**
 * Product Tag REST Controller Class
 *
 * Handles REST API endpoints for product tag generation.
 *
 * @since 1.1.0
 */
class Product_Tag extends Controller {


	/**
	 * Get resource type name
	 *
	 * @return string Resource type.
	 */
	protected function get_resource_type(): string {
		return 'product_tag';
	}

	/**
	 * Get resource type label
	 *
	 * @return string The translated label for the resource type.
	 */
	protected function get_resource_type_label(): string {
		return __( 'Product Tag', 'storeseeder' );
	}

	/**
	 * Get REST base for the endpoint
	 *
	 * @return string REST base.
	 */
	protected function get_rest_base(): string {
		return 'product_tags';
	}

	/**
	 * Get generator instance
	 *
	 * @return ProductTagGenerator Generator instance.
	 */
	protected function get_generator_instance(): ProductTagGenerator {
		return new ProductTagGenerator();
	}
}
