<?php
/**
 * License REST Controller
 *
 * @since   1.1.0
 * @package StoreSeeder\Rest\Controllers
 */

namespace StoreSeeder\Rest\Controllers;

use StoreSeeder\Generation\Generators\License as LicenseGenerator;
use StoreSeeder\Rest\Controller;

/**
 * License REST Controller Class
 *
 * Handles REST API endpoints for licence generation. Whether the target platform can store a
 * licence is answered by the capability matrix before this controller writes anything — on
 * Fluent Cart it needs Pro — so a request against a site without it gets a 400 naming the
 * plugin rather than a failure per item.
 *
 * @since 1.1.0
 */
class License extends Controller {

	/**
	 * Get resource type name
	 *
	 * @return string Resource type.
	 */
	protected function get_resource_type(): string {
		return 'license';
	}

	/**
	 * Get resource type label
	 *
	 * @return string The translated label for the resource type.
	 */
	protected function get_resource_type_label(): string {
		return __( 'License', 'storeseeder' );
	}

	/**
	 * Get REST base for the endpoint
	 *
	 * @return string REST base.
	 */
	protected function get_rest_base(): string {
		return 'licenses';
	}

	/**
	 * Get generator instance
	 *
	 * @return LicenseGenerator Generator instance.
	 */
	protected function get_generator_instance(): LicenseGenerator {
		return new LicenseGenerator();
	}
}
