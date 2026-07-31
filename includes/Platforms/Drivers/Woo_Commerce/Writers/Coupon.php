<?php
/**
 * WooCommerce coupon writer
 *
 * @since   1.1.0
 * @package StoreSeeder\Platforms\Drivers\Woo_Commerce\Writers
 */

namespace StoreSeeder\Platforms\Drivers\Woo_Commerce\Writers;

use StoreSeeder\Platforms\Drivers\Woo_Commerce\Writer;
use StoreSeeder\Platforms\Resource;
use WC_Coupon;
use WC_Data_Exception;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Persists a canonical coupon into WooCommerce.
 *
 * @since 1.1.0
 */
final class Coupon extends Writer {
	/**
	 * Canonical discount type to WooCommerce's.
	 *
	 * WooCommerce has no free-shipping *type*: free shipping is a flag on a coupon whose
	 * discount is a fixed amount of zero. Mapping it to `fixed_cart` and setting the flag is
	 * what produces a coupon that actually waives shipping — a made-up type string would be
	 * stored and then ignored by every calculation.
	 *
	 * @since 1.1.0
	 * @var array<string, string>
	 */
	private const DISCOUNT_TYPE = array(
		'percentage'    => 'percent',
		'fixed'         => 'fixed_cart',
		'free_shipping' => 'fixed_cart',
	);

	/**
	 * The resource this writer persists.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public function resource(): string {
		return Resource::COUPON;
	}

	/**
	 * Create a WooCommerce coupon.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $entity Canonical coupon entity.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function write( array $entity ) {
		if ( ! class_exists( 'WC_Coupon' ) ) {
			return new WP_Error(
				'missing_woocommerce',
				__( 'WooCommerce is not active on this site.', 'storeseeder' )
			);
		}

		$type          = (string) $entity['type'];
		$free_shipping = 'free_shipping' === $type;
		$code          = $this->unique_code( (string) $entity['code'] );

		// A percentage is a percent on both sides; a fixed amount arrives in minor units and
		// WooCommerce wants a decimal. Free shipping carries no amount at all.
		if ( $free_shipping ) {
			$amount = '0';
		} elseif ( 'percentage' === $type ) {
			$amount = (string) (int) $entity['discount'];
		} else {
			$amount = $this->to_decimal( (int) $entity['discount'] );
		}

		$coupon = new WC_Coupon();

		try {
			$props = array(
				'code'               => $code,
				'description'        => $entity['description'],
				'discount_type'      => self::DISCOUNT_TYPE[ $type ] ?? 'fixed_cart',
				'amount'             => $amount,
				'free_shipping'      => $free_shipping,
				'date_expires'       => (string) $entity['expires_at'],
				// WooCommerce states the inverse: a stackable coupon is one not marked for
				// individual use.
				'individual_use'     => ! ( $entity['stackable'] ?? true ),
				'exclude_sale_items' => (bool) ( $entity['exclude_sale_items'] ?? false ),
			);

			// Null is an unlimited coupon and WooCommerce spells that as an empty limit, not as a
			// limit of zero — zero would reject the coupon on its first use.
			if ( null !== ( $entity['usage_limit'] ?? null ) ) {
				$props['usage_limit'] = (int) $entity['usage_limit'];
			}

			if ( null !== ( $entity['usage_limit_per_user'] ?? null ) ) {
				$props['usage_limit_per_user'] = (int) $entity['usage_limit_per_user'];
			}

			// Cart thresholds arrive in minor units and WooCommerce wants a decimal string.
			if ( null !== ( $entity['minimum_amount'] ?? null ) ) {
				$props['minimum_amount'] = $this->to_decimal( (int) $entity['minimum_amount'] );
			}

			if ( null !== ( $entity['maximum_amount'] ?? null ) ) {
				$props['maximum_amount'] = $this->to_decimal( (int) $entity['maximum_amount'] );
			}

			$products = $this->restricted_product_ids( (int) ( $entity['product_count'] ?? 0 ) );

			if ( array() !== $products ) {
				$props['product_ids'] = $products;
			}

			$coupon->set_props( $props );

			$id = $coupon->save();
		} catch ( WC_Data_Exception $e ) {
			return new WP_Error( 'coupon_creation_failed', $e->getMessage() );
		}

		if ( ! $id ) {
			return new WP_Error( 'coupon_creation_failed', __( 'Failed to create the coupon.', 'storeseeder' ) );
		}

		$result = array(
			'id'          => (int) $id,
			'code'        => $code,
			'type'        => $coupon->get_discount_type(),
			'discount'    => $free_shipping
				? __( 'free shipping', 'storeseeder' )
				: ( 'percent' === $coupon->get_discount_type() ? $amount . '%' : $amount ),
			'usage_limit' => (int) $entity['usage_limit'],
			'expires_at'  => (string) $entity['expires_at'],
			'created_at'  => current_time( 'Y-m-d H:i:s' ),
		);

		return $this->filter_result( $result, (int) $id, $result );
	}

	/**
	 * Draw the products a coupon is restricted to.
	 *
	 * The generator says how many, never which: only the platform knows what exists, and a coupon
	 * pointing at a product id that is not there restricts itself to nothing.
	 *
	 * @since 1.1.0
	 *
	 * @param int $count How many products to restrict to.
	 *
	 * @return array<int, int>
	 */
	private function restricted_product_ids( int $count ): array {
		if ( $count < 1 ) {
			return array();
		}

		return array_slice( $this->product_ids( $count ), 0, $count );
	}

	/**
	 * Remove a generated coupon.
	 *
	 * @since 1.1.0
	 *
	 * @param int|string $id Coupon id.
	 *
	 * @return true|WP_Error
	 */
	public function delete( $id ) {
		if ( ! class_exists( 'WC_Coupon' ) ) {
			return new WP_Error(
				'storeseeder_delete_unsupported',
				__( 'WooCommerce is not active on this site.', 'storeseeder' )
			);
		}

		if ( ! get_post( (int) $id ) ) {
			return true;
		}

		try {
			$coupon = new WC_Coupon( (int) $id );
			$coupon->delete( true );
		} catch ( \Exception $e ) {
			return new WP_Error( 'storeseeder_delete_failed', $e->getMessage() );
		}

		return true;
	}

	/**
	 * A coupon code nothing else is using.
	 *
	 * Two coupons sharing a code is not a database error in WooCommerce — the second one is
	 * simply never found, because lookup is by code and returns the first match. That is a
	 * fixture that wastes an afternoon.
	 *
	 * @since 1.1.0
	 *
	 * @param string $proposed Code the generator proposed.
	 *
	 * @return string
	 */
	private function unique_code( string $proposed ): string {
		$code = wc_format_coupon_code( $proposed );

		for ( $attempt = 0; $attempt < 5; $attempt++ ) {
			if ( 0 === wc_get_coupon_id_by_code( $code ) ) {
				return $code;
			}

			$code = wc_format_coupon_code( $proposed . strtoupper( $this->faker()->bothify( '??' ) ) );
		}

		return wc_format_coupon_code( $proposed . strtoupper( substr( md5( (string) wp_generate_uuid4() ), 0, 4 ) ) );
	}
}
