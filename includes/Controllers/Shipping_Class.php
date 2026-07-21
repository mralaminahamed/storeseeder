<?php
/**
 * Shipping Class REST Controller
 *
 * @since   2.4.0
 * @package StoreSeeder\Controllers
 */

namespace StoreSeeder\Controllers;

use StoreSeeder\Abstracts\Controller;
use StoreSeeder\Generators\Shipping_Class as ShippingClassGenerator;

/**
 * Shipping Class REST Controller Class
 *
 * Handles REST API endpoints for shipping class generation.
 *
 * @since 1.0.0
 */
class Shipping_Class extends Controller {


	/**
	 * Get resource type name
	 *
	 * @return string Resource type.
	 */
	protected function get_resource_type(): string {
		return 'shipping_class';
	}

	/**
	 * Get resource type label
	 *
	 * @return string The translated label for the resource type.
	 */
	protected function get_resource_type_label(): string {
		return __( 'Shipping Class', 'storeseeder' );
	}

	/**
	 * Get REST base for the endpoint
	 *
	 * @return string REST base.
	 */
	protected function get_rest_base(): string {
		return 'shipping_classes';
	}

	/**
	 * Get generator instance
	 *
	 * @return ShippingClassGenerator Generator instance.
	 */
	protected function get_generator_instance(): ShippingClassGenerator {
		return new ShippingClassGenerator();
	}
}
