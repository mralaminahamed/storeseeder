<?php
/**
 * Fluent Cart cart session writer
 *
 * @since   1.1.0
 * @package StoreSeeder\Platforms\Drivers\Fluent_Cart\Writers
 */

namespace StoreSeeder\Platforms\Drivers\Fluent_Cart\Writers;

use Exception;
use FluentCart\App\Models\Cart as CartModel;
use FluentCart\App\Models\Customer as CustomerModel;
use FluentCart\App\Models\ProductVariation as ProductVariationModel;
use StoreSeeder\Platforms\Writer;
use StoreSeeder\Platforms\Resource;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Persists a canonical cart session into Fluent Cart.
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
	 * Create a Fluent Cart cart session.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $entity Canonical cart session entity.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function write( array $entity ) {
		// Check if Fluent Cart Cart model is available.
		if ( ! class_exists( CartModel::class ) ) {
			return new WP_Error( 'missing_model', __( 'Fluent Cart Cart model not found. Please ensure Fluent Cart plugin is active.', 'storeseeder' ) );
		}

		$items   = (array) $entity['items'];
		$user_id = $entity['logged_in'] ? $this->random_user_id() : null;

		$session_data = array(
			'customer_id' => $user_id ? $this->random_customer_id() : null,
			'user_id'     => $user_id,
			'cart_data'   => $this->build_cart_data( $items ),
			'stage'       => $entity['stage'],
			// 'global' is the column default Fluent Cart uses; 'default' is not
			// a group it ever reads.
			'cart_group'  => 'global',
			'user_agent'  => $entity['user_agent'],
			'ip_address'  => $entity['ip_address'],
		);

		// A guest cart carries its own contact details. Also used when the entity
		// asked for a logged-in cart but the site has no users to attach one to.
		if ( ! $user_id && isset( $entity['guest'] ) ) {
			$session_data['email']      = $entity['guest']['email'];
			$session_data['first_name'] = $entity['guest']['first_name'];
			$session_data['last_name']  = $entity['guest']['last_name'];
		}

		if ( isset( $entity['checkout'] ) ) {
			$checkout                      = $entity['checkout'];
			$session_data['checkout_data'] = array(
				'form_data' => array(
					'billing_full_name' => $checkout['full_name'],
					'billing_email'     => $checkout['email'],
					'billing_address_1' => $checkout['address_1'],
					'billing_city'      => $checkout['city'],
					'billing_state'     => $checkout['state'],
					'billing_postcode'  => $checkout['postcode'],
					'billing_country'   => $checkout['country'],
				),
			);
		}

		$cart = $this->create_cart_session( $session_data );

		if ( ! $cart ) {
			return new WP_Error( 'cart_session_creation_failed', __( 'Failed to create cart session.', 'storeseeder' ) );
		}

		$result = array(
			// fct_carts has no id column — the primary key is cart_hash, and
			// the model sets $incrementing = false. $cart->id was always null.
			'id'             => $cart->cart_hash,
			'cart_hash'      => $cart->cart_hash,
			'user_id'        => $cart->user_id,
			'stage'          => $cart->stage,
			'items_count'    => count( $cart->cart_data ?? array() ),
			'customer_email' => $cart->email,
			'created_at'     => $cart->created_at,
		);

		return $this->filter_result( $result, $cart->cart_hash, $session_data );
	}

	/**
	 * Pair the entity's item shapes with real product variations.
	 *
	 * Each cart_data entry's object_id is a foreign key into
	 * fct_product_variations. Random integers produce carts full of products
	 * that do not exist, so the cart is only as long as there are products for.
	 *
	 * @since 1.1.0
	 *
	 * @param array<int, array<string, mixed>> $items Canonical item shapes.
	 *
	 * @return array<int, array<string, mixed>> Fluent Cart cart_data entries.
	 */
	private function build_cart_data( array $items ): array {
		if ( array() === $items ) {
			return array();
		}

		$variations = ProductVariationModel::query()
			->inRandomOrder()
			->limit( count( $items ) )
			->get();

		$cart_items = array();
		$index      = 0;

		foreach ( $variations as $variation ) {
			$item = $items[ $index ] ?? null;

			if ( null === $item ) {
				break;
			}

			$cart_items[] = array(
				'object_id'   => (int) $variation->id,
				'object_type' => 'product_variation',
				// Cart money is in integer cents, same as order items.
				'unit_price'  => (int) $item['unit_price'],
				'quantity'    => (int) $item['quantity'],
				'line_total'  => 0, // Will be calculated by Fluent Cart.
				'other_info'  => array(),
			);

			++$index;
		}

		return $cart_items;
	}

	/**
	 * Draw a real customer ID.
	 *
	 * @since 1.1.0
	 *
	 * @return int|null Customer ID, or null when the store has no customers.
	 */
	private function random_customer_id(): ?int {
		$customer = CustomerModel::query()->inRandomOrder()->first();

		return $customer ? (int) $customer->id : null;
	}

	/**
	 * Draw a real WordPress user ID.
	 *
	 * @since 1.1.0
	 *
	 * @return int|null User ID, or null when no users match.
	 */
	private function random_user_id(): ?int {
		$user_ids = get_users(
			array(
				'number'  => 1,
				'orderby' => 'rand',
				'fields'  => 'ID',
			)
		);

		return empty( $user_ids ) ? null : (int) $user_ids[0];
	}

	/**
	 * Create cart session in Fluent Cart.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $data Fluent-Cart-shaped cart data.
	 *
	 * @return CartModel|null Created cart instance.
	 */
	private function create_cart_session( array $data ): ?CartModel {
		try {
			$created = CartModel::query()->create( $data );

			// Eloquent's create() is reached through __callStatic, so its declared type is
			// Builder|Model rather than this model. Narrowing here is what makes the
			// nullable return type above true rather than merely intended.
			return $created instanceof CartModel ? $created : null;
		} catch ( Exception $e ) {
			return null;
		}
	}

	/**
	 * Remove a generated cart session.
	 *
	 * Keyed by cart_hash, not id: fct_carts has no id column, which is why the writer
	 * reported the hash in the first place.
	 *
	 * @since 1.1.0
	 *
	 * @param int|string $id The identifier reported when the row was created.
	 *
	 * @return true|WP_Error
	 */
	public function delete( $id ) {
		return $this->delete_model( CartModel::class, $id, array(), 'cart_hash' );
	}
}
