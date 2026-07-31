<?php
/**
 * WooCommerce shipping plan writer
 *
 * @since   1.1.0
 * @package StoreSeeder\Platforms\Drivers\Woo_Commerce\Writers
 */

namespace StoreSeeder\Platforms\Drivers\Woo_Commerce\Writers;

use StoreSeeder\Platforms\Drivers\Woo_Commerce\Writer;
use StoreSeeder\Platforms\Resource;
use WC_Shipping_Zone;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Persists a canonical shipping plan into WooCommerce as a zone and a method.
 *
 * WooCommerce has no "shipping plan": it has zones, and methods inside them. A canonical plan
 * is therefore one zone with one method, which is what the entity actually describes — a
 * title, a type and an amount.
 *
 * The method's cost is a *setting*, not a column: WooCommerce stores it in
 * `woocommerce_flat_rate_{instance_id}_settings`, and a method saved without touching that
 * option charges nothing. This writer sets it, which is the difference between a shipping
 * method that appears at checkout and one that appears to be free.
 *
 * @since 1.1.0
 */
final class Shipping_Plan extends Writer {
	/**
	 * Canonical plan type to the WooCommerce method id.
	 *
	 * @since 1.1.0
	 * @var array<string, string>
	 */
	private const METHOD = array(
		'flat_rate'     => 'flat_rate',
		'free_shipping' => 'free_shipping',
	);

	/**
	 * The resource this writer persists.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public function resource(): string {
		return Resource::SHIPPING_PLAN;
	}

	/**
	 * Create a WooCommerce shipping zone with one method in it.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $entity Canonical shipping-plan entity.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function write( array $entity ) {
		if ( ! class_exists( 'WC_Shipping_Zone' ) ) {
			return new WP_Error(
				'missing_woocommerce',
				__( 'WooCommerce is not active on this site.', 'storeseeder' )
			);
		}

		$type   = (string) $entity['type'];
		$method = self::METHOD[ $type ] ?? 'flat_rate';
		$amount = $this->to_decimal( (int) $entity['amount'] );

		$zone = new WC_Shipping_Zone();
		$zone->set_zone_name( (string) $entity['title'] );

		// Regions arrive empty from the generator — a plan with no region is a valid
		// WooCommerce zone, so one is drawn here rather than left blank, which would make
		// every generated zone match nothing and never appear at checkout.
		$regions = (array) $entity['regions'];

		if ( array() === $regions ) {
			$regions = array( strtoupper( $this->faker()->randomElement( array( 'US', 'GB', 'DE', 'FR', 'CA', 'AU' ) ) ) );
		}

		foreach ( $regions as $region ) {
			$zone->add_location( (string) $region, 'country' );
		}

		$zone_id = $zone->save();

		if ( ! $zone_id ) {
			return new WP_Error( 'shipping_zone_failed', __( 'Failed to create the shipping zone.', 'storeseeder' ) );
		}

		$instance_id = $zone->add_shipping_method( $method );

		if ( ! $instance_id ) {
			return new WP_Error( 'shipping_method_failed', __( 'Failed to add the shipping method.', 'storeseeder' ) );
		}

		$this->configure_method( $method, (int) $instance_id, $amount, (bool) $entity['enabled'] );

		$data = array(
			'zone_id'     => (int) $zone_id,
			'instance_id' => (int) $instance_id,
			'method'      => $method,
			'amount'      => $amount,
		);

		$result = array(
			// The zone is the thing to remove later: deleting it takes its methods with it,
			// while deleting a method would leave an empty zone behind.
			'id'         => (int) $zone_id,
			'title'      => $zone->get_zone_name(),
			'type'       => $method,
			'amount'     => 'free_shipping' === $method ? __( 'free', 'storeseeder' ) : $amount,
			'regions'    => implode( ', ', array_map( 'strval', $regions ) ),
			'enabled'    => (bool) $entity['enabled'],
			'created_at' => current_time( 'Y-m-d H:i:s' ),
		);

		return $this->filter_result( $result, (int) $zone_id, $data );
	}

	/**
	 * Remove a generated shipping zone.
	 *
	 * @since 1.1.0
	 *
	 * @param int|string $id Zone id.
	 *
	 * @return true|WP_Error
	 */
	public function delete( $id ) {
		if ( ! class_exists( 'WC_Shipping_Zone' ) ) {
			return new WP_Error(
				'storeseeder_delete_unsupported',
				__( 'WooCommerce is not active on this site.', 'storeseeder' )
			);
		}

		try {
			$zone = new WC_Shipping_Zone( (int) $id );

			if ( ! $zone->get_id() ) {
				return true;
			}

			$zone->delete( true );
		} catch ( \Exception $e ) {
			return new WP_Error( 'storeseeder_delete_failed', $e->getMessage() );
		}

		return true;
	}

	/**
	 * Write the method's own settings option.
	 *
	 * The instance row carries only an id and an order; everything a shopper sees — the cost,
	 * the title, whether it is enabled — lives in this option.
	 *
	 * @since 1.1.0
	 *
	 * @param string $method      WooCommerce method id.
	 * @param int    $instance_id The instance just created.
	 * @param string $amount      Decimal cost.
	 * @param bool   $enabled     Whether the method is offered.
	 *
	 * @return void
	 */
	private function configure_method( string $method, int $instance_id, string $amount, bool $enabled ): void {
		$settings = array(
			'title'      => 'free_shipping' === $method
				? __( 'Free shipping', 'storeseeder' )
				: __( 'Flat rate', 'storeseeder' ),
			'tax_status' => 'taxable',
			'enabled'    => $enabled ? 'yes' : 'no',
		);

		if ( 'free_shipping' === $method ) {
			// Without a requirement, free shipping is offered to everyone — which is the
			// simplest thing to test against and the WooCommerce default.
			$settings['requires'] = '';
		} else {
			$settings['cost'] = $amount;
		}

		update_option( 'woocommerce_' . $method . '_' . $instance_id . '_settings', $settings );
	}
}
