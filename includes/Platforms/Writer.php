<?php
/**
 * Abstract Writer Class for StoreSeeder
 *
 * A Writer persists one canonical resource into one platform. It is the only place in
 * the plugin that is allowed to name a platform's models, tables or status strings —
 * everything above it works in the neutral vocabulary of Platform\Resource.
 *
 * @since   1.1.0
 * @package StoreSeeder\Platforms
 */

namespace StoreSeeder\Platforms;

use Faker\Generator as Faker_Generator;

/**
 * Abstract Writer Class
 *
 * ## Where the boundary sits
 *
 * The split between Generator::build_entity() and Writer::write() is *not* "invented
 * data vs. saved data". It is "data that has no platform in it" vs. "everything that
 * touches the platform, in either direction".
 *
 * That matters because several resources cannot be shaped without reading real rows.
 * An order needs real product variations to have line items, and their real prices to
 * have a total; a refund needs an existing charge transaction to refund against; a
 * label needs orders to attach to. Resolving those foreign keys is the writer's job,
 * not the generator's, because each platform stores and randomises them differently.
 *
 * So a writer legitimately reads before it writes, and computes totals from what it
 * read. What it must never do is invent a name, an address, a date or a quantity —
 * those come in on the entity, already localised by FakerPHP, so that the same seed
 * produces the same data on every platform.
 *
 * ## The entity contract
 *
 * Two conventions hold across every entity, and every writer converts on the way out:
 *
 * - Money is an integer in the currency's minor unit, alongside an explicit
 *   `currency`. Fluent Cart stores cents already; WooCommerce, StoreEngine and
 *   EasyCommerce want decimals, so those writers divide. A float must never reach a
 *   writer, because binary rounding on a price is a real bug and an invisible one.
 * - Statuses use the canonical vocabulary — pending, processing, on_hold, completed,
 *   cancelled, failed, refunded — which each writer maps to its own spelling.
 *   WooCommerce needs a `wc-` prefix; Fluent Cart does not.
 *
 * @since 1.1.0
 */
abstract class Writer {
	/**
	 * FakerPHP generator, shared with the generator that owns this writer.
	 *
	 * Present so a writer can randomise a choice among rows it read — picking one of
	 * the real orders to attach to, say. Not for inventing field values.
	 *
	 * @since 1.1.0
	 * @var Faker_Generator|null
	 */
	protected $faker = null;

	/**
	 * Generation parameters from the REST request.
	 *
	 * @since 1.1.0
	 * @var array<string, mixed>
	 */
	protected array $params = array();

	/**
	 * The canonical resource this writer persists.
	 *
	 * @since 1.1.0
	 *
	 * @return string One of the Platform\Resource constants.
	 */
	abstract public function resource(): string;

	/**
	 * Persist one canonical entity.
	 *
	 * Returning array|WP_Error rather than an id is deliberate: it is the contract
	 * Generator::generate() already loops over, so per-item failures keep landing in
	 * the batch's error list instead of aborting the run.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $entity Canonical, platform-neutral entity.
	 *
	 * @return array<string, mixed>|\WP_Error The persisted item, or the reason it failed.
	 */
	abstract public function write( array $entity );

	/**
	 * Remove one row this writer created.
	 *
	 * Concrete rather than abstract, and unsupported by default: adding an abstract method
	 * would break every third-party writer already in the wild, and a driver that cannot
	 * delete a resource should say so rather than fail to load. The cleanup reports the
	 * refusal per resource, so "orders cannot be removed automatically" is something the
	 * user reads instead of a silent no-op.
	 *
	 * Only rows recorded in the ledger are ever passed here — this is never asked to delete
	 * something the store itself created.
	 *
	 * @since 1.1.0
	 *
	 * @param int|string $id The identifier this writer reported when it created the row.
	 *
	 * @return true|\WP_Error True when the row is gone, including when it was already gone.
	 */
	public function delete( $id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- the signature subclasses override.
		return new \WP_Error(
			'storeseeder_delete_unsupported',
			sprintf(
				/* translators: %s: canonical resource name, e.g. product. */
				__( 'This platform cannot remove generated %s records automatically.', 'storeseeder' ),
				$this->resource()
			)
		);
	}

