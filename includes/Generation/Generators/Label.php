<?php
/**
 * Label Generator Class for StoreSeeder Plugin
 *
 * @since      2.4.0
 * @subpackage Generators
 * @package    StoreSeeder\Generation\Generators
 */

namespace StoreSeeder\Generation\Generators;

use StoreSeeder\Generation\Generator;
use StoreSeeder\Platforms\Resource;

defined( 'ABSPATH' ) || exit;

/**
 * Label Generator Class
 *
 * Shapes labels (tags) and decides how many orders and customers each should be
 * attached to. Finding those rows, and knowing how the platform records the
 * association, is the writer's job.
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
			'labels' => __( 'Labels', 'storeseeder' ),
		);
	}

	/**
	 * Get generator description.
	 *
	 * @return string Description
	 */
	public function get_description(): string {
		return 'Generates labels and attaches them to existing orders and customers for testing tagging and segmentation.';
	}

	/**
	 * Build a canonical label
	 *
	 * `attach` is keyed by canonical resource rather than by model class, so the same
	 * entity means the same thing on a platform that stores labels as a taxonomy as on
	 * one that stores them in a polymorphic table.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, mixed>
	 */
	protected function build_entity() {
		return array(
			'value'  => $this->get_faker()->randomElement( self::LABELS ),
			'attach' => array(
				Resource::ORDER    => $this->get_faker()->numberBetween( 0, 3 ),
				Resource::CUSTOMER => $this->get_faker()->numberBetween( 0, 3 ),
			),
		);
	}
}
