<?php
/**
 * Log (Activity) Generator.
 *
 * @since   1.0.0
 * @package StoreSeeder\Generation\Generators
 */

namespace StoreSeeder\Generation\Generators;

defined( 'ABSPATH' ) || exit;

use StoreSeeder\Generation\Generator;
use StoreSeeder\Platforms\Resource;

/**
 * Shapes activity log entries.
 *
 * @since 1.0.0
 */
class Log extends Generator {

	/**
	 * Resources an entry can be about.
	 *
	 * Canonical resource names, not model class names. The platform's own type token —
	 * Fluent Cart stores a model FQCN in this column, WooCommerce would not — is the
	 * writer's business.
	 *
	 * @var string[]
	 */
	private const MODULES = array(
		Resource::ORDER,
		Resource::PRODUCT,
		Resource::COUPON,
		Resource::SUBSCRIPTION,
		Resource::CUSTOMER,
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
			'info'    => __( 'Info', 'storeseeder' ),
			'warning' => __( 'Warning', 'storeseeder' ),
			'error'   => __( 'Error', 'storeseeder' ),
			'success' => __( 'Success', 'storeseeder' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return 'Generates activity log entries across orders, products, customers, coupons, and subscriptions with realistic severities and module references.';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function build_entity() {
		$module   = $this->get_faker()->randomElement( self::MODULES );
		$severity = $this->get_faker()->randomElement(
			$this->generation_params['log_types'] ?? self::SEVERITIES
		);
		$log_type = $this->get_faker()->randomElement( self::LOG_TYPES );
		$title    = sprintf( $this->get_faker()->randomElement( self::TITLES ), ucfirst( $module ) );

		return array(
			'module'    => $module,
			'severity'  => $severity,
			'log_type'  => $log_type,
			'title'     => $title,
			'module_id' => $this->get_faker()->numberBetween( 1, 9999 ),
			'content'   => $this->get_faker()->sentence( 10 ),
			'read'      => $this->get_faker()->boolean( 40 ),
			'user_id'   => max( 1, get_current_user_id() ),
		);
	}
}
