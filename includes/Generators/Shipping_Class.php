<?php
/**
 * Shipping Class Generator Class for Fluent Cart FakerPress Plugin
 *
 * @since      2.4.0
 * @subpackage Generators
 * @package    FluentCartFakerPress
 */

namespace FluentCartFakerPress\Generators;

use FluentCart\App\Models\ShippingClass as ShippingClassModel;
use FluentCartFakerPress\Abstracts\Generator;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Shipping Class Generator Class
 *
 * Generates shipping classes used to group products with similar shipping
 * requirements for testing Fluent Cart shipping functionality.
 */
class Shipping_Class extends Generator {


	/**
	 * Representative shipping class names.
	 *
	 * @var string[]
	 */
	private const CLASS_NAMES = array(
		'Standard',
		'Bulky Items',
		'Fragile Goods',
		'Heavy',
		'Oversized',
		'Lightweight',
		'Hazardous',
		'Refrigerated',
		'Express Only',
		'Flat Pack',
	);

	/**
	 * Get the resource type name
	 *
	 * @return string Resource type name.
	 */
	protected function get_resource_type(): string {
		return 'shipping_class';
	}

	/**
	 * Get supported data types for this generator.
	 *
	 * @return array Supported types
	 */
	public function get_supported_types(): array {
		return array(
			'shipping_classes' => 'Fluent Cart Shipping Classes',
		);
	}

	/**
	 * Get generator description.
	 *
	 * @return string Description
	 */
	public function get_description(): string {
		return 'Generates shipping classes that group products with similar shipping requirements for testing Fluent Cart shipping rate functionality.';
	}

	/**
	 * Generate a single shipping class
	 *
	 * @return WP_Error|array Single shipping class data, error, or false on failure.
	 */
	protected function generate_single_item() {
		if ( ! defined( 'FLUENTCART_VERSION' ) || ! class_exists( ShippingClassModel::class ) ) {
			return new WP_Error( 'missing_fluent_cart', __( 'Fluent Cart ShippingClass model not found. Please ensure Fluent Cart is active.', 'fluent-cart-fakerpress' ) );
		}

		$data = $this->generate_shipping_class_data();

		$shipping_class = $this->create_shipping_class( $data );

		if ( ! $shipping_class ) {
			return new WP_Error( 'shipping_class_creation_failed', __( 'Failed to create shipping class.', 'fluent-cart-fakerpress' ) );
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
		 * @since 2.4.0
		 * @hook  fluent_cart_fakerpress_shipping_class_generation_result
		 *
		 * @param array $result The shipping class generation result data.
		 * @param int   $id     The created shipping class ID.
		 * @param array $data   The original data used for creation.
		 */
		return apply_filters( 'fluent_cart_fakerpress_shipping_class_generation_result', $result, $shipping_class->id, $data );
	}

	/**
	 * Generate shipping class data.
	 *
	 * @return array Shipping class data.
	 */
	private function generate_shipping_class_data(): array {
		return array(
			'name'        => $this->unique_name(),
			'description' => $this->get_faker()->sentence( 8 ),
			// cost is DECIMAL(10,2) — dollars, not cents. Fluent Cart multiplies
			// by 100 when it applies the charge.
			'cost'        => $this->get_faker()->randomFloat( 2, 0, 25 ),
			'per_item'    => $this->get_faker()->boolean( 40 ) ? 1 : 0,
			// 'fixed' is the column default and the only type Fluent Cart writes.
			'type'        => 'fixed',
		);
	}

	/**
	 * Build a shipping class name not already taken.
	 *
	 * @return string Unused name.
	 */
	private function unique_name(): string {
		$base = $this->get_faker()->randomElement( self::CLASS_NAMES );

		$name = $base;
		while ( ShippingClassModel::query()->where( 'name', $name )->exists() ) {
			$name = $base . ' ' . strtoupper( $this->get_faker()->bothify( '??#' ) );
		}

		return $name;
	}

	/**
	 * Create the shipping class in Fluent Cart.
	 *
	 * @param array $data Shipping class data.
	 *
	 * @return ShippingClassModel|null Created shipping class instance.
	 */
	private function create_shipping_class( array $data ): ?ShippingClassModel {
		try {
			return ShippingClassModel::query()->create( $data );
		} catch ( \Exception $e ) {
			return null;
		}
	}
}
