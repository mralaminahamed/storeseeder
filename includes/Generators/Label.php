<?php
/**
 * Label Generator Class for Fluent Cart FakerPress Plugin
 *
 * @since      2.4.0
 * @subpackage Generators
 * @package    FluentCartFakerPress
 */

namespace FluentCartFakerPress\Generators;

use FluentCart\App\Models\Customer as CustomerModel;
use FluentCart\App\Models\Label as LabelModel;
use FluentCart\App\Models\LabelRelationship as LabelRelationshipModel;
use FluentCart\App\Models\Order as OrderModel;
use FluentCartFakerPress\Abstracts\Generator;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Label Generator Class
 *
 * Generates labels (tags) and attaches them to existing orders and customers for
 * testing Fluent Cart's tagging and segmentation features.
 */
class Label extends Generator {


	/**
	 * Representative label values.
	 *
	 * @var string[]
	 */
	private const LABELS = array(
		'VIP',
		'Wholesale',
		'Repeat Buyer',
		'At Risk',
		'High Value',
		'Fraud Review',
		'Gift Order',
		'Priority',
		'New Customer',
		'Follow Up',
		'Refund Requested',
		'Subscriber',
	);

	/**
	 * Get the resource type name
	 *
	 * @return string Resource type name.
	 */
	protected function get_resource_type(): string {
		return 'label';
	}

	/**
	 * Get supported data types for this generator.
	 *
	 * @return array Supported types
	 */
	public function get_supported_types(): array {
		return array(
			'labels' => 'Fluent Cart Labels',
		);
	}

	/**
	 * Get generator description.
	 *
	 * @return string Description
	 */
	public function get_description(): string {
		return 'Generates labels and attaches them to existing orders and customers for testing Fluent Cart tagging and segmentation.';
	}

	/**
	 * Generate a single label
	 *
	 * @return WP_Error|array Single label data, error, or false on failure.
	 */
	protected function generate_single_item() {
		if ( ! defined( 'FLUENTCART_VERSION' ) || ! class_exists( LabelModel::class ) ) {
			return new WP_Error( 'missing_fluent_cart', __( 'Fluent Cart Label model not found. Please ensure Fluent Cart is active.', 'fluent-cart-fakerpress' ) );
		}

		$label = $this->create_label();

		if ( ! $label ) {
			return new WP_Error( 'label_creation_failed', __( 'Failed to create label.', 'fluent-cart-fakerpress' ) );
		}

		$attached = $this->attach_label( (int) $label->id );

		$result = array(
			'id'         => $label->id,
			'value'      => $label->value,
			'attached'   => $attached,
			'created_at' => current_time( 'Y-m-d H:i:s' ),
		);

		/**
		 * Filters the label generation result data.
		 *
		 * @since 2.4.0
		 * @hook  fluent_cart_fakerpress_label_generation_result
		 *
		 * @param array $result The label generation result data.
		 * @param int   $id     The created label ID.
		 */
		return apply_filters( 'fluent_cart_fakerpress_label_generation_result', $result, $label->id );
	}

	/**
	 * Create a label with a value not already taken.
	 *
	 * The fct_label.value column carries a UNIQUE index, so a collision is a
	 * database error.
	 *
	 * @return LabelModel|null Created label, or null on failure.
	 */
	private function create_label(): ?LabelModel {
		$base  = $this->get_faker()->randomElement( self::LABELS );
		$value = $base;

		while ( LabelModel::query()->where( 'value', $value )->exists() ) {
			$value = $base . ' ' . strtoupper( $this->get_faker()->bothify( '??#' ) );
		}

		try {
			return LabelModel::query()->create( array( 'value' => $value ) );
		} catch ( \Exception $e ) {
			return null;
		}
	}

	/**
	 * Attach the label to a few random orders and customers.
	 *
	 * The fct_label_relationships table is polymorphic: labelable_type is the
	 * target model class and labelable_id its row id. Labels attach to nothing
	 * until this runs.
	 *
	 * @param int $label_id Label ID.
	 *
	 * @return int Number of relationships created.
	 */
	private function attach_label( int $label_id ): int {
		if ( ! class_exists( LabelRelationshipModel::class ) ) {
			return 0;
		}

		$targets = array();

		foreach ( $this->random_rows( OrderModel::class ) as $id ) {
			$targets[] = array(
				'id'   => $id,
				'type' => OrderModel::class,
			);
		}

		foreach ( $this->random_rows( CustomerModel::class ) as $id ) {
			$targets[] = array(
				'id'   => $id,
				'type' => CustomerModel::class,
			);
		}

		$created = 0;

		foreach ( $targets as $target ) {
			LabelRelationshipModel::query()->create(
				array(
					'label_id'       => $label_id,
					'labelable_id'   => $target['id'],
					'labelable_type' => $target['type'],
				)
			);

			++$created;
		}

		return $created;
	}

	/**
	 * Draw a handful of random row IDs for a model.
	 *
	 * @param class-string $model Model class.
	 *
	 * @return array<int, int> Row IDs, possibly empty.
	 */
	private function random_rows( string $model ): array {
		$rows = $model::query()
			->inRandomOrder()
			->limit( $this->get_faker()->numberBetween( 0, 3 ) )
			->get();

		$ids = array();

		foreach ( $rows as $row ) {
			$ids[] = (int) $row->id;
		}

		return $ids;
	}
}
