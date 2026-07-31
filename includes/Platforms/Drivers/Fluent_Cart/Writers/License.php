<?php
/**
 * Fluent Cart licence writer
 *
 * @since   1.1.0
 * @package StoreSeeder\Platforms\Drivers\Fluent_Cart\Writers
 */

namespace StoreSeeder\Platforms\Drivers\Fluent_Cart\Writers;

use Exception;
use FluentCart\App\Models\Order as OrderModel;
use FluentCart\App\Models\ProductVariation as ProductVariationModel;
use StoreSeeder\Platforms\Resource;
use StoreSeeder\Platforms\Status;
use StoreSeeder\Platforms\Writer;
use WP_Error;

// Imported for the instanceof below and for static analysis. A `use` statement does not load
// a class, and neither does instanceof, so this file stays loadable on a site without Pro —
// the class_exists() guard in write() is what decides whether any of it runs.
use FluentCartPro\App\Modules\Licensing\Models\License as ProLicenseModel;

defined( 'ABSPATH' ) || exit;

/**
 * Persists a canonical licence into Fluent Cart.
 *
 * Licensing lives in Fluent Cart **Pro**: `fct_licenses` and `fct_license_activations` are
 * created by Pro's own migrator, and the models are in Pro's namespace. The driver therefore
 * reports this resource as needing Pro and names it, so the admin can say "install Fluent
 * Cart Pro" rather than dimming a tile. This writer checks again, because a capability
 * matrix computed at page load can be stale by the time a run reaches the writer — a plugin
 * can be deactivated in another tab.
 *
 * The Pro model is resolved by name rather than imported, since importing it would make this
 * file unloadable on a site without Pro.
 *
 * @since 1.1.0
 */
