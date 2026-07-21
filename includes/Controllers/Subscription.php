<?php
/**
 * Subscription REST Controller
 *
 * @since   2.4.0
 * @package FluentCartFakerPress\Controllers
 */

namespace FluentCartFakerPress\Controllers;

use FluentCartFakerPress\Abstracts\Controller;
use FluentCartFakerPress\Generators\Subscription as SubscriptionGenerator;

/**
 * Subscription REST Controller Class
 *
 * Handles REST API endpoints for subscription generation.
 *
 * @since 2.4.0
 */
class Subscription extends Controller {


	/**
	 * Get resource type name
	 *
	 * @return string Resource type.
	 */
	protected function get_resource_type(): string {
		return 'subscription';
	}

	/**
	 * Get resource type label
	 *
	 * @return string The translated label for the resource type.
	 */
	protected function get_resource_type_label(): string {
		return __( 'Subscription', 'fluent-cart-fakerpress' );
	}

	/**
	 * Get REST base for the endpoint
	 *
	 * @return string REST base.
	 */
	protected function get_rest_base(): string {
		return 'subscriptions';
	}

	/**
	 * Get generator instance
	 *
	 * @return SubscriptionGenerator Generator instance.
	 */
	protected function get_generator_instance(): SubscriptionGenerator {
		return new SubscriptionGenerator();
	}
}
