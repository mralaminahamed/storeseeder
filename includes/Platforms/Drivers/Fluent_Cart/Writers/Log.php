<?php
/**
 * Fluent Cart activity log writer
 *
 * @since   1.1.0
 * @package StoreSeeder\Platforms\Drivers\Fluent_Cart\Writers
 */

namespace StoreSeeder\Platforms\Drivers\Fluent_Cart\Writers;

use FluentCart\App\Models\Activity;
use StoreSeeder\Platforms\Writer;
use StoreSeeder\Platforms\Resource;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Persists a canonical log entry into Fluent Cart.
 *
 * @since 1.1.0
 */
final class Log extends Writer {
	/**
	 * Canonical resource to the model FQCN Fluent Cart stores in `module_type`.
	 *
	 * This column holds a literal class name, which is why the mapping has to live in
	 * the driver: the canonical entity says 'order', and only Fluent Cart knows that it
	 * writes that as FluentCart\App\Models\Order.
	 *
	 * @since 1.1.0
	 * @var array<string, string>
	 */
	private const MODULE_TYPE = array(
		Resource::ORDER        => 'FluentCart\\App\\Models\\Order',
		Resource::PRODUCT      => 'FluentCart\\App\\Models\\Product',
		Resource::COUPON       => 'FluentCart\\App\\Models\\Coupon',
		Resource::SUBSCRIPTION => 'FluentCart\\App\\Models\\Subscription',
		Resource::CUSTOMER     => 'FluentCart\\App\\Models\\Customer',
	);

	/**
	 * The resource this writer persists.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public function resource(): string {
		return Resource::LOG;
	}

	/**
	 * Create a Fluent Cart activity entry.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $entity Canonical log entity.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function write( array $entity ) {
		if ( ! defined( 'FLUENTCART_VERSION' ) || ! class_exists( Activity::class ) ) {
			return new WP_Error( 'missing_fluent_cart', __( 'Fluent Cart Activity model not found. Ensure Fluent Cart is active.', 'storeseeder' ) );
		}

		$module      = (string) $entity['module'];
		$module_type = self::MODULE_TYPE[ $module ] ?? '';

		if ( '' === $module_type ) {
			return new WP_Error(
				'storeseeder_unknown_log_module',
				sprintf(
					/* translators: %s: canonical resource name. */
					__( 'Fluent Cart has no model for log module "%s".', 'storeseeder' ),
					$module
				)
			);
		}

		$activity = Activity::query()->create(
			array(
				'status'      => $entity['severity'],
				'log_type'    => $entity['log_type'],
				'module_id'   => $entity['module_id'],
				'module_type' => $module_type,
				'module_name' => $module,
				'title'       => $entity['title'],
				'content'     => $entity['content'],
				'user_id'     => $entity['user_id'],
				'read_status' => $entity['read'] ? 'read' : 'unread',
				'created_by'  => 'FCT-BOT',
			)
		);

		if ( ! $activity || ! $activity->id ) {
			return new WP_Error( 'log_creation_failed', __( 'Failed to create activity log entry.', 'storeseeder' ) );
		}

		return array(
			'id'          => (int) $activity->id,
			'module_name' => $module,
			'module_type' => $module_type,
			'status'      => $entity['severity'],
			'log_type'    => $entity['log_type'],
			'title'       => $entity['title'],
		);
	}
}
