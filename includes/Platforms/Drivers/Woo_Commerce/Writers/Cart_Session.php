<?php
/**
 * WooCommerce cart session writer
 *
 * @since   1.1.0
 * @package StoreSeeder\Platforms\Drivers\Woo_Commerce\Writers
 */

namespace StoreSeeder\Platforms\Drivers\Woo_Commerce\Writers;

use StoreSeeder\Platforms\Drivers\Woo_Commerce\Writer;
use StoreSeeder\Platforms\Resource;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Persists a canonical cart session into WooCommerce's session table.
 *
 * WooCommerce keeps carts in `{prefix}woocommerce_sessions`: one row per shopper, keyed by
 * customer id for a logged-in user and by a random key for a guest, holding a serialised map of
 * session keys — `cart`, `customer`, `cart_totals` — each of which is *itself* serialised. That
 * double serialisation is not decoration: `WC_Session_Handler::get_session_data()` runs
 * `maybe_unserialize()` on the outer value and then again per key, so a single-serialised row
 * reads back as a string and the cart appears empty.
 *
 * Written directly rather than through `WC_Session_Handler`, because the handler only ever
 * writes *the current request's* session — there is no API for "create a session belonging to
 * somebody else", which is exactly what seeding abandoned carts is.
 *
 * @since 1.1.0
 */
