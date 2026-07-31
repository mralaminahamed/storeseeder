<?php
/**
 * Order Tax Rate REST Controller
 *
 * @since   2.4.0
 * @package StoreSeeder\Rest\Controllers
 */

namespace StoreSeeder\Rest\Controllers;

use StoreSeeder\Rest\Controller;
use StoreSeeder\Generation\Generators\Order_Tax_Rate as OrderTaxRateGenerator;

/**
 * Order Tax Rate REST Controller Class
 *
 * Handles REST API endpoints for order tax line generation.
 *
 * @since 1.0.0
 */
class Order_Tax_Rate extends Controller {


	/**
	 * Get resource type name
	 *
	 * @return string Resource type.
	 */
	protected function get_resource_type(): string {
		return 'order_tax_rate';
	}

	/**
	 * Get resource type label
	 *
	 * @return string The translated label for the resource type.
	 */
	protected function get_resource_type_label(): string {
		return __( 'Order Tax Line', 'storeseeder' );
	}

	/**
	 * Get REST base for the endpoint
	 *
	 * @return string REST base.
	 */
	protected function get_rest_base(): string {
		return 'order_tax_rates';
	}

	/**
	 * Get generator instance
	 *
	 * @return OrderTaxRateGenerator Generator instance.
	 */
	protected function get_generator_instance(): OrderTaxRateGenerator {
		return new OrderTaxRateGenerator();
	}
}