	/**
	 * Delete rows from one model, and optionally its children first.
	 *
	 * Here rather than in each driver because the shape is the same everywhere — children
	 * before parent, missing rows counted as already gone — while the model names are the
	 * caller's, which keeps this class free of any platform's vocabulary.
	 *
	 * A row that has already vanished is a success, not an error: the ledger can outlive the
	 * data it points at, and demanding that every recorded row still exist would make the
	 * cleanup fail permanently on a database restored from elsewhere.
	 *
	 * @since 1.1.0
	 *
	 * @param string                $model    Eloquent model class for the row itself.
	 * @param int|string            $id       Primary key value.
	 * @param array<string, string> $children Child model class => foreign key column.
	 * @param string                $key      Primary key column on the parent model.
	 *
	 * @return true|\WP_Error
	 */
	protected function delete_model( string $model, $id, array $children = array(), string $key = 'id' ) {
		if ( ! class_exists( $model ) ) {
			return new \WP_Error(
				'storeseeder_delete_unsupported',
				sprintf(
					/* translators: %s: canonical resource name, e.g. license. */
					__( 'The plugin that stores generated %s records is no longer active.', 'storeseeder' ),
					$this->resource()
				)
			);
		}

		try {
			foreach ( $children as $child => $foreign_key ) {
				if ( class_exists( $child ) ) {
					$child::query()->where( $foreign_key, $id )->delete();
				}
			}

			$model::query()->where( $key, $id )->delete();
		} catch ( \Exception $e ) {
			return new \WP_Error(
				'storeseeder_delete_failed',
				sprintf(
					/* translators: 1: canonical resource name, 2: database error message. */
					__( 'Could not remove a generated %1$s record: %2$s', 'storeseeder' ),
					$this->resource(),
					$e->getMessage()
				)
			);
		}

		return true;
	}

	/**
	 * Create a taxonomy term and attach it to products.
	 *
	 * Here rather than in each driver because brands and categories are taxonomy terms on every
	 * platform that has them, and the only things that differ are the taxonomy's name and where
	 * the products come from — both arguments, which keeps this class free of any platform's
	 * vocabulary, the same way `delete_model()` takes a model class.
	 *
	 * The differences it absorbs are real: WooCommerce calls its taxonomies `product_brand` and
	 * `product_cat`, Fluent Cart calls them `product-brands` and `product-categories`, and a
	 * writer that hard-coded either pair would silently create nothing on the other platform.
	 *
	 * @since 1.1.0
	 *
	 * @param string               $taxonomy    Taxonomy name.
	 * @param array<string, mixed> $entity      Canonical entity with name, slug, description,
	 *                                          link_count and nested.
	 * @param array<int, int>      $product_ids Products to attach it to.
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	protected function create_term_in( string $taxonomy, array $entity, array $product_ids ) {
		$name   = $this->unique_term_name( $taxonomy, (string) $entity['name'] );
		$parent = empty( $entity['nested'] ) ? 0 : $this->random_term_id( $taxonomy );

		$term = wp_insert_term(
			$name,
			$taxonomy,
			array(
				'description' => (string) $entity['description'],
				'slug'        => sanitize_title( $name ),
				'parent'      => $parent,
			)
		);

		if ( is_wp_error( $term ) ) {
			return $term;
		}

		$id = (int) $term['term_id'];

		// Attached to real products, because a term on nothing is invisible outside the
		// taxonomy screen — the same reason the attribute writer links its terms.
		$wanted   = max( 0, (int) $entity['link_count'] );
		$attached = 0;

		foreach ( array_slice( $product_ids, 0, $wanted ) as $product_id ) {
			$set = wp_set_object_terms( (int) $product_id, array( $id ), $taxonomy, true );

			if ( ! is_wp_error( $set ) ) {
				++$attached;
			}
		}

		$result = array(
			'id'          => $id,
			'name'        => $name,
			'slug'        => sanitize_title( $name ),
			'description' => (string) $entity['description'],
			'products'    => $attached,
			'parent'      => $parent > 0 ? get_term_field( 'name', $parent, $taxonomy ) : '',
			'created_at'  => current_time( 'Y-m-d H:i:s' ),
		);

		return $this->filter_result( $result, $id, $result );
	}

	/**
	 * Remove a term this writer created.
	 *
	 * The objects it was attached to keep existing; only the relationship goes, which is what
	 * deleting a brand or a class means rather than deleting the products carrying it.
	 *
	 * @since 1.1.0
	 *
	 * @param string     $taxonomy Taxonomy name.
	 * @param int|string $id       Term id.
	 *
	 * @return true|\WP_Error
	 */
	protected function delete_term( string $taxonomy, $id ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return new \WP_Error(
				'storeseeder_delete_unsupported',
				sprintf(
					/* translators: %s: canonical resource name, e.g. brand. */
					__( 'The plugin that stores generated %s records is no longer active.', 'storeseeder' ),
					$this->resource()
				)
			);
		}