final class Cart_Session extends Writer {
	/**
	 * The resource this writer persists.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public function resource(): string {
		return Resource::CART_SESSION;
	}

	/**
	 * Create a WooCommerce session holding a cart.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $entity Canonical cart-session entity.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function write( array $entity ) {
		global $wpdb;

		if ( ! class_exists( 'WC_Session_Handler' ) ) {
			return new WP_Error(
				'missing_woocommerce',
				__( 'WooCommerce is not active on this site.', 'storeseeder' )
			);
		}

		$products = $this->product_ids( count( (array) $entity['items'] ) );

		if ( array() === $products ) {
			return $this->missing_prerequisite(
				'no_products',
				__( 'No products were found. Generate products before generating cart sessions.', 'storeseeder' )
			);
		}

		$logged_in   = (bool) $entity['logged_in'];
		$customer_id = $logged_in ? $this->random_customer_id() : 0;

		// A logged-in cart is keyed by the customer id; a guest one by a random key, which is
		// what WooCommerce puts in the shopper's cookie.
		$key = $customer_id > 0
			? (string) $customer_id
			: 't_' . substr( md5( (string) wp_generate_uuid4() ), 0, 30 );

		if ( $this->session_exists( $key ) ) {
			return new WP_Error(
				'session_exists',
				__( 'The customer drawn already has a cart session. Generate more customers, or fewer sessions.', 'storeseeder' )
			);
		}

		$cart  = $this->build_cart( (array) $entity['items'], $products );
		$total = $this->cart_total( $cart );

		// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- not a
		// choice: this is the format WC_Session_Handler::get_session_data() reads back, per
		// key, inside the serialised row. JSON here would read as an empty cart.
		$session = array(
			'cart'              => serialize( $cart ),
			'cart_totals'       => serialize( $this->totals( $total ) ),
			'applied_coupons'   => serialize( array() ),
			'customer'          => serialize( $this->customer_fields( $entity, $customer_id ) ),
			// The stage the entity describes has no WooCommerce column; recorded in the
			// session so a plugin reading these carts can tell them apart, and so the
			// generated distinction is not simply lost.
			'storeseeder_stage' => serialize( (string) $entity['stage'] ),
		);
		// phpcs:enable WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize

		$expiry = time() + (int) apply_filters( 'wc_session_expiration', 2 * DAY_IN_SECONDS );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- WC_Session_Handler can only write the current request's session.
		$written = $wpdb->insert(
			$wpdb->prefix . 'woocommerce_sessions',
			array(
				'session_key'    => $key,
				'session_value'  => maybe_serialize( $session ),
				'session_expiry' => $expiry,
			),
			array( '%s', '%s', '%d' )
		);

		if ( false === $written ) {
			return new WP_Error( 'cart_session_creation_failed', __( 'Failed to create the cart session.', 'storeseeder' ) );
		}

		// The persistent cart is what WooCommerce restores when a logged-in shopper comes
		// back on another device, and what most abandoned-cart tooling reads.
		if ( $customer_id > 0 ) {
			update_user_meta(
				$customer_id,
				'_woocommerce_persistent_cart_' . get_current_blog_id(),
				array( 'cart' => $cart )
			);
		}

		$data = array(
			'id'          => $key,
			'customer_id' => $customer_id,
			'items'       => count( $cart ),
		);

		$result = array(
			// The session key, not the row id: it is what WooCommerce looks a session up by,
			// and what the cleanup needs to remove one.
			'id'             => $key,
			'session_key'    => $key,
			'stage'          => (string) $entity['stage'],
			'customer_id'    => $customer_id,
			'items_count'    => count( $cart ),
			'total'          => (string) wc_format_decimal( $total, wc_get_price_decimals() ),
			'customer_email' => $this->email( $entity, $customer_id ),
			'created_at'     => current_time( 'Y-m-d H:i:s' ),
		);

		return $this->filter_result( $result, $key, $data );
	}

	/**
	 * Remove a generated cart session.
	 *
	 * @since 1.1.0
	 *
	 * @param int|string $id The session key.
	 *
	 * @return true|WP_Error
	 */
	public function delete( $id ) {
		global $wpdb;

		$key = (string) $id;

		if ( '' === $key ) {
			return true;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- own row in WooCommerce's session table; the handler cannot delete another shopper's.
		$wpdb->delete( $wpdb->prefix . 'woocommerce_sessions', array( 'session_key' => $key ), array( '%s' ) );

		// A numeric key is a customer id, so the persistent cart written alongside it goes
		// too — leaving it would restore the cart this just deleted on their next visit.
		if ( ctype_digit( $key ) ) {
			delete_user_meta( (int) $key, '_woocommerce_persistent_cart_' . get_current_blog_id() );
		}

		return true;
	}

	/**
	 * Whether a session already exists for this key.
	 *
	 * @since 1.1.0
	 *
	 * @param string $key Session key.
	 *
	 * @return bool
	 */
	private function session_exists( string $key ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- see the class docblock.
		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT session_id FROM {$wpdb->prefix}woocommerce_sessions WHERE session_key = %s LIMIT 1",
				$key
			)
		);
	}

	/**
	 * Build the cart contents, in the shape WooCommerce's cart expects.
	 *
	 * Every key here is one `WC_Cart::get_cart()` consumers read. A cart missing `line_total`
	 * renders as zero in the admin and in every abandoned-cart email.
	 *
	 * @since 1.1.0
	 *
	 * @param array<int, mixed> $items    Canonical items.
	 * @param array<int, int>   $products Product ids to draw from.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function build_cart( array $items, array $products ): array {
		$cart = array();

		foreach ( $items as $index => $item ) {
			if ( ! is_array( $item ) || ! isset( $products[ $index ] ) ) {
				continue;
			}

			$product = wc_get_product( $products[ $index ] );

			if ( ! $product instanceof \WC_Product ) {
				continue;
			}

			$quantity = max( 1, (int) ( $item['quantity'] ?? 1 ) );
			$price    = '' !== (string) $product->get_price()
				? (float) $product->get_price()
				: (float) $this->to_decimal( (int) ( $item['unit_price'] ?? 0 ) );

			$cart_key = md5( (string) $product->get_id() . (string) $index );

			$cart[ $cart_key ] = array(
				'key'               => $cart_key,
				'product_id'        => $product->get_id(),
				'variation_id'      => 0,
				'variation'         => array(),
				'quantity'          => $quantity,
				'line_tax_data'     => array(
					'subtotal' => array(),
					'total'    => array(),
				),
				'line_subtotal'     => $price * $quantity,
				'line_subtotal_tax' => 0,
				'line_total'        => $price * $quantity,
				'line_tax'          => 0,
			);
		}

		return $cart;
	}

	/**
	 * The cart's total, from its lines.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, array<string, mixed>> $cart Cart contents.
	 *
	 * @return float
	 */
	private function cart_total( array $cart ): float {
		$total = 0.0;

		foreach ( $cart as $line ) {
			$total += (float) ( $line['line_total'] ?? 0 );
		}

		return $total;
	}

	/**
	 * The `cart_totals` session key.
	 *
	 * @since 1.1.0
	 *
	 * @param float $total The cart's total.
	 *
	 * @return array<string, mixed>
	 */
	private function totals( float $total ): array {
		return array(
			'subtotal'            => $total,
			'subtotal_tax'        => 0,
			'shipping_total'      => 0,
			'shipping_tax'        => 0,
			'shipping_taxes'      => array(),
			'discount_total'      => 0,
			'discount_tax'        => 0,
			'cart_contents_total' => $total,
			'cart_contents_tax'   => 0,
			'cart_contents_taxes' => array(),
			'fee_total'           => 0,
			'fee_tax'             => 0,
			'fee_taxes'           => array(),
			'total'               => $total,
			'total_tax'           => 0,
		);
	}

	/**
	 * The `customer` session key.
	 *
	 * A guest cart carries its own contact details, because there is no account to read them
	 * from — which is what makes a guest abandoned cart reachable by email at all.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $entity      The canonical entity.
	 * @param int                  $customer_id Customer id, or 0 for a guest.
	 *
	 * @return array<string, mixed>
	 */
	private function customer_fields( array $entity, int $customer_id ): array {
		$guest = (array) ( $entity['guest'] ?? array() );

		$fields = array(
			'id'    => (string) $customer_id,
			'email' => $this->email( $entity, $customer_id ),
		);

		if ( array() !== $guest ) {
			$fields['first_name']         = (string) ( $guest['first_name'] ?? '' );
			$fields['last_name']          = (string) ( $guest['last_name'] ?? '' );
			$fields['billing_first_name'] = $fields['first_name'];
			$fields['billing_last_name']  = $fields['last_name'];
		}

		$checkout = (array) ( $entity['checkout'] ?? array() );

		if ( array() !== $checkout ) {
			$fields['billing_address_1'] = (string) ( $checkout['address_1'] ?? '' );
			$fields['billing_city']      = (string) ( $checkout['city'] ?? '' );
			$fields['billing_state']     = (string) ( $checkout['state'] ?? '' );
			$fields['billing_postcode']  = (string) ( $checkout['postcode'] ?? '' );
			$fields['billing_country']   = (string) ( $checkout['country'] ?? '' );
		}

		return $fields;
	}

	/**
	 * The email to record against the session.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $entity      The canonical entity.
	 * @param int                  $customer_id Customer id, or 0 for a guest.
	 *
	 * @return string
	 */
	private function email( array $entity, int $customer_id ): string {
		$guest = (array) ( $entity['guest'] ?? array() );

		if ( isset( $guest['email'] ) ) {
			return (string) $guest['email'];
		}

		if ( $customer_id > 0 ) {
			$user = get_userdata( $customer_id );

			if ( $user ) {
				return (string) $user->user_email;
			}
		}

		$checkout = (array) ( $entity['checkout'] ?? array() );

		return (string) ( $checkout['email'] ?? '' );
	}
}
