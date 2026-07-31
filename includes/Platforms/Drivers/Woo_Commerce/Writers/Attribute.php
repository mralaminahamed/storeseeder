<?php
/**
 * WooCommerce attribute writer
 *
 * @since   1.1.0
 * @package StoreSeeder\Platforms\Drivers\Woo_Commerce\Writers
 */

namespace StoreSeeder\Platforms\Drivers\Woo_Commerce\Writers;

use StoreSeeder\Platforms\Drivers\Woo_Commerce\Writer;
use StoreSeeder\Platforms\Resource;
use WC_Product_Attribute;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Persists a canonical attribute group into WooCommerce as a global attribute.
 *
 * A WooCommerce global attribute is a row in `wc_attribute_taxonomies` plus a taxonomy named
 * `pa_{slug}` holding its terms. Registering the taxonomy immediately matters: `wc_create_attribute()`
 * records the attribute but the taxonomy is only registered on the next request, and
 * `wp_insert_term()` against an unregistered taxonomy fails — so the terms this writer creates
 * would silently be none.
 *
 * The attribute is then attached to real products, because an attribute attached to nothing is
 * invisible everywhere except the attributes screen.
 *
 * @since 1.1.0
 */
final class Attribute extends Writer {
	/**
	 * The resource this writer persists.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public function resource(): string {
		return Resource::ATTRIBUTE;
	}

	/**
	 * Create a WooCommerce global attribute with its terms.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $entity Canonical attribute entity.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function write( array $entity ) {
		if ( ! function_exists( 'wc_create_attribute' ) ) {
			return new WP_Error(
				'missing_woocommerce',
				__( 'WooCommerce is not active on this site.', 'storeseeder' )
			);
		}

		$name = (string) $entity['title'];
		$slug = $this->unique_slug( (string) $entity['slug'] );

		$id = wc_create_attribute(
			array(
				'name'         => $name,
				'slug'         => $slug,
				'type'         => 'select',
				'order_by'     => 'menu_order',
				'has_archives' => false,
			)
		);

		if ( is_wp_error( $id ) ) {
			return $id;
		}

		$taxonomy = wc_attribute_taxonomy_name( $slug );

		// Registered by hand for this request. WooCommerce registers attribute taxonomies on
		// init from a cached list, and the attribute created a moment ago is not in it — so
		// without this every term insert below fails with "invalid taxonomy".
		if ( ! taxonomy_exists( $taxonomy ) ) {
			register_taxonomy(
				$taxonomy,
				array( 'product' ),
				array(
					'hierarchical' => false,
					'show_ui'      => false,
					'query_var'    => true,
					'rewrite'      => false,
				)
			);
		}

		$terms  = $this->insert_terms( $taxonomy, (array) $entity['terms'] );
		$linked = $this->attach_to_products( $taxonomy, $terms, (int) $entity['link_count'] );

		$data = array(
			'id'       => (int) $id,
			'taxonomy' => $taxonomy,
			'terms'    => count( $terms ),
		);

		$result = array(
			'id'         => (int) $id,
			'name'       => $name,
			'slug'       => $slug,
			'values'     => array_keys( $terms ),
			'terms'      => count( $terms ),
			'linked'     => $linked,
			'created_at' => current_time( 'Y-m-d H:i:s' ),
		);

		return $this->filter_result( $result, (int) $id, $data );
	}

	/**
	 * Remove a generated attribute.
	 *
	 * `wc_delete_attribute()` removes the row and its terms together, and clears the
	 * transient WooCommerce caches the taxonomy list in.
	 *
	 * @since 1.1.0
	 *
	 * @param int|string $id Attribute id.
	 *
	 * @return true|WP_Error
	 */
	public function delete( $id ) {
		if ( ! function_exists( 'wc_delete_attribute' ) ) {
			return new WP_Error(
				'storeseeder_delete_unsupported',
				__( 'WooCommerce is not active on this site.', 'storeseeder' )
			);
		}

		wc_delete_attribute( (int) $id );

		return true;
	}

	/**
	 * Create the attribute's terms.
	 *
	 * @since 1.1.0
	 *
	 * @param string            $taxonomy The `pa_` taxonomy.
	 * @param array<int, mixed> $labels   Term labels from the entity.
	 *
	 * @return array<string, int> Label => term id.
	 */
	private function insert_terms( string $taxonomy, array $labels ): array {
		$terms = array();

		foreach ( $labels as $label ) {
			if ( ! is_string( $label ) || '' === $label ) {
				continue;
			}

			$term = wp_insert_term( $label, $taxonomy );

			if ( is_wp_error( $term ) ) {
				// A term that already exists is fine to reuse: the label is what matters.
				$existing = get_term_by( 'name', $label, $taxonomy );

				if ( $existing instanceof \WP_Term ) {
					$terms[ $label ] = (int) $existing->term_id;
				}

				continue;
			}

			$terms[ $label ] = (int) $term['term_id'];
		}

		return $terms;
	}

	/**
	 * Attach the attribute to real products.
	 *
	 * @since 1.1.0
	 *
	 * @param string             $taxonomy The `pa_` taxonomy.
	 * @param array<string, int> $terms    Label => term id.
	 * @param int                $count    How many products to attach to.
	 *
	 * @return int How many products were touched.
	 */
	private function attach_to_products( string $taxonomy, array $terms, int $count ): int {
		if ( array() === $terms ) {
			return 0;
		}

		$attached = 0;

		foreach ( $this->product_ids( max( 1, $count ) ) as $product_id ) {
			$product = wc_get_product( $product_id );

			if ( ! $product instanceof \WC_Product ) {
				continue;
			}

			// The terms have to be on the object as well as in the attribute list: the
			// product page reads the taxonomy relationship, the admin reads the attribute.
			wp_set_object_terms( $product_id, array_values( $terms ), $taxonomy, true );

			$attributes = $product->get_attributes();

			$attribute = new WC_Product_Attribute();
			$attribute->set_id( wc_attribute_taxonomy_id_by_name( $taxonomy ) );
			$attribute->set_name( $taxonomy );
			$attribute->set_options( array_values( $terms ) );
			$attribute->set_position( count( $attributes ) );
			$attribute->set_visible( true );
			$attribute->set_variation( false );

			$attributes[ $taxonomy ] = $attribute;

			$product->set_attributes( $attributes );
			$product->save();

			++$attached;
		}

		return $attached;
	}

	/**
	 * A slug WooCommerce will accept.
	 *
	 * Attribute slugs are unique, and `pa_` plus the slug has to fit inside WordPress's
	 * 32-character taxonomy-name limit — `wc_create_attribute()` rejects anything longer,
	 * which is a failure the generator cannot see coming.
	 *
	 * @since 1.1.0
	 *
	 * @param string $proposed Slug the generator proposed.
	 *
	 * @return string
	 */
	private function unique_slug( string $proposed ): string {
		$base = substr( sanitize_title( $proposed ), 0, 24 );
		$slug = $base;

		for ( $attempt = 2; $attempt <= 40; $attempt++ ) {
			if ( ! taxonomy_exists( wc_attribute_taxonomy_name( $slug ) )
				&& 0 === wc_attribute_taxonomy_id_by_name( wc_attribute_taxonomy_name( $slug ) ) ) {
				return $slug;
			}

			$slug = $base . '-' . $attempt;
		}

		return $base . '-' . substr( md5( (string) wp_generate_uuid4() ), 0, 4 );
	}
}