		$deleted = wp_delete_term( (int) $id, $taxonomy );

		if ( is_wp_error( $deleted ) ) {
			return $deleted;
		}

		// A term that was already gone counts as deleted: the ledger can outlive what it
		// points at, and failing here would block the rest of a cleanup for ever.
		return true;
	}

	/**
	 * A term name the taxonomy will accept.
	 *
	 * `wp_insert_term()` refuses a duplicate name at the same level, and a generator drawing
	 * from a fixed pool collides by design — so without this, every item after the first few
	 * fails.
	 *
	 * @since 1.1.0
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @param string $proposed Name the generator proposed.
	 *
	 * @return string
	 */
	protected function unique_term_name( string $taxonomy, string $proposed ): string {
		$name = $proposed;

		for ( $attempt = 2; $attempt <= 40; $attempt++ ) {
			if ( ! term_exists( $name, $taxonomy ) ) {
				return $name;
			}

			$name = $proposed . ' ' . $attempt;
		}

		return $proposed . ' ' . strtoupper( substr( md5( (string) wp_generate_uuid4() ), 0, 4 ) );
	}

	/**
	 * The id of an existing term in one taxonomy, or 0 when it has none.
	 *
	 * @since 1.1.0
	 *
	 * @param string $taxonomy Taxonomy name.
	 *
	 * @return int
	 */
	protected function random_term_id( string $taxonomy ): int {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'number'     => 20,
				'fields'     => 'ids',
			)
		);

		if ( is_wp_error( $terms ) || array() === (array) $terms ) {
			return 0;
		}

		return (int) $this->faker()->randomElement( (array) $terms );
	}

	/**
	 * Share the generator's FakerPHP instance.
	 *
	 * @since 1.1.0
	 *
	 * @param Faker_Generator $faker Configured, already seeded generator.
	 *
	 * @return void
	 */
	public function set_faker( Faker_Generator $faker ): void {
		$this->faker = $faker;
	}

	/**
	 * Share the generation parameters.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $params Parameters from the REST request.
	 *
	 * @return void
	 */
	public function set_params( array $params ): void {
		$this->params = $params;
	}

	/**
	 * Let integrators reshape what a write reports back.
	 *
	 * Every writer offers this, and the hook name is derived from `resource()` rather than
	 * written out per writer — which is how three of them (attributes, logs, refunds) came to
	 * offer no filter at all, and how the shipping-plan writer ended up firing
	 * `storeseeder_shipping_method_generation_result` after the resource was renamed. A
	 * derived name cannot drift from the resource it belongs to.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $result What the writer is about to return.
	 * @param int|string           $id     The created record's identifier.
	 * @param array<string, mixed> $data   The platform-shaped data used to create it.
	 *
	 * @return array<string, mixed>
	 */
	protected function filter_result( array $result, $id, array $data ): array {
		/**
		 * Filters one generated record's reported result.
		 *
		 * `{$resource}` is the canonical resource name — `storeseeder_product_generation_result`,
		 * `storeseeder_cart_session_generation_result`, and so on for all eighteen.
		 *
		 * @since 1.0.0
		 * @hook  storeseeder_{$resource}_generation_result
		 *
		 * @param array<string, mixed> $result The generation result data.
		 * @param int|string           $id     The created record's id.
		 * @param array<string, mixed> $data   The data used for creation.
		 */
		return (array) apply_filters(
			"storeseeder_{$this->resource()}_generation_result",
			$result,
			$id,
			$data
		);
	}

	/**
	 * The shared FakerPHP instance.
	 *
	 * @since 1.1.0
	 *
	 * @return Faker_Generator
	 */
	protected function faker(): Faker_Generator {
		return $this->faker;
	}
}
