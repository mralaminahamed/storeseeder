<?php
/**
 * Log (Activity) Generator.
 *
 * @since   1.0.0
 * @package FluentCartFakerPress\Generators
 */

namespace FluentCartFakerPress\Generators;

defined( 'ABSPATH' ) || exit;

use FluentCart\App\Models\Activity;
use FluentCartFakerPress\Abstracts\Generator;
use WP_Error;

/**
 * Generates Fluent Cart activity log entries.
 *
 * @since 1.0.0
 */
class Log extends Generator {

	/**
	 * Module noun => model FQCN.
	 *
	 * @var array<string, string>
	 */
	private const MODULES = array(
		'order'        => 'FluentCart\\App\\Models\\Order',
		'product'      => 'FluentCart\\App\\Models\\Product',
		'coupon'       => 'FluentCart\\App\\Models\\Coupon',
		'subscription' => 'FluentCart\\App\\Models\\Subscription',
		'customer'     => 'FluentCart\\App\\Models\\Customer',
	);

	/**
	 * Severity pool for the `status` column.
	 *
	 * @var string[]
	 */
	private const SEVERITIES = array( 'info', 'info', 'info', 'success', 'warning', 'error' );

	/**
	 * Category pool for the `log_type` column.
	 *
	 * @var string[]
	 */
	private const LOG_TYPES = array( 'activity', 'api', 'payment', 'webhook' );

	/**
	 * Title templates.
	 *
	 * @var string[]
	 */
	private const TITLES = array(
		'%s created',
		'%s updated',
		'%s deleted',
		'%s payment processed',
		'%s refunded',
		'%s status changed',
	);

	/**
	 * {@inheritDoc}
	 */
	protected function get_resource_type(): string {
		return 'log';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_supported_types(): array {
		return array(
			'info'    => __( 'Info', 'fluent-cart-fakerpress' ),
			'warning' => __( 'Warning', 'fluent-cart-fakerpress' ),
			'error'   => __( 'Error', 'fluent-cart-fakerpress' ),
			'success' => __( 'Success', 'fluent-cart-fakerpress' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return 'Generates Fluent Cart activity log entries across orders, products, customers, coupons, and subscriptions with realistic severities and module references.';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function generate_single_item() {
		if ( ! defined( 'FLUENTCART_VERSION' ) || ! class_exists( Activity::class ) ) {
			return new WP_Error( 'missing_fluent_cart', __( 'Fluent Cart Activity model not found. Ensure Fluent Cart is active.', 'fluent-cart-fakerpress' ) );
		}

		$module_names = array_keys( self::MODULES );
		$module_name  = $this->get_faker()->randomElement( $module_names );
		$module_type  = self::MODULES[ $module_name ];
		$severity     = $this->get_faker()->randomElement(
			$this->generation_params['log_types'] ?? self::SEVERITIES
		);
		$log_type     = $this->get_faker()->randomElement( self::LOG_TYPES );
		$title        = sprintf( $this->get_faker()->randomElement( self::TITLES ), ucfirst( $module_name ) );
		$user_id      = max( 1, get_current_user_id() );

		$activity = Activity::query()->create(
			array(
				'status'      => $severity,
				'log_type'    => $log_type,
				'module_id'   => $this->get_faker()->numberBetween( 1, 9999 ),
				'module_type' => $module_type,
				'module_name' => $module_name,
				'title'       => $title,
				'content'     => $this->get_faker()->sentence( 10 ),
				'user_id'     => $user_id,
				'read_status' => $this->get_faker()->boolean( 40 ) ? 'read' : 'unread',
				'created_by'  => 'FCT-BOT',
			)
		);

		if ( ! $activity || ! $activity->id ) {
			return new WP_Error( 'log_creation_failed', __( 'Failed to create activity log entry.', 'fluent-cart-fakerpress' ) );
		}

		return array(
			'id'          => (int) $activity->id,
			'module_name' => $module_name,
			'module_type' => $module_type,
			'status'      => $severity,
			'log_type'    => $log_type,
			'title'       => $title,
		);
	}
}
