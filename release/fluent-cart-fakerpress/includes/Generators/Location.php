<?php
/**
 * Location Generator Class for Fluent Cart FakerPress Plugin
 *
 * @since      1.0.0
 * @subpackage Generators
 * @package    FluentCartFakerPress
 */

namespace FluentCartFakerPress\Generators;

use FluentCartFakerPress\Abstracts\Generator;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Location Generator Class
 *
 * Generates realistic fake location data for Fluent Cart testing and development.
 */
class Location extends Generator {


	/**
	 * Get the resource type name
	 *
	 * @return string Resource type name.
	 */
	protected function get_resource_type(): string {
		return 'location';
	}

	/**
	 * Get supported data types for this generator.
	 *
	 * @return array Supported types
	 */
	public function get_supported_types(): array {
		return array(
			'locations' => 'Geographic Locations',
		);
	}

	/**
	 * Get generator description.
	 *
	 * @return string Description
	 */
	public function get_description(): string {
		return 'Generates geographic location data including countries, states, and cities for testing Fluent Cart shipping and tax functionality.';
	}

	/**
	 * Generate a single location
	 *
	 * @return WP_Error|array Single location data, error, or false on failure.
	 */
	protected function generate_single_item() {
		$location_data = $this->generate_location_data();
		$location_id   = $this->create_location( $location_data );

		if ( ! $location_id ) {
			return new WP_Error( 'location_creation_failed', __( 'Failed to create location.', 'fluent-cart-fakerpress' ) );
		}

		$result = array(
			'id'         => $location_id,
			'country'    => $location_data['country'],
			'state'      => $location_data['state'],
			'city'       => $location_data['city'],
			'created_at' => current_time( 'Y-m-d H:i:s' ),
		);

		/**
		 * Filters the location generation result data.
		 *
		 * Allows developers to modify the returned location data after generation.
		 *
		 * @since 1.0.0
		 * @hook  fluent_cart_fakerpress_location_generation_result
		 *
		 * @param array $result        The location generation result data.
		 * @param int   $location_id   The created location ID.
		 * @param array $location_data The original location data used for creation.
		 */
		return apply_filters( 'fluent_cart_fakerpress_location_generation_result', $result, $location_id, $location_data );
	}

	/**
	 * Generate location data
	 *
	 * @return array Location data
	 */
	private function generate_location_data(): array {
		return array(
			'country' => $this->get_faker()->country(),
			'state'   => $this->get_faker()->city(),
			'city'    => $this->get_faker()->city(),
			'zip'     => $this->get_faker()->postcode(),
		);
	}

	/**
	 * Create location in Fluent Cart
	 *
	 * @param array $data Location data.
	 *
	 * @return int|null Created location ID
	 */
	private function create_location( array $data ): ?int {
		// Use Fluent Cart's location creation API if available.
		if ( function_exists( 'fluentCartCreateLocation' ) ) {
			return fluentCartCreateLocation( $data );
		}

		// Fallback: create as WordPress post.
		$post_data = array(
			'post_title'   => $data['city'] . ', ' . $data['state'] . ', ' . $data['country'],
			'post_content' => '',
			'post_status'  => 'publish',
			'post_type'    => 'fluentcart_location',
			'meta_input'   => array(
				'_country' => $data['country'],
				'_state'   => $data['state'],
				'_city'    => $data['city'],
				'_zip'     => $data['zip'],
			),
		);

		$location_id = wp_insert_post( $post_data );

		return $location_id ? $location_id : null;
	}
}
