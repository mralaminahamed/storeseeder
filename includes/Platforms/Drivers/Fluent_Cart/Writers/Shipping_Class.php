<?php
/**
 * Fluent Cart shipping class writer
 *
 * @since   1.1.0
 * @package StoreSeeder\Platforms\Drivers\Fluent_Cart\Writers
 */

namespace StoreSeeder\Platforms\Drivers\Fluent_Cart\Writers;

use Exception;
use FluentCart\App\Models\ShippingClass as ShippingClassModel;
use StoreSeeder\Platforms\Writer;
use StoreSeeder\Platforms\Resource;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Persists a canonical shipping class into Fluent Cart.
 *
 * @since 1.1.0
 */
final class Shipping_Class extends Writer {
	/**
	 * The resource this writer persists.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public function resource(): string {
		return Resource::SHIPPING_CLASS;
	}

	/**
	 * Create a Fluent Cart shipping class.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $entity Canonical shipping class entity.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function write( array $entity ) {
		if ( ! defined( 'FLUENTCART_VERSION' ) || ! class_exists( ShippingClassModel::class ) ) {
			return new WP_Error( 'missing_fluent_cart', __( 'Fluent Cart ShippingClass model not found. Please ensure Fluent Cart is active.', 'storeseeder' ) );
		}

		$data = array(
			'name'        => $this->unique_name( (string) $entity['name'] ),
			'description' => $entity['description'],
			// cost is DECIMAL(10,2) — dollars, not cents. Fluent Cart multiplies
			// by 100 when it applies the charge, so the canonical minor units are
			// divided back down here.
			'cost'        => round( (int) $entity['cost'] / 100, 2 ),
			'per_item'    => $entity['per_item'] ? 1 : 0,
			// 'fixed' is the column default and the only type Fluent Cart writes.
			'type'        => 'fixed',
		);

		$shipping_class = $this->create_shipping_class( $data );

		if ( ! $shipping_class ) {
			return new WP_Error( 'shipping_class_creation_failed', __( 'Failed to create shipping class.', 'storeseeder' ) );
		}

		$result = array(
			'id'         => $shipping_class->id,
			'name'       => $shipping_class->name,
			// cost is a DECIMAL of major currency units — Fluent Cart scales it
			// to cents at calculation time — so it is reported as-is.
			'cost'       => (float) $shipping_class->cost,
			'per_item'   => (int) $shipping_class->per_item,
			'created_at' => current_time( 'Y-m-d H:i:s' ),
		);

		/**
		 * Filters the shipping class generation result data.
		 *
		 * @since 1.0.0
		 * @hook  storeseeder_shipping_class_generation_result
		 *
		 * @param array $result The shipping class generation result data.
		 * @param int   $id     The created shipping class ID.
		 * @param array $data   The original data used for creation.
		 */
		return apply_filters( 'storeseeder_shipping_class_generation_result', $result, $shipping_class->id, $data );
	}

	/**
	 * Disambiguate a shipping class name against the ones already stored.
	 *
	 * @since 1.1.0
	 *
	 * @param string $base Name proposed by the generator.
	 *
	 * @return string Unused name.
	 */
	private function unique_name( string $base ): string {
		$name = $base;

		while ( ShippingClassModel::query()->where( 'name', $name )->exists() ) {
			$name = $base . ' ' . strtoupper( $this->faker()->bothify( '??#' ) );
		}

		return $name;
	}

	/**
	 * Create the shipping class in Fluent Cart.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $data Fluent-Cart-shaped data.
	 *
	 * @return ShippingClassModel|null Created shipping class instance.
	 */
	private function create_shipping_class( array $data ): ?ShippingClassModel {
		try {
			return ShippingClassModel::query()->create( $data );
		} catch ( Exception $e ) {
			return null;
		}
	}
}
