<?php
/**
 * Log Generator REST Controller.
 *
 * @since   1.0.0
 * @package FluentCartFakerPress\Controllers
 */

namespace FluentCartFakerPress\Controllers;

use FluentCartFakerPress\Abstracts\Controller;
use FluentCartFakerPress\Abstracts\Generator;
use FluentCartFakerPress\Generators\Log as LogGenerator;

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for activity log generation.
 *
 * @since 1.0.0
 */
class Log extends Controller {

	/**
	 * {@inheritDoc}
	 */
	protected function get_resource_type(): string {
		return 'log';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_resource_type_label(): string {
		return __( 'Log', 'fluent-cart-fakerpress' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_rest_base(): string {
		return 'logs';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_generator_instance(): Generator {
		return new LogGenerator();
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_resource_specific_params(): array {
		return array(
			'log_types' => array(
				'description'       => __( 'Severity types to generate.', 'fluent-cart-fakerpress' ),
				'type'              => 'array',
				'items'             => array(
					'type' => 'string',
					'enum' => array( 'info', 'success', 'warning', 'error' ),
				),
				'default'           => array( 'info', 'success', 'warning', 'error' ),
				'sanitize_callback' => array( $this, 'sanitize_array' ),
			),
		);
	}
}
