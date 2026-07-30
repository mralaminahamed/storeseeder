<?php
/**
 * Fluent Cart attribute writer
 *
 * @since   1.1.0
 * @package StoreSeeder\Platforms\Fluent_Cart\Writers
 */

namespace StoreSeeder\Platforms\Fluent_Cart\Writers;

use FluentCart\App\Models\AttributeGroup;
use FluentCart\App\Models\AttributeRelation;
use FluentCart\App\Models\AttributeTerm;
use FluentCart\App\Models\ProductVariation as ProductVariationModel;
use StoreSeeder\Abstracts\Writer;
use StoreSeeder\Platform\Resource;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Persists a canonical attribute group into Fluent Cart.
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
	 * Create a Fluent Cart attribute group, its terms, and their variation bindings.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $entity Canonical attribute entity.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function write( array $entity ) {
		if ( ! defined( 'FLUENTCART_VERSION' ) || ! class_exists( AttributeGroup::class ) ) {
			return new WP_Error( 'missing_fluent_cart', __( 'Fluent Cart attribute models not found. Ensure Fluent Cart is active.', 'storeseeder' ) );
		}

		$title = (string) $entity['title'];
		$slug  = (string) $entity['slug'];

		$group = AttributeGroup::create(
			array(
				'title'       => $title,
				'slug'        => $slug,
				'description' => $entity['description'],
				'settings'    => array(),
				'serial'      => $entity['serial'],
			)
		);

		if ( ! $group || ! $group->id ) {
			return new WP_Error( 'attribute_creation_failed', __( 'Failed to create attribute group.', 'storeseeder' ) );
		}

		$values = array();

		foreach ( (array) $entity['terms'] as $i => $label ) {
			$term = AttributeTerm::create(
				array(
					'group_id'    => $group->id,
					'serial'      => $i + 1,
					'title'       => $label,
					'slug'        => sanitize_title( $title . '-' . $label ),
					'description' => '',
					'settings'    => array(),
				)
			);

			if ( $term && $term->id ) {
				$values[] = array(
					'id'    => (int) $term->id,
					'label' => $label,
				);
			}
		}

		// Bind the terms to real product variations. Without this the attribute
		// group and its terms exist but attach to nothing — fct_atts_relations
		// stays empty, no product is genuinely variable, and the attribute is
		// invisible on every product page.
		$linked = $this->link_terms_to_variations(
			(int) $group->id,
			array_column( $values, 'id' ),
			(int) $entity['link_count']
		);

		return array(
			'id'     => (int) $group->id,
			'name'   => $title,
			'slug'   => $slug,
			'values' => $values,
			'linked' => $linked,
		);
	}

	/**
	 * Attach this attribute group's terms to real product variations.
	 *
	 * The fct_atts_relations table binds a variation (object_id) to one term of a group.
	 * A variation carries at most one term per group — it is one size, one
	 * colour — so a variation that already has a term for this group is skipped.
	 *
	 * @since 1.1.0
	 *
	 * @param int   $group_id Attribute group ID.
	 * @param int[] $term_ids IDs of the group's terms.
	 * @param int   $limit    How many variations to attempt to bind.
	 *
	 * @return int Number of variations linked.
	 */
	private function link_terms_to_variations( int $group_id, array $term_ids, int $limit ): int {
		if ( empty( $term_ids ) || ! class_exists( AttributeRelation::class ) ) {
			return 0;
		}

		$variations = ProductVariationModel::query()
			->inRandomOrder()
			->limit( $limit )
			->get();

		$linked = 0;

		foreach ( $variations as $variation ) {
			$already = AttributeRelation::query()
				->where( 'object_id', $variation->id )
				->where( 'group_id', $group_id )
				->exists();

			if ( $already ) {
				continue;
			}

			AttributeRelation::query()->create(
				array(
					'object_id' => (int) $variation->id,
					'group_id'  => $group_id,
					'term_id'   => (int) $this->faker()->randomElement( $term_ids ),
				)
			);

			++$linked;
		}

		return $linked;
	}
}
