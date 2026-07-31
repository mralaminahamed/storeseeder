<?php
/**
 * WooCommerce tax class writer
 *
 * @since   1.1.0
 * @package StoreSeeder\Platforms\Drivers\Woo_Commerce\Writers
 */

namespace StoreSeeder\Platforms\Drivers\Woo_Commerce\Writers;

use StoreSeeder\Platforms\Drivers\Woo_Commerce\Writer;
use StoreSeeder\Platforms\Resource;
use WC_Tax;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Persists a canonical tax class into WooCommerce, with the rates that make it apply.
 *
 * A tax class with no rates is inert — WooCommerce charges nothing for it and the admin shows
 * an empty table — so the rates are part of creating one, not a follow-up.
 *
 * WooCommerce identifies a tax class by **slug**, not by an integer id: `wc_tax_rate_classes`
 * has a numeric primary key, but every consumer (`WC_Tax::get_rates`, a product's `tax_class`
 * property, the rate rows themselves) works in slugs. So the slug is what this writer reports
 * and what the cleanup deletes by.
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
	 * Create a WooCommerce tax class and its rates.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $entity Canonical tax-class entity.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function write( array $entity ) {
		if ( ! class_exists( 'WC_Tax' ) ) {
			return new WP_Error(
				'missing_woocommerce',
				__( 'WooCommerce is not active on this site.', 'storeseeder' )
			);
		}

		$name    = $this->unique_name( (string) $entity['name'] );
		$created = WC_Tax::create_tax_class( $name );

		if ( is_wp_error( $created ) ) {
			return $created;
		}

		$slug  = (string) $created['slug'];
		$rates = (array) $entity['rates'];

		// The entity's own country/state/rate is the primary rate; `rates` carries the rest.
		// Prepending it keeps the record consistent with what the generator reported.
		array_unshift(
			$rates,
			array(
				'country' => $entity['country'],
				'state'   => $entity['state'],
				'rate'    => $entity['rate'],
			)
		);

		$written = $this->insert_rates( $slug, $name, $rates );

		$data = array(
			'slug'  => $slug,
			'name'  => $name,
			'rates' => $written,
		);

		$result = array(
			// The slug, not the numeric id: it is what every WooCommerce consumer uses, and
			// what the cleanup needs in order to delete the class and its rates together.
			'id'         => $slug,
			'name'       => $name,
			'rate'       => (float) $entity['rate'] . '%',
			'country'    => (string) $entity['country'],
			'state'      => (string) $entity['state'],
			'rates'      => $written,
			'created_at' => current_time( 'Y-m-d H:i:s' ),
		);

		return $this->filter_result( $result, $slug, $data );
	}

	/**
	 * Remove a generated tax class and its rates.
	 *
	 * @since 1.1.0
	 *
	 * @param int|string $id The class slug.
	 *
	 * @return true|WP_Error
	 */
	public function delete( $id ) {
		if ( ! class_exists( 'WC_Tax' ) ) {
			return new WP_Error(
				'storeseeder_delete_unsupported',
				__( 'WooCommerce is not active on this site.', 'storeseeder' )
			);
		}

		$slug = (string) $id;

		// Rates first: WooCommerce does not cascade, and a rate whose class is gone still
		// applies to nothing while cluttering the tax screens.
		foreach ( (array) WC_Tax::get_rates_for_tax_class( $slug ) as $rate ) {
			if ( isset( $rate->tax_rate_id ) ) {
				WC_Tax::_delete_tax_rate( (int) $rate->tax_rate_id );
			}
		}

		$deleted = WC_Tax::delete_tax_class_by( 'slug', $slug );

		if ( is_wp_error( $deleted ) ) {
			// Already gone counts as success: the ledger can outlive what it points at.
			return 'invalid_tax_class' === $deleted->get_error_code() ? true : $deleted;
		}

		return true;
	}

	/**
	 * Insert the rate rows for one class.
	 *
	 * @since 1.1.0
	 *
	 * @param string            $slug  Tax class slug.
	 * @param string            $name  Tax class name, reused as the rate label.
	 * @param array<int, mixed> $rates Canonical rate rows.
	 *
	 * @return int How many rates were written.
	 */
	private function insert_rates( string $slug, string $name, array $rates ): int {
		$written = 0;

		foreach ( $rates as $rate ) {
			if ( ! is_array( $rate ) ) {
				continue;
			}

			$id = WC_Tax::_insert_tax_rate(
				array(
					'tax_rate_country'  => strtoupper( (string) ( $rate['country'] ?? '' ) ),
					'tax_rate_state'    => strtoupper( (string) ( $rate['state'] ?? '' ) ),
					'tax_rate'          => (string) ( $rate['rate'] ?? 0 ),
					'tax_rate_name'     => $name,
					'tax_rate_priority' => 1,
					'tax_rate_compound' => 0,
					'tax_rate_shipping' => 1,
					'tax_rate_class'    => $slug,
				)
			);

			if ( $id ) {
				++$written;
			}
		}

		return $written;
	}

	/**
	 * A class name WooCommerce will accept.
	 *
	 * `create_tax_class()` refuses a duplicate name outright, and the generator draws from a
	 * short list, so a second run would fail every item without this.
	 *
	 * @since 1.1.0
	 *
	 * @param string $proposed Name the generator proposed.
	 *
	 * @return string
	 */
	private function unique_name( string $proposed ): string {
		$existing = array_map( 'strtolower', WC_Tax::get_tax_classes() );
		$name     = $proposed;

		for ( $attempt = 2; $attempt <= 40; $attempt++ ) {
			if ( ! in_array( strtolower( $name ), $existing, true ) ) {
				return $name;
			}

			$name = $proposed . ' ' . $attempt;
		}

		return $proposed . ' ' . strtoupper( substr( md5( (string) wp_generate_uuid4() ), 0, 4 ) );
	}
}
