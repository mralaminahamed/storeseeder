<?php
/**
 * Label REST Controller
 *
 * @since   2.4.0
 * @package FluentCartFakerPress\Controllers
 */

namespace FluentCartFakerPress\Controllers;

use FluentCartFakerPress\Abstracts\Controller;
use FluentCartFakerPress\Generators\Label as LabelGenerator;

/**
 * Label REST Controller Class
 *
 * Handles REST API endpoints for label generation.
 *
 * @since 1.0.0
 */
class Label extends Controller {


	/**
	 * Get resource type name
	 *
	 * @return string Resource type.
	 */
	protected function get_resource_type(): string {
		return 'label';
	}

	/**
	 * Get resource type label
	 *
	 * @return string The translated label for the resource type.
	 */
	protected function get_resource_type_label(): string {
		return __( 'Label', 'fluent-cart-fakerpress' );
	}

	/**
	 * Get REST base for the endpoint
	 *
	 * @return string REST base.
	 */
	protected function get_rest_base(): string {
		return 'labels';
	}

	/**
	 * Get generator instance
	 *
	 * @return LabelGenerator Generator instance.
	 */
	protected function get_generator_instance(): LabelGenerator {
		return new LabelGenerator();
	}
}
