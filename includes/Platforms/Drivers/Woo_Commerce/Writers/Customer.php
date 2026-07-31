<?php
/**
 * WooCommerce customer writer
 *
 * @since   1.1.0
 * @package StoreSeeder\Platforms\Drivers\Woo_Commerce\Writers
 */

namespace StoreSeeder\Platforms\Drivers\Woo_Commerce\Writers;

use StoreSeeder\Platforms\Drivers\Woo_Commerce\Writer;
use StoreSeeder\Platforms\Resource;
use WC_Customer;
use WC_Data_Exception;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Persists a canonical customer into WooCommerce.
 *
 * A WooCommerce customer **is** a WordPress user — there is no separate customer table for a
 * registered one, and the billing and shipping fields are user meta the CRUD object owns. So
 * `with_account` cannot mean what it means on Fluent Cart, where a customer row exists
 * independently. See `write()`.
 *
 * @since 1.1.0
 */
final class Customer extends Writer {
	/**
	 * The resource this writer persists.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public function resource(): string {
		return Resource::CUSTOMER;
	}

	/**
	 * Create a WooCommerce customer.
	 *
	 * `with_account` is honoured in the only way WooCommerce allows: a customer always gets a
	 * user account, because that is what a WooCommerce customer is. The entity's guest case is
	 * served by the Orders generator instead, which can write a guest order carrying the same
	 * addresses and no account — so nothing is lost, and this writer does not pretend to store
	 * a customer record that WooCommerce has nowhere to put.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $entity Canonical customer entity.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function write( array $entity ) {
		if ( ! class_exists( 'WC_Customer' ) ) {
			return new WP_Error(
				'missing_woocommerce',
				__( 'WooCommerce is not active on this site.', 'storeseeder' )
			);
		}

		$email = (string) $entity['email'];

		if ( email_exists( $email ) ) {
			return new WP_Error(
				'email_exists',
				__( 'A user with this email address already exists.', 'storeseeder' )
			);
		}

		$meta     = (array) $entity['meta'];
		$billing  = (array) $entity['billing_address'];
		$shipping = (array) $entity['shipping_address'];
		$username = $this->unique_username( (string) $entity['username_base'] );

		$customer = new WC_Customer();

		try {
			$customer->set_props(
				array(
					'email'        => $email,
					'first_name'   => $entity['first_name'],
					'last_name'    => $entity['last_name'],
					'username'     => $username,
					'password'     => wp_generate_password( 16, true, true ),
					'role'         => 'customer',
					// The account is as old as the customer. Left unset, a customer "since 2021"
					// registered today, and every cohort report read the registration date.
					'date_created' => (string) ( $entity['date_created'] ?? current_time( 'Y-m-d H:i:s' ) ),
				)
			);

			$this->set_address( $customer, 'billing', $billing );
			$this->set_address( $customer, 'shipping', array() === $shipping ? $billing : $shipping );

			$id = $customer->save();
		} catch ( WC_Data_Exception $e ) {
			// The CRUD layer validates emails and usernames and throws rather than
			// returning — a duplicate that slipped past email_exists() lands here.
			return new WP_Error( 'customer_creation_failed', $e->getMessage() );
		}

		if ( ! $id ) {
			return new WP_Error( 'customer_creation_failed', __( 'Failed to create the customer.', 'storeseeder' ) );
		}

		// `WC_Customer` accepts a `date_created` and does not carry it through to
		// `wp_users.user_registered`, so every generated customer registered today however old
		// their history said they were. Written directly, where WordPress does read it.
		if ( ! empty( $entity['date_created'] ) ) {
			wp_update_user(
				array(
					'ID'              => (int) $id,
					'user_registered' => (string) $entity['date_created'],
				)
			);
		}

		// Purchase history is metadata, not orders. WooCommerce reads these two keys for the
		// customer's lifetime value in reports, so writing them makes the generated history
		// consistent with what the admin shows. `_money_spent` is a decimal, and the entity
		// carries minor units.
		update_user_meta( (int) $id, '_money_spent', $this->to_decimal( (int) $meta['total_spent'] ) );
		update_user_meta( (int) $id, '_order_count', (int) $meta['total_orders'] );

		if ( ! empty( $entity['notes'] ) ) {
			update_user_meta( (int) $id, 'description', (string) $entity['notes'] );
		}

		// The demographic and loyalty fields, which WooCommerce has no columns for. Namespaced so
		// they are identifiable, and so nothing collides with a real plugin's keys.
		foreach ( $this->profile_meta( $meta ) as $key => $value ) {
			update_user_meta( (int) $id, 'storeseeder_' . $key, $value );
		}

		$result = array(
			'id'              => (int) $id,
			'name'            => $entity['full_name'],
			'email'           => $email,
			'username'        => $username,
			'phone'           => $billing['phone'] ?? '',
			'billing_city'    => $billing['city'] ?? '',
			'billing_country' => $billing['country'] ?? '',
			'total_orders'    => (int) $meta['total_orders'],
			'total_spent'     => '$' . $this->to_decimal( (int) $meta['total_spent'] ),
			'created_at'      => (string) ( $entity['date_created'] ?? current_time( 'Y-m-d H:i:s' ) ),
		);

		return $this->filter_result( $result, (int) $id, $result );
	}

	/**
	 * Remove a generated customer.
	 *
	 * `wc_get_customer` does not exist, so the CRUD object is constructed directly. Deleting
	 * it removes the WordPress user with it, which is correct here: this writer created that
	 * user, unlike the Fluent Cart one, which attaches to an existing account.
	 *
	 * @since 1.1.0
	 *
	 * @param int|string $id Customer (user) id.
	 *
	 * @return true|WP_Error
	 */
	public function delete( $id ) {
		if ( ! class_exists( 'WC_Customer' ) ) {
			return new WP_Error(
				'storeseeder_delete_unsupported',
				__( 'WooCommerce is not active on this site.', 'storeseeder' )
			);
		}

		if ( ! get_userdata( (int) $id ) ) {
			return true;
		}

		try {
			$customer = new WC_Customer( (int) $id );
			$customer->delete( true );
		} catch ( \Exception $e ) {
			return new WP_Error( 'storeseeder_delete_failed', $e->getMessage() );
		}

		return true;
	}

