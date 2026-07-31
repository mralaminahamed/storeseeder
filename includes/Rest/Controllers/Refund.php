<?php
/**
 * Refund Generator REST Controller.
 *
 * @since   1.0.0
 * @package StoreSeeder\Rest\Controllers
 */

namespace StoreSeeder\Rest\Controllers;

use StoreSeeder\Rest\Controller;
use StoreSeeder\Generation\Generator;
use StoreSeeder\Generation\Generators\Refund as RefundGenerator;

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for refund generation.
 *
 * @since 1.0.0
 */
class Refund extends Controller {

	/**
	 * {@inheritDoc}
	 */
	protected function get_resource_type(): string {
		return 'refund';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_resource_type_label(): string {
		return __( 'Refund', 'storeseeder' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_rest_base(): string {
		return 'refunds';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_generator_instance(): Generator {
		return new RefundGenerator();
	}
}
