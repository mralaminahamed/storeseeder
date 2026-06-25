<?php
/**
 * Tax Class Generator Class for Fluent Cart FakerPress Plugin
 *
 * @since      1.0.0
 * @subpackage Generators
 * @package    FluentCartFakerPress
 */

namespace FluentCartFakerPress\Generators;

use FluentCart\App\Models\TaxClass as TaxClassModel;
use FluentCartFakerPress\Abstracts\Generator;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Tax Class Generator Class
 *
 * Generates realistic fake tax class data for Fluent Cart testing and development.
 */
class Tax_Class extends Generator {


	/**
	 * Get the resource type name
	 *
	 * @return string Resource type name.
	 */
	protected function get_resource_type(): string {
		return 'tax_class';
	}

	/**
	 * Get supported data types for this generator.
	 *
	 * @return array Supported types
	 */
	public function get_supported_types(): array {
		return array(
			'tax_classes' => 'Fluent Cart Tax Classes',
		);
	}

	/**
	 * Get generator description.
	 *
	 * @return string Description
	 */
	public function get_description(): string {
		return 'Generates tax classes with rates and rules for testing Fluent Cart tax calculation functionality.';
	}

	/**
	 * Generate a single tax class
	 *
	 * @return WP_Error|array Single tax class data, error, or false on failure.
	 */
	protected function generate_single_item() {
		// Check if Fluent Cart is active.
		if ( ! defined( 'FLUENTCART_VERSION' ) ) {
			return new WP_Error( 'missing_fluent_cart', __( 'Fluent Cart plugin not found. Please ensure Fluent Cart is active.', 'fluent-cart-fakerpress' ) );
		}

		$tax_data = $this->generate_tax_class_data();
		$tax_id   = $this->create_tax_class( $tax_data );

		if ( is_wp_error( $tax_id ) ) {
			return $tax_id;
		}

		if ( ! $tax_id ) {
			return new WP_Error( 'tax_class_creation_failed', __( 'Failed to create tax class.', 'fluent-cart-fakerpress' ) );
		}

		$result = array(
			'id'         => $tax_id,
			'name'       => $tax_data['name'],
			'rate'       => $tax_data['rate'],
			'country'    => $tax_data['country'],
			'status'     => $tax_data['status'],
			'created_at' => current_time( 'Y-m-d H:i:s' ),
		);

		/**
		 * Filters the tax class generation result data.
		 *
		 * Allows developers to modify the returned tax class data after generation.
		 *
		 * @since 1.0.0
		 * @hook  fluent_cart_fakerpress_tax_class_generation_result
		 *
		 * @param array $result    The tax class generation result data.
		 * @param int   $tax_id    The created tax class ID.
		 * @param array $tax_data  The original tax class data used for creation.
		 */
		return apply_filters( 'fluent_cart_fakerpress_tax_class_generation_result', $result, $tax_id, $tax_data );
	}

	/**
	 * Generate tax class data
	 *
	 * @return array Tax class data
	 */
	private function generate_tax_class_data(): array {
		return array(
			'name'    => implode( ' ', (array) $this->get_faker()->words( 2, true ) ) . ' Tax',
			'rate'    => $this->get_faker()->randomFloat( 2, 0, 25 ),
			'country' => $this->get_faker()->countryCode(),
			'state'   => strtoupper( $this->get_faker()->lexify( '??' ) ),
			'status'  => 'active',
		);
	}

	/**
	 * Create tax class in Fluent Cart
	 *
	 * @param array $data Tax class data.
	 *
	 * @return int|WP_Error|null Created tax class ID or error
	 */
	private function create_tax_class( array $data ) {
		// Check if Fluent Cart TaxClass model is available.
		if ( ! class_exists( TaxClassModel::class ) ) {
			return new WP_Error( 'missing_model', __( 'Fluent Cart TaxClass model not found. Please ensure Fluent Cart plugin is active.', 'fluent-cart-fakerpress' ) );
		}

		// Prepare tax class data for Fluent Cart TaxClass model.
		$tax_class_data = array(
			'title'       => $data['name'],
			'description' => '',
			'slug'        => sanitize_title( $data['name'] ),
			'meta'        => array(
				'rate'    => $data['rate'],
				'country' => $data['country'],
				'state'   => $data['state'],
				'status'  => $data['status'],
			),
		);

		// Create tax class using Fluent Cart TaxClass model.
		$tax_class = TaxClassModel::create( $tax_class_data );

		if ( is_wp_error( $tax_class ) ) {
			return $tax_class;
		}

		if ( ! $tax_class ) {
			return new WP_Error( 'tax_class_creation_failed', __( 'Failed to create tax class using Fluent Cart model.', 'fluent-cart-fakerpress' ) );
		}

		return $tax_class->id;
	}
}
