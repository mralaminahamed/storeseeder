<?php
/**
 * Brand REST Controller
 *
 * @since   1.1.0
 * @package StoreSeeder\Rest\Controllers
 */

namespace StoreSeeder\Rest\Controllers;

use StoreSeeder\Rest\Controller;
use StoreSeeder\Generation\Generators\Brand as BrandGenerator;

/**
 * Brand REST Controller Class
 *
 * Handles REST API endpoints for product brand generation.
 *
 * @since 1.1.0
 */
class Brand extends Controller {


	/**
	 * Get resource type name
	 *
	 * @return string Resource type.
	 */
	protected function get_resource_type(): string {
		return 'brand';
	}

	/**
	 * Get resource type label
	 *
	 * @return string The translated label for the resource type.
	 */
	protected function get_resource_type_label(): string {
		return __( 'Brand', 'storeseeder' );
	}

	/**
	 * Get REST base for the endpoint
	 *
	 * @return string REST base.
	 */
	protected function get_rest_base(): string {
		return 'brands';
	}

	/**
	 * Get generator instance
	 *
	 * @return BrandGenerator Generator instance.
	 */
	protected function get_generator_instance(): BrandGenerator {
		return new BrandGenerator();
	}
}
