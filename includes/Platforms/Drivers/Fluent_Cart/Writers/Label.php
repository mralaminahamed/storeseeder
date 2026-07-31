<?php
/**
 * Fluent Cart label writer
 *
 * @since   1.1.0
 * @package StoreSeeder\Platforms\Drivers\Fluent_Cart\Writers
 */

namespace StoreSeeder\Platforms\Drivers\Fluent_Cart\Writers;

use Exception;
use FluentCart\App\Models\Customer as CustomerModel;
use FluentCart\App\Models\Label as LabelModel;
use FluentCart\App\Models\LabelRelationship as LabelRelationshipModel;
use FluentCart\App\Models\Order as OrderModel;
use StoreSeeder\Platforms\Writer;
use StoreSeeder\Platforms\Resource;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Persists a canonical label into Fluent Cart.
 *
 * @since 1.1.0
 */
final class Label extends Writer {
	/**
	 * Canonical resource to the model class Fluent Cart stores in `labelable_type`.
	 *
	 * The relationship table is polymorphic on a class name, so this mapping can only
	 * live in the driver.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, class-string>
	 */
	private function labelable_types(): array {
		return array(
			Resource::ORDER    => OrderModel::class,
			Resource::CUSTOMER => CustomerModel::class,
		);
	}

	/**
	 * The resource this writer persists.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public function resource(): string {
		return Resource::LABEL;
	}

	/**
	 * Create a Fluent Cart label and attach it.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $entity Canonical label entity.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function write( array $entity ) {
		if ( ! defined( 'FLUENTCART_VERSION' ) || ! class_exists( LabelModel::class ) ) {
			return new WP_Error( 'missing_fluent_cart', __( 'Fluent Cart Label model not found. Please ensure Fluent Cart is active.', 'storeseeder' ) );
		}

		$label = $this->create_label( (string) $entity['value'] );

		if ( ! $label ) {
			return new WP_Error( 'label_creation_failed', __( 'Failed to create label.', 'storeseeder' ) );
		}

		$attached = $this->attach_label( (int) $label->id, (array) $entity['attach'] );

		$result = array(
			'id'         => $label->id,
			'value'      => $label->value,
			'attached'   => $attached,
			'created_at' => current_time( 'Y-m-d H:i:s' ),
		);

		/**
		 * Filters the label generation result data.
		 *
		 * @since 1.0.0
		 * @hook  storeseeder_label_generation_result
		 *
		 * @param array $result The label generation result data.
		 * @param int   $id     The created label ID.
		 */
		return apply_filters( 'storeseeder_label_generation_result', $result, $label->id );
	}

	/**
	 * Create a label, disambiguating the value against the ones already stored.
	 *
	 * The fct_label.value column carries a UNIQUE index, so a collision is a
	 * database error.
	 *
	 * @since 1.1.0
	 *
	 * @param string $base Value proposed by the generator.
	 *
	 * @return LabelModel|null Created label, or null on failure.
	 */
	private function create_label( string $base ): ?LabelModel {
		$value = $base;

		while ( LabelModel::query()->where( 'value', $value )->exists() ) {
			$value = $base . ' ' . strtoupper( $this->faker()->bothify( '??#' ) );
		}

		try {
			$created = LabelModel::query()->create( array( 'value' => $value ) );

			// Eloquent's create() is reached through __callStatic, so its declared type is
			// Builder|Model rather than this model. Narrowing here is what makes the
			// nullable return type above true rather than merely intended.
			return $created instanceof LabelModel ? $created : null;
		} catch ( Exception $e ) {
			return null;
		}
	}

	/**
	 * Attach the label to random orders and customers.
	 *
	 * The fct_label_relationships table is polymorphic: labelable_type is the
	 * target model class and labelable_id its row id. Labels attach to nothing
	 * until this runs.
	 *
	 * @since 1.1.0
	 *
	 * @param int                $label_id Label ID.
	 * @param array<string, int> $attach   How many rows to attach, by canonical resource.
	 *
	 * @return int Number of relationships created.
	 */
	private function attach_label( int $label_id, array $attach ): int {
		if ( ! class_exists( LabelRelationshipModel::class ) ) {
			return 0;
		}

		$created = 0;

		foreach ( $this->labelable_types() as $resource_type => $model ) {
			$limit = (int) ( $attach[ $resource_type ] ?? 0 );

			foreach ( $this->random_rows( $model, $limit ) as $id ) {
				LabelRelationshipModel::query()->create(
					array(
						'label_id'       => $label_id,
						'labelable_id'   => $id,
						'labelable_type' => $model,
					)
				);

				++$created;
			}
		}

		return $created;
	}

	/**
	 * Draw random row IDs for a model.
	 *
	 * @since 1.1.0
	 *
	 * @param class-string $model Model class.
	 * @param int          $limit How many rows to draw.
	 *
	 * @return array<int, int> Row IDs, possibly empty.
	 */
	private function random_rows( string $model, int $limit ): array {
		if ( $limit < 1 ) {
			return array();
		}

		$rows = $model::query()
			->inRandomOrder()
			->limit( $limit )
			->get();

		$ids = array();

		foreach ( $rows as $row ) {
			$ids[] = (int) $row->id;
		}

		return $ids;
	}
}
