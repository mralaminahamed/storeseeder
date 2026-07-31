<?php
/**
 * Fluent Cart shipping plan writer
 *
 * @since   1.1.0
 * @package StoreSeeder\Platforms\Drivers\Fluent_Cart\Writers
 */

namespace StoreSeeder\Platforms\Drivers\Fluent_Cart\Writers;

use Exception;
use FluentCart\App\Models\ShippingMethod as ShippingMethodModel;
use FluentCart\App\Models\ShippingZone as ShippingZoneModel;
use StoreSeeder\Platforms\Writer;
use StoreSeeder\Platforms\Resource;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Persists a canonical shipping plan into Fluent Cart.
 *
 * @since 1.1.0
 */
final class Shipping_Plan extends Writer {
	/**
	 * Canonical method type to Fluent Cart's spelling.
	 *
	 * Fluent Cart recognises 'fixed' (charged by the stored amount) and
	 * 'free_shipping'. 'flat_rate' and 'local_pickup' are not method types it reads —
	 * CartHelper only special-cases 'free_shipping' and charges every other method by
	 * its amount, so those strings produced methods the admin UI could not edit.
	 *
	 * @since 1.1.0
	 * @var array<string, string>
	 */
	private const METHOD_TYPE = array(
		'flat_rate'     => 'fixed',
		'free_shipping' => 'free_shipping',
	);

	/**
	 * The resource this writer persists.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public function resource(): string {
		return Resource::SHIPPING_PLAN;
	}

	/**
	 * Create a Fluent Cart shipping method.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $entity Canonical shipping plan entity.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function write( array $entity ) {
		if ( ! class_exists( ShippingMethodModel::class ) ) {
			return new WP_Error( 'missing_model', __( 'Fluent Cart ShippingMethod model not found. Please ensure Fluent Cart plugin is active.', 'storeseeder' ) );
		}

		$plan_data = array(
			'zone_id'    => $this->get_or_create_shipping_zone(),
			'title'      => $entity['title'],
			'type'       => self::METHOD_TYPE[ $entity['type'] ] ?? 'fixed',
			// amount is a DECIMAL(10,2) of major currency units — Fluent Cart scales
			// it to cents at calculation time — so the canonical minor units are
			// divided back down here.
			'amount'     => round( (int) $entity['amount'] / 100, 2 ),
			'settings'   => array(
				'description' => $entity['description'],
			),
			'is_enabled' => $entity['enabled'],
			'states'     => $entity['regions'], // Empty array means all states in the zone.
		);

		$shipping_method = $this->create_shipping_method( $plan_data );

		if ( ! $shipping_method ) {
			return new WP_Error( 'shipping_method_creation_failed', __( 'Failed to create shipping method.', 'storeseeder' ) );
		}

		$result = array(
			'id'         => $shipping_method->id,
			'title'      => $shipping_method->title,
			'type'       => $shipping_method->type,
			'amount'     => $shipping_method->amount,
			'is_enabled' => $shipping_method->is_enabled,
			'zone_id'    => $shipping_method->zone_id,
			'created_at' => $shipping_method->created_at,
		);

		$result = $this->filter_result( $result, $shipping_method->id, $plan_data );

		/**
		 * Filters the shipping-plan generation result data.
		 *
		 * @since      1.0.0
		 * @deprecated 1.1.0 Use storeseeder_shipping_plan_generation_result, which matches the
		 *             canonical resource name. This one is fired afterwards so callbacks
		 *             registered against it keep running; it will go in a future major.
		 *
		 * @param array<string, mixed> $result The generation result data.
		 * @param int                  $id     The created shipping method id.
		 * @param array<string, mixed> $data   The data used for creation.
		 */
		return (array) apply_filters(
			'storeseeder_shipping_method_generation_result',
			$result,
			$shipping_method->id,
			$plan_data
		);
	}

	/**
	 * Get or create a shipping zone for the shipping method.
	 *
	 * @since 1.1.0
	 *
	 * @return int Shipping zone ID.
	 */
	private function get_or_create_shipping_zone(): int {
		// Check if ShippingZone model is available.
		if ( ! class_exists( ShippingZoneModel::class ) ) {
			return 1; // Fallback to zone ID 1 if model not available.
		}

		// Try to find an existing zone, or create a default one.
		$zone = ShippingZoneModel::query()->where( 'region', 'all' )->first();

		if ( ! $zone ) {
			$zone = ShippingZoneModel::query()->create(
				array(
					'name'   => 'Worldwide Shipping',
					'region' => 'all',
					'order'  => 0,
				)
			);
		}

		return $zone->id;
	}

	/**
	 * Create shipping method in Fluent Cart.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $data Fluent-Cart-shaped shipping method data.
	 *
	 * @return ShippingMethodModel|null Created shipping method instance.
	 */
	private function create_shipping_method( array $data ): ?ShippingMethodModel {
		try {
			$created = ShippingMethodModel::query()->create( $data );

			// Eloquent's create() is reached through __callStatic, so its declared type is
			// Builder|Model rather than this model. Narrowing here is what makes the
			// nullable return type above true rather than merely intended.
			return $created instanceof ShippingMethodModel ? $created : null;
		} catch ( Exception $e ) {
			return null;
		}
	}

	/**
	 * Remove a generated shipping method.
	 *
	 * The zone survives: the writer reuses an existing one where it can, so deleting it
	 * would take out methods it never created.
	 *
	 * @since 1.1.0
	 *
	 * @param int|string $id The identifier reported when the row was created.
	 *
	 * @return true|WP_Error
	 */
	public function delete( $id ) {
		return $this->delete_model( ShippingMethodModel::class, $id );
	}
}
