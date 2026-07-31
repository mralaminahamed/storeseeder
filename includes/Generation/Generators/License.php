<?php
/**
 * License Generator Class for StoreSeeder Plugin
 *
 * @since      1.1.0
 * @subpackage Generators
 * @package    StoreSeeder\Generation\Generators
 */

namespace StoreSeeder\Generation\Generators;

use StoreSeeder\Generation\Generator;
use StoreSeeder\Platforms\Status;

defined( 'ABSPATH' ) || exit;

/**
 * License Generator Class
 *
 * Shapes software licence records: a key, how many sites it permits, how many it is already
 * on, and when it lapses. The order, customer and product it belongs to are existing rows
 * the writer draws — a licence with no purchase behind it is not a case worth testing.
 *
 * Which platforms can store one is a capability question, not a generator one. Fluent Cart
 * creates `fct_licenses` from Pro's migrator, so the driver reports the licence resource as
 * needing Fluent Cart Pro and names it; this class knows nothing about that.
 *
 * @since 1.1.0
 */
class License extends Generator {

	/**
	 * How many sites a licence permits.
	 *
	 * Zero means unlimited, which is a real tier and the one most likely to be mishandled
	 * by code that treats the limit as a plain number — so it is in the set deliberately.
	 *
	 * @var int[]
	 */
	private const SITE_LIMITS = array( 0, 1, 3, 5, 10, 25 );

	/**
	 * Get the resource type name
	 *
	 * @return string Resource type name.
	 */
	protected function get_resource_type(): string {
		return 'license';
	}

	/**
	 * Get supported data types for this generator.
	 *
	 * @return array Supported types
	 */
	public function get_supported_types(): array {
		return array(
			'licenses' => __( 'Licenses', 'storeseeder' ),
		);
	}

	/**
	 * Get generator description.
	 *
	 * @return string Description
	 */
	public function get_description(): string {
		return 'Generates software licences against existing orders — keys, site limits, activation counts and expiry dates.';
	}

	/**
	 * Build a canonical licence
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, mixed>
	 */
	protected function build_entity() {
		$status = $this->get_faker()->randomElement( Status::license_statuses() );
		$limit  = $this->get_faker()->randomElement( self::SITE_LIMITS );

		// An activation count above the limit is not a licence anyone would issue, and code
		// under test would be right to reject it — so the count respects the limit, with
		// unlimited (0) treated as generous rather than as zero allowed.
		$ceiling          = 0 === $limit ? 12 : $limit;
		$activation_count = Status::EXPIRED === $status
			? 0
			: $this->get_faker()->numberBetween( 0, $ceiling );

		return array(
			'license_key'      => $this->license_key(),
			'status'           => $status,
			'site_limit'       => $limit,
			'activation_count' => $activation_count,
			// Expired licences lapsed in the past; the rest have a year left, which is what
			// an annual product looks like. A disabled licence keeps its date: it was
			// withdrawn, not aged out, and the two are different states to test.
			'expires_at'       => Status::EXPIRED === $status
				? gmdate( 'Y-m-d H:i:s', time() - $this->get_faker()->numberBetween( 1, 400 ) * DAY_IN_SECONDS )
				: gmdate( 'Y-m-d H:i:s', time() + $this->get_faker()->numberBetween( 30, 400 ) * DAY_IN_SECONDS ),
			'activations'      => $this->activations( $activation_count ),
		);
	}

	/**
	 * A licence key in the shape people expect to paste.
	 *
	 * Grouped hex, uppercase: long enough to look real in a UI and to exercise a column
	 * width, and unambiguous when read aloud from a support ticket.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	private function license_key(): string {
		$groups = array();

		for ( $i = 0; $i < 4; $i++ ) {
			$groups[] = strtoupper( $this->get_faker()->bothify( '????####' ) );
		}

		return implode( '-', $groups );
	}

	/**
	 * The sites a licence is currently active on.
	 *
	 * Domains rather than ids: the writer owns the site rows and their keys, and a
	 * generator that invented ids would be guessing at someone else's table.
	 *
	 * @since 1.1.0
	 *
	 * @param int $count How many activations to describe.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function activations( int $count ): array {
		$activations = array();

		for ( $i = 0; $i < $count; $i++ ) {
			$activations[] = array(
				'site_url'   => 'https://' . $this->get_faker()->domainName(),
				'is_local'   => $this->get_faker()->boolean( 20 ),
				'version'    => sprintf(
					'%d.%d.%d',
					$this->get_faker()->numberBetween( 1, 4 ),
					$this->get_faker()->numberBetween( 0, 9 ),
					$this->get_faker()->numberBetween( 0, 9 )
				),
				'created_at' => gmdate( 'Y-m-d H:i:s', time() - $this->get_faker()->numberBetween( 1, 300 ) * DAY_IN_SECONDS ),
			);
		}

		return $activations;
	}
}
