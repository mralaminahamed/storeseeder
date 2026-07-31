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
			'zone_id'    => $this->zone_for( (array) $entity['regions'] ),
			'title'      => $entity['title'],
			'type'       => self::METHOD_TYPE[ $entity['type'] ] ?? 'fixed',
			// amount is a DECIMAL(10,2) of major currency units — Fluent Cart scales
			// it to cents at calculation time — so the canonical minor units are
			// divided back down here.
			'amount'     => round( (int) $entity['amount'] / 100, 2 ),
			'settings'   => array(
				'description' => $entity['description'],
				// The delivery estimate. `settings` is Fluent Cart's own blob for what a method
				// carries beyond its columns, which is where a window belongs — the title shows it
				// to a shopper, and this keeps the numbers readable by anything that looks.
				'delivery'    => array(
					'min_days' => (int) ( $entity['delivery_min'] ?? 0 ),
					'max_days' => (int) ( $entity['delivery_max'] ?? 0 ),
				),
			),
			'is_enabled' => $entity['enabled'],
			// Fluent Cart's `states` is a state list within the zone, not a country list, so the
			// canonical regions do not belong in it: a two-letter country code there matches no
			// state and silently narrows the method to nothing. Empty means every state in the zone,
			// which is what a country-level plan means here.
			'states'     => array(),
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
	 * The zone covering a set of countries, created if it does not exist yet.
	 *
	 * Fluent Cart zones speak three dialects: `all` for the whole world, a bare country code for one
	 * country, and `selection` with a country list in `meta`. Everything went into one "Worldwide
	 * Shipping" zone before this, so `coverage_areas` could not have been honoured even if anything
	 * had read it — every generated method was available everywhere.
	 *
	 * @since 1.1.0
	 *
	 * @param array<int, string> $regions Canonical region list; empty means worldwide.
	 *
	 * @return int Shipping zone ID.
	 */
	private function zone_for( array $regions ): int {
		if ( ! class_exists( ShippingZoneModel::class ) ) {
			return 1; // Fallback to zone ID 1 if the model is not available.
		}

		$countries = array();

		foreach ( $regions as $region ) {
			// `store_country` is the generator's placeholder for wherever this store sells from,
			// which only this side knows.
			$countries[] = 'store_country' === $region
				? $this->store_country()
				: strtoupper( (string) $region );
		}

		$countries = array_values( array_unique( array_filter( $countries ) ) );

		if ( array() === $countries ) {
			return $this->zone( 'all', __( 'Worldwide Shipping', 'storeseeder' ), array() );
		}

		if ( 1 === count( $countries ) ) {
			return $this->zone(
				$countries[0],
				sprintf(
					/* translators: %s: two-letter country code. */
					__( '%s Shipping', 'storeseeder' ),
					$countries[0]
				),
				array()
			);
		}

		sort( $countries );

		return $this->zone(
			'selection',
			sprintf(
				/* translators: %s: comma-separated country codes. */
				__( 'Shipping to %s', 'storeseeder' ),
				implode( ', ', $countries )
			),
			array(
				'countries'      => $countries,
				'selection_type' => 'included',
			)
		);
	}

	/**
	 * Find or create one zone.
	 *
	 * Matched on name as well as region, because two `selection` zones differ only by their country
	 * list — which lives in `meta`, where a query cannot reach it reliably.
	 *
	 * @since 1.1.0
	 *
	 * @param string               $region Fluent Cart's region value.
	 * @param string               $name   Zone name.
	 * @param array<string, mixed> $meta   Zone meta.
	 *
	 * @return int
	 */
	private function zone( string $region, string $name, array $meta ): int {
		$existing = ShippingZoneModel::query()
			->where( 'region', $region )
			->where( 'name', $name )
			->first();

		if ( $existing instanceof ShippingZoneModel ) {
			return (int) $existing->id;
		}

		$created = ShippingZoneModel::query()->create(
			array(
				'name'   => $name,
				'region' => $region,
				'meta'   => $meta,
				'order'  => 0,
			)
		);

		return $created instanceof ShippingZoneModel ? (int) $created->id : 1;
	}

	/**
	 * The country this store sells from.
	 *
	 * @since 1.1.0
	 *
	 * @return string Two-letter country code.
	 */
	private function store_country(): string {
		$base = (string) get_option( 'fluent_cart_store_country', '' );

		if ( '' === $base ) {
			$settings = (array) get_option( 'fluent_cart_settings', array() );
			$base     = (string) ( $settings['store_country'] ?? '' );
		}

		// WordPress has no store country of its own, so US is the fallback rather than a guess from
		// the site locale — a locale is a language, not a place of business.
		return '' !== $base ? strtoupper( $base ) : 'US';
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
