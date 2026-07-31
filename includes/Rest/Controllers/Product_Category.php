<?php
/**
 * Product Category REST Controller
 *
 * @since   1.1.0
 * @package StoreSeeder\Rest\Controllers
 */

namespace StoreSeeder\Rest\Controllers;

use StoreSeeder\Rest\Controller;
use StoreSeeder\Generation\Generators\Product_Category as ProductCategoryGenerator;

/**
 * Product Category REST Controller Class
 *
 * Handles REST API endpoints for product category generation.
 *
 * @since 1.1.0
 */
class Product_Category extends Controller {


	/**
	 * Get resource type name
	 *
	 * @return string Resource type.
	 */
	protected function get_resource_type(): string {
		return 'product_category';
	}

	/**
	 * Get resource type label
	 *
	 * @return string The translated label for the resource type.
	 */
	protected function get_resource_type_label(): string {
		return __( 'Product Category', 'storeseeder' );
	}

	/**
	 * Get REST base for the endpoint
	 *
	 * @return string REST base.
	 */
	protected function get_rest_base(): string {
		return 'product_categories';
	}

	/**
	 * Get generator instance
	 *
	 * @return ProductCategoryGenerator Generator instance.
	 */
	protected function get_generator_instance(): ProductCategoryGenerator {
		return new ProductCategoryGenerator();
	}
}
