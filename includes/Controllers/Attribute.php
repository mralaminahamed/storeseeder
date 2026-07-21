<?php
/**
 * Attribute Generator REST Controller.
 *
 * @since   1.0.0
 * @package StoreSeeder\Controllers
 */

namespace StoreSeeder\Controllers;

use StoreSeeder\Abstracts\Controller;
use StoreSeeder\Abstracts\Generator;
use StoreSeeder\Generators\Attribute as AttributeGenerator;

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for attribute generation.
 *
 * @since 1.0.0
 */
class Attribute extends Controller {

	/**
	 * {@inheritDoc}
	 */
	protected function get_resource_type(): string {
		return 'attribute';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_resource_type_label(): string {
		return __( 'Attribute', 'storeseeder' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_rest_base(): string {
		return 'attributes';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_generator_instance(): Generator {
		return new AttributeGenerator();
	}
}
