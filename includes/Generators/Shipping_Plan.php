<?php
/**
 * Shipping Plan Generator Class for Fluent Cart FakerPress Plugin
 *
 * @since      1.0.0
 * @subpackage Generators
 * @package    FluentCartFakerPress
 */

namespace FluentCartFakerPress\Generators;

use FluentCart\App\Models\ShippingMethod as ShippingMethodModel;
use FluentCart\App\Models\ShippingZone as ShippingZoneModel;
use FluentCartFakerPress\Abstracts\Generator;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Shipping Plan Generator Class
 *
 * Generates realistic fake shipping plan data for Fluent Cart testing and development.
 */
class Shipping_Plan extends Generator {


	/**
	 * Get the resource type name
	 *
	 * @return string Resource type name.
	 */
	protected function get_resource_type(): string {
		return 'shipping_plan';
	}

	/**
	 * Get supported data types for this generator.
	 *
	 * @return array Supported types
	 */
	public function get_supported_types(): array {
		return array(
			'shipping_plans' => 'Fluent Cart Shipping Plans',
		);
	}

	/**
	 * Get generator description.
	 *
	 * @return string Description
	 */
	public function get_description(): string {
		return 'Generates shipping plans with different methods, rates, and coverage areas for testing Fluent Cart shipping functionality.';
	}

	/**
	 * Generate a single shipping plan
	 *
	 * @return WP_Error|array Single shipping plan data, error, or false on failure.
	 */
	protected function generate_single_item() {
		// Check if Fluent Cart ShippingMethod model is available.
		if ( ! class_exists( ShippingMethodModel::class ) ) {
			return new WP_Error( 'missing_model', __( 'Fluent Cart ShippingMethod model not found. Please ensure Fluent Cart plugin is active.', 'fluent-cart-fakerpress' ) );
		}

		$plan_data       = $this->generate_shipping_plan_data();
		$shipping_method = $this->create_shipping_method( $plan_data );

		if ( ! $shipping_method ) {
			return new WP_Error( 'shipping_method_creation_failed', __( 'Failed to create shipping method.', 'fluent-cart-fakerpress' ) );
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

		/**
		 * Filters the shipping method generation result data.
		 *
		 * Allows developers to modify the returned shipping method data after generation.
		 *
		 * @since 1.0.0
		 * @hook  fluent_cart_fakerpress_shipping_method_generation_result
		 *
		 * @param array $result          The shipping method generation result data.
		 * @param int   $method_id       The created shipping method ID.
		 * @param array $plan_data       The original shipping method data used for creation.
		 */
		return apply_filters( 'fluent_cart_fakerpress_shipping_method_generation_result', $result, $shipping_method->id, $plan_data );
	}

	/**
	 * Generate shipping method data
	 *
	 * @return array Shipping method data
	 */
	private function generate_shipping_plan_data(): array {
		$types = array( 'flat_rate', 'free_shipping', 'local_pickup' );
		$type  = $this->get_faker()->randomElement( $types );

		$amount = 0;
		if ( 'flat_rate' === $type ) {
			$amount = $this->get_faker()->randomFloat( 2, 5, 50 );
		}

		$zone_id = $this->get_or_create_shipping_zone();

		return array(
			'zone_id'    => $zone_id,
			'title'      => implode( ' ', (array) $this->get_faker()->words( 3, true ) ) . ' Shipping',
			'type'       => $type,
			'amount'     => $amount,
			'settings'   => array(
				'description' => $this->get_faker()->sentence( 6 ),
			),
			'is_enabled' => $this->get_faker()->boolean( 85 ), // 85% chance of being enabled.
			'states'     => array(), // Empty array means all states in the zone.
		);
	}

	/**
	 * Get or create a shipping zone for the shipping method
	 *
	 * @return int Shipping zone ID
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
	 * Create shipping method in Fluent Cart
	 *
	 * @param array $data Shipping method data.
	 *
	 * @return ShippingMethodModel|null Created shipping method instance
	 */
	private function create_shipping_method( array $data ): ?ShippingMethodModel {
		try {
			$shipping_method = ShippingMethodModel::query()->create( $data );
			return $shipping_method;
		} catch ( \Exception $e ) {
			return null;
		}
	}
}
