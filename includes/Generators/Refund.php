<?php
/**
 * Refund Generator.
 *
 * @since   1.0.0
 * @package StoreSeeder\Generators
 */

namespace StoreSeeder\Generators;

defined( 'ABSPATH' ) || exit;

use StoreSeeder\Abstracts\Generator;

/**
 * Shapes refunds against existing successful charges.
 *
 * @since 1.0.0
 */
class Refund extends Generator {

	/**
	 * Refund reasons.
	 *
	 * @var string[]
	 */
	private const REASONS = array(
		'Item not as described',
		'Duplicate order placed',
		'Item arrived damaged',
		'Wrong item received',
		'Item never arrived',
		'Changed mind after purchase',
		'Quality not as expected',
		'Billing error',
	);

	/**
	 * {@inheritDoc}
	 */
	protected function get_resource_type(): string {
		return 'refund';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_supported_types(): array {
		return array(
			'full'    => __( 'Full refund', 'storeseeder' ),
			'partial' => __( 'Partial refund', 'storeseeder' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return 'Generates full and partial refund transactions against existing successful charge transactions.';
	}

	/**
	 * {@inheritDoc}
	 *
	 * A refund is a proportion of something that already exists, so the amount cannot
	 * be decided here — only whether the refund is full or partial, and if partial, what
	 * share of the charge it covers. The writer finds the charge and does the
	 * multiplication.
	 */
	protected function build_entity() {
		$full = $this->get_faker()->boolean( 50 );

		// Drawn only for partial refunds, so that a run of full refunds consumes the
		// same number of faker values as it always has.
		$fraction = $full ? null : $this->get_faker()->randomFloat( 2, 0.1, 0.9 );

		return array(
			'kind'     => $full ? 'full' : 'partial',
			'fraction' => $fraction,
			'reason'   => $this->get_faker()->randomElement( self::REASONS ),
		);
	}
}