final class License extends Writer {
	/**
	 * Pro's licence model.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	const LICENSE_MODEL = 'FluentCartPro\\App\\Modules\\Licensing\\Models\\License';

	/**
	 * Pro's activation model, written only when the licence has activations.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	const ACTIVATION_MODEL = 'FluentCartPro\\App\\Modules\\Licensing\\Models\\LicenseActivation';

	/**
	 * The resource this writer persists.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public function resource(): string {
		return Resource::LICENSE;
	}

	/**
	 * Create a Fluent Cart licence.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $entity Canonical licence entity.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function write( array $entity ) {
		$model = self::LICENSE_MODEL;

		if ( ! class_exists( $model ) ) {
			return new WP_Error(
				'missing_fluent_cart_pro',
				__( 'Licences are stored by Fluent Cart Pro, which is not active on this site. Install and activate Fluent Cart Pro to generate them.', 'storeseeder' )
			);
		}

		// A licence is issued by a purchase: it carries the order and the customer who
		// bought it, so without a real order both would have to be invented.
		$order = OrderModel::query()->whereNotNull( 'customer_id' )->inRandomOrder()->first();

		if ( ! $order ) {
			return new WP_Error(
				'no_orders',
				__( 'No orders were found. Generate orders before generating licences.', 'storeseeder' )
			);
		}

		// The licensed product comes from a real variation, which carries both the product
		// post id and the variation id Pro records against the licence.
		$variation = ProductVariationModel::query()->with( 'product' )->inRandomOrder()->first();

		if ( ! $variation ) {
			return new WP_Error(
				'no_products',
				__( 'No product variations were found. Generate products before generating licences.', 'storeseeder' )
			);
		}

		$data = array(
			'status'           => $this->map_status( (string) $entity['status'] ),
			'limit'            => (int) $entity['site_limit'],
			'activation_count' => (int) $entity['activation_count'],
			'license_key'      => $this->unique_key( (string) $entity['license_key'] ),
			'product_id'       => (int) $variation->post_id,
			'variation_id'     => (int) $variation->id,
			'order_id'         => (int) $order->id,
			'customer_id'      => (int) $order->customer_id,
			'expiration_date'  => $entity['expires_at'],
			// Zero rather than null: the column is NOT NULL with a zero default, and Pro
			// reads it as "no subscription behind this licence".
			'subscription_id'  => 0,
		);

		$license = $this->create_license( $data );

		if ( null === $license ) {
			return new WP_Error( 'license_creation_failed', __( 'Failed to create the licence.', 'storeseeder' ) );
		}

		$activations = $this->create_activations( (int) $license->id, $data, $entity );

		$result = array(
			'id'               => (int) $license->id,
			'license_key'      => $data['license_key'],
			'status'           => $data['status'],
			'order_id'         => $data['order_id'],
			'customer_id'      => $data['customer_id'],
			'site_limit'       => 0 === $data['limit'] ? __( 'unlimited', 'storeseeder' ) : $data['limit'],
			'activation_count' => $activations,
			'expiration_date'  => $data['expiration_date'],
			'created_at'       => current_time( 'Y-m-d H:i:s' ),
		);

		/**
		 * Filters the licence generation result data.
		 *
		 * @since 1.1.0
		 * @hook  storeseeder_license_generation_result
		 *
		 * @param array<string, mixed> $result The generation result data.
		 * @param int                  $id     The created licence id.
		 * @param array<string, mixed> $data   The data used for creation.
		 */
		return apply_filters( 'storeseeder_license_generation_result', $result, (int) $license->id, $data );
	}

	/**
	 * Map a canonical licence status to Fluent Cart's spelling.
	 *
	 * They happen to coincide today. Mapped anyway, because the canonical vocabulary is
	 * deliberately no single platform's spelling, and a writer that skips the mapping is
	 * the one that breaks when a platform renames a status.
	 *
	 * @since 1.1.0
	 *
	 * @param string $status Canonical status.
	 *
	 * @return string
	 */
	private function map_status( string $status ): string {
		$map = array(
			Status::ACTIVE   => 'active',
			Status::EXPIRED  => 'expired',
			Status::DISABLED => 'disabled',
		);

		return $map[ $status ] ?? 'active';
	}

	/**
	 * A licence key nothing else is using.
	 *
	 * The column is indexed and a duplicate key would make two customers share a licence,
	 * which is the kind of test fixture that wastes an afternoon. Uniqueness belongs to the
	 * writer because the platform owns the index; the generator only proposes.
	 *
	 * @since 1.1.0
	 *
	 * @param string $proposed The key the generator proposed.
	 *
	 * @return string
	 */
	private function unique_key( string $proposed ): string {
		$model = self::LICENSE_MODEL;
		$key   = $proposed;

		for ( $attempt = 0; $attempt < 5; $attempt++ ) {
			if ( ! $model::query()->where( 'license_key', $key )->exists() ) {
				return $key;
			}

			$key = $proposed . '-' . strtoupper( $this->faker()->bothify( '??##' ) );
		}

		// Five collisions on random hex means something is wrong with the source of
		// randomness, not with this licence; a suffix nothing else can hold ends it.
		return $proposed . '-' . strtoupper( substr( md5( (string) wp_generate_uuid4() ), 0, 6 ) );
	}

	/**
	 * Create the licence row.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $data Fluent-Cart-shaped licence data.
	 *
	 * @return ProLicenseModel|null The created licence, or null on failure.
	 */
	private function create_license( array $data ): ?ProLicenseModel {
		$model = self::LICENSE_MODEL;

		try {
			$created = $model::query()->create( $data );

			// Eloquent's create() types as Builder|Model through __callStatic, so this is
			// what narrows it to a licence — and what makes the null return honest.
			return $created instanceof ProLicenseModel ? $created : null;
		} catch ( Exception $e ) {
			return null;
		}
	}

	/**
	 * Record the sites a licence is active on.
	 *
	 * Best effort: a licence with no activation rows is still a valid licence, so a failure
	 * here reduces the reported count rather than failing the run. The count returned is
	 * what was actually written, not what was asked for — reporting the request would make
	 * the summary a wish.
	 *
	 * @since 1.1.0
	 *
	 * @param int                  $license_id The licence just created.
	 * @param array<string, mixed> $data       The licence row, for product and variation ids.
	 * @param array<string, mixed> $entity     The canonical entity, carrying the activations.
	 *
	 * @return int How many activation rows were written.
	 */
	private function create_activations( int $license_id, array $data, array $entity ): int {
		$model = self::ACTIVATION_MODEL;

		if ( ! class_exists( $model ) || empty( $entity['activations'] ) ) {
			return 0;
		}

		$written = 0;

		foreach ( (array) $entity['activations'] as $activation ) {
			if ( ! is_array( $activation ) ) {
				continue;
			}

			try {
				$model::query()->create(
					array(
						// Pro links an activation to a site row; with no site registry to
						// draw from, the hash is what identifies the install, which is also
						// how Pro's key-based activations work.
						'site_id'             => 0,
						'license_id'          => $license_id,
						'status'              => 'active',
						'is_local'            => empty( $activation['is_local'] ) ? 0 : 1,
						'product_id'          => $data['product_id'],
						'variation_id'        => $data['variation_id'],
						'activation_method'   => 'key_based',
						'activation_hash'     => md5( (string) ( $activation['site_url'] ?? wp_generate_uuid4() ) ),
						'last_update_version' => (string) ( $activation['version'] ?? '' ),
						'last_update_date'    => $activation['created_at'] ?? current_time( 'Y-m-d H:i:s' ),
					)
				);

				++$written;
			} catch ( Exception $e ) {
				continue;
			}
		}

		return $written;
	}
}
