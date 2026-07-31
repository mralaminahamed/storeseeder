<?php
/**
 * Fluent Cart tax class writer
 *
 * @since   1.1.0
 * @package StoreSeeder\Platforms\Drivers\Fluent_Cart\Writers
 */

namespace StoreSeeder\Platforms\Drivers\Fluent_Cart\Writers;

use FluentCart\App\Models\TaxClass as TaxClassModel;
use FluentCart\App\Models\TaxRate as TaxRateModel;
use StoreSeeder\Platforms\Writer;
use StoreSeeder\Platforms\Resource;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Persists a canonical tax class into Fluent Cart.
 *
 * @since 1.1.0
 */
final class Tax_Class extends Writer {
	/**
	 * The resource this writer persists.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public function resource(): string {
		return Resource::TAX_CLASS;
	}

	/**
	 * Create a Fluent Cart tax class and its rate rows.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $entity Canonical tax class entity.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function write( array $entity ) {
		if ( ! defined( 'FLUENTCART_VERSION' ) ) {
			return new WP_Error( 'missing_fluent_cart', __( 'Fluent Cart plugin not found. Please ensure Fluent Cart is active.', 'storeseeder' ) );
		}

		$tax_data = array(
			'name'    => $entity['name'],
			'rate'    => $entity['rate'],
			'country' => $entity['country'],
			'state'   => $entity['state'],
			'status'  => $entity['status'],
		);

		$tax_id = $this->create_tax_class( $tax_data );

		if ( is_wp_error( $tax_id ) ) {
			return $tax_id;
		}

		if ( ! $tax_id ) {
			return new WP_Error( 'tax_class_creation_failed', __( 'Failed to create tax class.', 'storeseeder' ) );
		}

		// Create the geographic rate rows. Without them the tax class exists but
		// carries no rate that Fluent Cart can apply — fct_tax_rates stays empty,
		// so no order is ever taxed and every tax report reads zero. The rate the
		// old generator tucked into the class meta was read by nothing.
		$rates_created = $this->create_tax_rates( (int) $tax_id, $entity );

		$result = array(
			'id'            => $tax_id,
			'name'          => $tax_data['name'],
			'rate'          => $tax_data['rate'],
			'country'       => $tax_data['country'],
			'status'        => $tax_data['status'],
			'rates_created' => $rates_created,
			'created_at'    => current_time( 'Y-m-d H:i:s' ),
		);

		return $this->filter_result( $result, $tax_id, $tax_data );
	}

	/**
	 * Create the geographic rate rows for a tax class.
	 *
	 * The fct_tax_rates.rate column is a percentage stored as a string ("8.5" = 8.5%), keyed
	 * to the class by class_id and scoped by country/state. for_order marks the
	 * rate as one Fluent Cart applies to orders.
	 *
	 * @since 1.1.0
	 *
	 * @param int                  $class_id Parent tax class ID.
	 * @param array<string, mixed> $entity   Canonical tax class entity.
	 *
	 * @return int Number of rate rows created.
	 */
	private function create_tax_rates( int $class_id, array $entity ): int {
		if ( ! class_exists( TaxRateModel::class ) ) {
			return 0;
		}

		$created  = 0;
		$priority = 1;

		foreach ( $entity['rates'] as $row ) {
			TaxRateModel::query()->create(
				array(
					'class_id'    => $class_id,
					'country'     => $row['country'],
					'state'       => $row['state'],
					'rate'        => (string) $row['rate'],
					'name'        => $entity['name'] . ' - ' . $row['country'],
					'priority'    => $priority,
					'is_compound' => 0,
					'for_order'   => 1,
				)
			);

			++$created;
			++$priority;
		}

		return $created;
	}

	/**
	 * Create tax class in Fluent Cart.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $data Fluent-Cart-shaped tax class data.
	 *
	 * @return int|WP_Error|null Created tax class ID or error.
	 */
	private function create_tax_class( array $data ) {
		// Check if Fluent Cart TaxClass model is available.
		if ( ! class_exists( TaxClassModel::class ) ) {
			return new WP_Error( 'missing_model', __( 'Fluent Cart TaxClass model not found. Please ensure Fluent Cart plugin is active.', 'storeseeder' ) );
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
		$tax_class = TaxClassModel::query()->create( $tax_class_data );

		if ( is_wp_error( $tax_class ) ) {
			return $tax_class;
		}

		if ( ! $tax_class instanceof TaxClassModel ) {
			return new WP_Error( 'tax_class_creation_failed', __( 'Failed to create tax class using Fluent Cart model.', 'storeseeder' ) );
		}

		return $tax_class->id;
	}

	/**
	 * Remove a generated tax class and its rates.
	 *
	 * @since 1.1.0
	 *
	 * @param int|string $id The identifier reported when the row was created.
	 *
	 * @return true|WP_Error
	 */
	public function delete( $id ) {
		return $this->delete_model(
			TaxClassModel::class,
			$id,
			array( TaxRateModel::class => 'class_id' )
		);
	}
}
