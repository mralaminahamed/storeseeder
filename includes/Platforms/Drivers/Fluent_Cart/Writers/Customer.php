<?php
/**
 * Fluent Cart customer writer
 *
 * @since   1.1.0
 * @package StoreSeeder\Platforms\Drivers\Fluent_Cart\Writers
 */

namespace StoreSeeder\Platforms\Drivers\Fluent_Cart\Writers;

use FluentCart\App\Models\Customer as CustomerModel;
use FluentCart\App\Models\CustomerAddresses as CustomerAddressModel;
use StoreSeeder\Platforms\Writer;
use StoreSeeder\Platforms\Resource;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Persists a canonical customer into Fluent Cart.
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
	 * Create a Fluent Cart customer, optionally with a WordPress account.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $entity Canonical customer entity.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function write( array $entity ) {
		if ( ! defined( 'FLUENTCART_VERSION' ) ) {
			return new WP_Error( 'missing_fluent_cart', __( 'Fluent Cart plugin not found. Please ensure Fluent Cart is active.', 'storeseeder' ) );
		}

		if ( ! class_exists( CustomerModel::class ) ) {
			return new WP_Error( 'missing_model', __( 'Fluent Cart Customer model not found. Please ensure Fluent Cart plugin is active.', 'storeseeder' ) );
		}

		$email = (string) $entity['email'];

		// Check if user with this email already exists.
		if ( email_exists( $email ) ) {
			return new WP_Error( 'email_exists', __( 'A user with this email address already exists.', 'storeseeder' ) );
		}

		$meta             = (array) $entity['meta'];
		$billing_address  = (array) $entity['billing_address'];
		$shipping_address = (array) $entity['shipping_address'];

		// Resolved once and reused, so the username reported back is the one the
		// account was actually created with. Computing it twice — as this generator
		// used to — meant the second call saw the first username already taken and
		// reported a name belonging to nobody.
		$username = $this->unique_username( (string) $entity['username_base'] );

		$customer_data = array(
			'email'          => $email,
			'first_name'     => $entity['first_name'],
			'last_name'      => $entity['last_name'],
			'status'         => 'active',
			'purchase_value' => $meta['total_spent'],
			'purchase_count' => $meta['total_orders'],
			'ltv'            => $meta['total_spent'],
			'aov'            => $meta['total_orders'] > 0 ? $meta['total_spent'] / $meta['total_orders'] : 0,
			'notes'          => $entity['notes'],
		);

		// Create customer using Fluent Cart Customer model.
		$customer = CustomerModel::query()->firstOrCreate(
			array( 'email' => $email ),
			$customer_data
		);

		if ( ! $customer instanceof CustomerModel ) {
			return new WP_Error( 'customer_creation_failed', __( 'Failed to create customer using Fluent Cart model.', 'storeseeder' ) );
		}

		if ( $entity['with_account'] ) {
			$user_id = wp_insert_user(
				array(
					'user_login' => $username,
					'user_email' => $email,
					'first_name' => $entity['first_name'],
					'last_name'  => $entity['last_name'],
					'user_pass'  => wp_generate_password( 16, true, true ),
					'role'       => 'customer',
				)
			);

			if ( ! is_wp_error( $user_id ) ) {
				$customer->update( array( 'user_id' => $user_id ) );
			}
		}

		// Persist the generated addresses to fct_customer_addresses. They were
		// built and then discarded, leaving the customer with an empty address
		// book that pre-filled nothing at checkout and showed no address in the
		// admin profile.
		$this->create_customer_addresses( (int) $customer->id, $billing_address, $shipping_address );

		$customer_id = $customer->id;

		$result = array(
			'id'              => $customer_id,
			'name'            => $entity['full_name'],
			'email'           => $email,
			'username'        => $username,
			'phone'           => $billing_address['phone'],
			'billing_city'    => $billing_address['city'],
			'billing_country' => $billing_address['country'],
			'shipping_city'   => ! empty( $shipping_address ) ? $shipping_address['city'] : $billing_address['city'],
			'customer_since'  => $meta['customer_since'],
			'loyalty_tier'    => $meta['loyalty_tier'],
			'total_orders'    => $meta['total_orders'],
			'total_spent'     => '$' . number_format( $meta['total_spent'], 2 ),
			'last_login'      => $meta['last_login'],
		);

		/**
		 * Filters the customer generation result data.
		 *
		 * Allows developers to modify the returned customer data after generation.
		 *
		 * @since 1.0.0
		 * @hook  storeseeder_customer_generation_result
		 *
		 * @param array $result        The customer generation result data.
		 * @param int   $customer_id   The created customer ID.
		 * @param array $customer_data The original customer data used for creation.
		 */
		$result = apply_filters( 'storeseeder_customer_generation_result', $result, $customer_id, $entity );

		/**
		 * Fires after a customer has been successfully created.
		 *
		 * Allows developers to perform additional operations after customer creation,
		 * such as adding custom metadata, triggering related processes, or logging.
		 *
		 * @since 1.0.0
		 * @hook  storeseeder_after_customer_created
		 *
		 * @param int   $customer_id   The created customer ID.
		 * @param array $result        The customer generation result data.
		 * @param array $customer_data The original customer data used for creation.
		 */
		do_action( 'storeseeder_after_customer_created', $customer_id, $result, $entity );

		return $result;
	}

	/**
	 * Disambiguate an account name against the ones already registered.
	 *
	 * @since 1.1.0
	 *
	 * @param string $base Base username proposed by the generator.
	 *
	 * @return string Unused username.
	 */
	private function unique_username( string $base ): string {
		$username = sanitize_user( $base, true );
		$attempts = 0;

		while ( username_exists( $username ) && $attempts < 10 ) {
			$username = sanitize_user( $base . $this->faker()->numberBetween( 1, 999 ), true );
			++$attempts;
		}

		if ( username_exists( $username ) ) {
			$username = sanitize_user( $base . time(), true );
		}

		return $username;
	}

	/**
	 * Persist a customer's billing and shipping addresses.
	 *
	 * @since 1.1.0
	 *
	 * @param int                  $customer_id Customer row ID.
	 * @param array<string, mixed> $billing     Billing address fields.
	 * @param array<string, mixed> $shipping    Shipping address fields, empty when it
	 *                                          matches billing.
	 *
	 * @return void
	 */
	private function create_customer_addresses( int $customer_id, array $billing, array $shipping ): void {
		if ( ! class_exists( CustomerAddressModel::class ) || empty( $billing ) ) {
			return;
		}

		CustomerAddressModel::query()->create(
			$this->map_customer_address( $customer_id, $billing, 'billing' )
		);

		// The entity carries an empty shipping address when the customer ships to
		// their billing address, so only store a distinct one.
		if ( ! empty( $shipping ) ) {
			CustomerAddressModel::query()->create(
				$this->map_customer_address( $customer_id, $shipping, 'shipping' )
			);
		}
	}

	/**
	 * Shape a canonical address for the CustomerAddresses model.
	 *
	 * @since 1.1.0
	 *
	 * @param int                  $customer_id Customer row ID.
	 * @param array<string, mixed> $addr        Canonical address fields.
	 * @param string               $type        'billing' or 'shipping'.
	 *
	 * @return array<string, mixed> Column map for CustomerAddresses::create().
	 */
	private function map_customer_address( int $customer_id, array $addr, string $type ): array {
		$name = trim( ( $addr['first_name'] ?? '' ) . ' ' . ( $addr['last_name'] ?? '' ) );

		return array(
			'customer_id' => $customer_id,
			// Each type carries its own primary; Fluent Cart resolves the default
			// billing and default shipping address separately.
			'is_primary'  => 1,
			'type'        => $type,
			'status'      => 'active',
			'label'       => ucfirst( $type ),
			'name'        => $name,
			'address_1'   => $addr['address_1'] ?? '',
			'address_2'   => $addr['address_2'] ?? '',
			'city'        => $addr['city'] ?? '',
			'state'       => $addr['state'] ?? '',
			'postcode'    => $addr['postcode'] ?? '',
			'country'     => $addr['country'] ?? '',
			'phone'       => $addr['phone'] ?? '',
			'email'       => $addr['email'] ?? '',
			// company_name is not fillable; it lives under meta.other_data.
			'meta'        => array(
				'other_data' => array(
					'company_name' => $addr['company'] ?? '',
				),
			),
		);
	}
}
