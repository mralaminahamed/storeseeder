<?php
/**
 * WooCommerce shipping class writer
 *
 * @since   1.1.0
 * @package StoreSeeder\Platforms\Drivers\Woo_Commerce\Writers
 */

namespace StoreSeeder\Platforms\Drivers\Woo_Commerce\Writers;

use StoreSeeder\Platforms\Drivers\Woo_Commerce\Writer;
use StoreSeeder\Platforms\Resource;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Persists a canonical shipping class into WooCommerce.
 *
 * A WooCommerce shipping class is a `product_shipping_class` term and nothing more. The cost
 * the entity carries is deliberately not stored on it: in WooCommerce a class cost belongs to
 * a *shipping method* — `class_cost_{term_id}` in the flat-rate instance's settings — so the
 * same class costs different amounts in different zones. Writing it here would invent a field
 * WooCommerce does not read.
 *
 * @since 1.1.0
 */
final class Shipping_Class extends Writer {
	/**
	 * The taxonomy WooCommerce keeps shipping classes in.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	const TAXONOMY = 'product_shipping_class';

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
	 * Create a WooCommerce shipping class.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $entity Canonical shipping-class entity.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function write( array $entity ) {
		if ( ! taxonomy_exists( self::TAXONOMY ) ) {
			return new WP_Error(
				'missing_woocommerce',
				__( 'WooCommerce is not active on this site.', 'storeseeder' )
			);
		}

		$name = $this->unique_name( (string) $entity['name'] );

		$term = wp_insert_term(
			$name,
			self::TAXONOMY,
			array(
				'description' => (string) $entity['description'],
				'slug'        => sanitize_title( $name ),
			)
		);

		if ( is_wp_error( $term ) ) {
			return $term;
		}

		$id = (int) $term['term_id'];

		$result = array(
			'id'          => $id,
			'name'        => $name,
			'slug'        => sanitize_title( $name ),
			'description' => (string) $entity['description'],
			// Reported for what it is: the generated figure, applied per shipping method
			// rather than stored on the class.
			'cost_hint'   => $this->to_decimal( (int) $entity['cost'] ),
			'created_at'  => current_time( 'Y-m-d H:i:s' ),
		);

		return $this->filter_result( $result, $id, $result );
	}

	/**
	 * Remove a generated shipping class.
	 *
	 * @since 1.1.0
	 *
	 * @param int|string $id Term id.
	 *
	 * @return true|WP_Error
	 */
	public function delete( $id ) {
		if ( ! taxonomy_exists( self::TAXONOMY ) ) {
			return new WP_Error(
				'storeseeder_delete_unsupported',
				__( 'WooCommerce is not active on this site.', 'storeseeder' )
			);
		}

		$deleted = wp_delete_term( (int) $id, self::TAXONOMY );

		if ( is_wp_error( $deleted ) ) {
			return $deleted;
		}

		return true;
	}

	/**
	 * A class name not already taken.
	 *
	 * The generator draws from a short list of realistic names, so a second run collides by
	 * design. `wp_insert_term()` refuses a duplicate name in the same taxonomy, which would
	 * otherwise fail every item after the first few.
	 *
	 * @since 1.1.0
	 *
	 * @param string $proposed Name the generator proposed.
	 *
	 * @return string
	 */
	private function unique_name( string $proposed ): string {
		$name = $proposed;

		for ( $attempt = 2; $attempt <= 40; $attempt++ ) {
			if ( ! term_exists( $name, self::TAXONOMY ) ) {
				return $name;
			}

			$name = $proposed . ' ' . $attempt;
		}

		return $proposed . ' ' . strtoupper( substr( md5( (string) wp_generate_uuid4() ), 0, 4 ) );
	}
}
