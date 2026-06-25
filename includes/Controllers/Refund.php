<?php
/**
 * Refund Generator REST Controller.
 *
 * @since   1.0.0
 * @package FluentCartFakerPress\Controllers
 */

namespace FluentCartFakerPress\Controllers;

use FluentCartFakerPress\Abstracts\Controller;
use FluentCartFakerPress\Abstracts\Generator;
use FluentCartFakerPress\Generators\Refund as RefundGenerator;

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
		return __( 'Refund', 'fluent-cart-fakerpress' );
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