	/**
	 * Copy one canonical address onto the customer.
	 *
	 * @since 1.1.0
	 *
	 * @param WC_Customer          $customer The customer being built.
	 * @param string               $type     `billing` or `shipping`.
	 * @param array<string, mixed> $address  Canonical address.
	 *
	 * @return void
	 */
	private function set_address( WC_Customer $customer, string $type, array $address ): void {
		if ( array() === $address ) {
			return;
		}

		// Either shape. A customer address carries `first_name` and `last_name`; an order address
		// carries one `name`. Reading only the second is why every generated WooCommerce customer
		// had an empty billing name — the fields are on the profile screen and in the admin's
		// customer list, so it was visible and still went unnoticed.
		if ( isset( $address['first_name'] ) || isset( $address['last_name'] ) ) {
			$first = (string) ( $address['first_name'] ?? '' );
			$last  = (string) ( $address['last_name'] ?? '' );
		} else {
			// explode() always yields the first element; the second only when there was a space to
			// split on, which a one-word name has not.
			$name  = explode( ' ', (string) ( $address['name'] ?? '' ), 2 );
			$first = $name[0];
			$last  = $name[1] ?? '';
		}

		$props = array(
			$type . '_first_name' => $first,
			$type . '_last_name'  => $last,
			// Optional on the entity, and null where absent — cast so a null never reaches a
			// setter typed for a string.
			$type . '_company'    => (string) ( $address['company'] ?? '' ),
			$type . '_address_1'  => $address['address_1'] ?? '',
			$type . '_address_2'  => (string) ( $address['address_2'] ?? '' ),
			$type . '_city'       => $address['city'] ?? '',
			$type . '_state'      => $address['state'] ?? '',
			$type . '_postcode'   => $address['postcode'] ?? '',
			$type . '_country'    => $address['country'] ?? '',
		);

		// Only billing carries a phone and an email in WooCommerce.
		if ( 'billing' === $type ) {
			$props['billing_phone'] = (string) ( $address['phone'] ?? '' );
			$props['billing_email'] = (string) ( $address['email'] ?? '' );
		}

		$customer->set_props( $props );
	}

	/**
	 * A username nothing else is using.
	 *
	 * @since 1.1.0
	 *
	 * @param string $base Username the generator proposed.
	 *
	 * @return string
	 */
	private function unique_username( string $base ): string {
		$username = sanitize_user( $base, true );

		if ( '' === $username ) {
			$username = 'customer';
		}

		$candidate = $username;

		for ( $attempt = 1; $attempt <= 20; $attempt++ ) {
			if ( ! username_exists( $candidate ) ) {
				return $candidate;
			}

			$candidate = $username . $attempt;
		}

		return $username . '-' . substr( md5( (string) wp_generate_uuid4() ), 0, 6 );
	}
}
