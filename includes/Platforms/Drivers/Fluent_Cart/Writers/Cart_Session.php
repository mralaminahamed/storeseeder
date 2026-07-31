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
use FluentCart\App\Models\Order as OrderModel;
use FluentCart\App\Models\ProductVariation as ProductVariationModel;
use StoreSeeder\Platforms\Writer;
use StoreSeeder\Platforms\Resource;
use StoreSeeder\Platforms\Status as CanonicalStatus;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Persists a canonical cart session into Fluent Cart.
 *
 * @since 1.1.0
 */
final class Cart_Session extends Writer {
	/**
	 * Canonical cart stage to Fluent Cart's spelling.
	 *
	 * Its `Cart` model treats anything that is not `completed` as an open cart, and its checkout
	 * moves a cart to `intended` before converting it. Abandonment is not a stage there at all — an
	 * abandoned cart is one that reached `intended` and never got an order — so that is what
	 * `abandoned` maps to, and the writer leaves the order link empty for it.
	 *
	 * @since 1.1.0
	 *
	 * @var array<string, string>
	 */
	const CART_STAGE = array(
		CanonicalStatus::CART_ACTIVE    => 'draft',
		CanonicalStatus::CART_ABANDONED => 'intended',
		CanonicalStatus::CART_CONVERTED => 'completed',
	);

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

		$items    = (array) $entity['items'];
		$stage    = (string) $entity['stage'];
		$customer = $this->requested_customer();
		$user_id  = $entity['logged_in'] ? $this->cart_user_id( $customer ) : null;

		$session_data = array(
			// A cart attached to a WordPress user belongs to that user's customer record, not to a
			// random one: the two columns disagreeing is a cart that shows under one name in the
			// admin and another in the customer's own account.
			'customer_id' => $user_id ? $this->customer_id_for( $user_id, $customer ) : null,
			'user_id'     => $user_id,
			'cart_data'   => $this->build_cart_data( $items ),
			// Mapped, not passed through. An unrecognised stage is an open cart to Fluent Cart's own
			// query scope, so a typo silently turns a converted cart into an abandoned one.
			'stage'       => self::CART_STAGE[ $stage ] ?? 'draft',
			// 'global' is the column default Fluent Cart uses; 'default' is not
			// a group it ever reads.
			'cart_group'  => 'global',
			'user_agent'  => $entity['user_agent'],
			'ip_address'  => $entity['ip_address'],
		);

		// Not passed to create(): `created_at` is absent from the Cart model's fillable list, so
		// mass assignment drops it and Eloquent stamps today — the same trap that put every
		// generated customer's registration date at today's date. Written after the insert.
		$started_at = (string) ( $entity['created_at'] ?? current_time( 'Y-m-d H:i:s' ) );

		// A converted cart has an order behind it and a completion date. Without them the cart is
		// marked completed and appears in no revenue figure, which is the same as not converting.
		if ( CanonicalStatus::CART_CONVERTED === $stage ) {
			$order = $this->random_order( $customer );

			if ( $order ) {
				$session_data['order_id'] = (int) $order->id;
			}

			$session_data['completed_at'] = $started_at;
		}

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

		CartModel::query()
			->where( 'cart_hash', $cart->cart_hash )
			->update( array( 'created_at' => $started_at ) );

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
				// Cart money is in integer cents, same as order items — and taken from the variation
				// this line points at rather than the generated fallback, so a cart's value agrees
				// with the catalogue it was filled from.
				'unit_price'  => (int) ( $variation->item_price ?? 0 ) > 0
					? (int) $variation->item_price
					: (int) $item['unit_price'],
				'quantity'    => (int) $item['quantity'],
				'line_total'  => 0, // Will be calculated by Fluent Cart.
				'other_info'  => array(),
			);

			++$index;
		}

		return $cart_items;
	}

	/**
	 * The customer this run was pinned to, if any.
	 *
	 * `customer_type` and `specific_customer_id` were declared on three surfaces and read by
	 * nothing, so a run asked for one customer's carts got the whole store's.
	 *
	 * @since 1.1.0
	 *
	 * @return CustomerModel|null
	 */
	private function requested_customer(): ?CustomerModel {
		$requested = (int) ( $this->params['customer_id'] ?? 0 );

		if ( $requested < 1 ) {
			return null;
		}

		$customer = CustomerModel::query()->find( $requested );

		return $customer instanceof CustomerModel ? $customer : null;
	}

	/**
	 * The WordPress user this cart belongs to.
	 *
	 * @since 1.1.0
	 *
	 * @param CustomerModel|null $customer The pinned customer, if the run named one.
	 *
	 * @return int|null
	 */
	private function cart_user_id( ?CustomerModel $customer ): ?int {
		if ( $customer && $customer->user_id ) {
			return (int) $customer->user_id;
		}

		return $this->random_user_id();
	}

	/**
	 * The customer record for a cart's user.
	 *
	 * @since 1.1.0
	 *
	 * @param int                $user_id  The WordPress user the cart belongs to.
	 * @param CustomerModel|null $customer The pinned customer, if the run named one.
	 *
	 * @return int|null
	 */
	private function customer_id_for( int $user_id, ?CustomerModel $customer ): ?int {
		if ( $customer ) {
			return (int) $customer->id;
		}

		$match = CustomerModel::query()->where( 'user_id', $user_id )->first();

		if ( $match instanceof CustomerModel ) {
			return (int) $match->id;
		}

		// No customer record for this user yet, which is an ordinary state — a shopper who has an
		// account but has never bought anything.
		return null;
	}

	/**
	 * An order for a converted cart to point at.
	 *
	 * @since 1.1.0
	 *
	 * @param CustomerModel|null $customer The pinned customer, if the run named one.
	 *
	 * @return OrderModel|null
	 */
	private function random_order( ?CustomerModel $customer ): ?OrderModel {
		if ( ! class_exists( OrderModel::class ) ) {
			return null;
		}

		$query = OrderModel::query();

		if ( $customer ) {
			$query->where( 'customer_id', $customer->id );
		}

		$order = $query->inRandomOrder()->first();

		return $order instanceof OrderModel ? $order : null;
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
