<?php
/**
 * MCP Ability: Generate Tax Classes
 *
 * @package StoreSeeder\MCP\Abilities
 * @since   2.1.0
 */

namespace StoreSeeder\MCP\Abilities;

use StoreSeeder\MCP\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Generate_Tax_Classes
 *
 * Maps to REST endpoint: POST /storeseeder/v1/tax_classes/generate
 *
 * @since 1.0.0
 */
class Generate_Tax_Classes extends Ability {

	const REST_BASE = 'tax_classes';

	/**
	 * {@inheritdoc}
	 */
	public static function label(): string {
		return __( 'Generate Tax Classes', 'storeseeder' );
	}

	/**
	 * {@inheritdoc}
	 */
	public static function description(): string {
		return __( 'Generate tax classes with country/state/city-level rate tables, compound tax configurations, and priority settings for a Fluent Cart store.', 'storeseeder' );
	}

	/**
	 * {@inheritdoc}
	 */
	protected static function input_properties(): array {
		return array(
			'tax_types'        => array(
				'type'        => 'array',
				'description' => __( 'Tax class types to generate. Allowed: standard, reduced, zero, exempt, digital. Default: ["standard","reduced","zero"].', 'storeseeder' ),
				'items'       => array( 'type' => 'string' ),
				'default'     => array( 'standard', 'reduced', 'zero' ),
			),
			'countries'        => array(
				'type'        => 'array',
				'description' => __( 'ISO-2 country codes to generate tax rates for. Default: ["US","CA","GB","AU","DE"].', 'storeseeder' ),
				'items'       => array( 'type' => 'string' ),
				'default'     => array( 'US', 'CA', 'GB', 'AU', 'DE' ),
			),
			'include_compound' => array(
				'type'        => 'boolean',
				'description' => __( 'Allow compound rates, which stack on top of the ones before them — a state tax over a federal one. Default: true.', 'storeseeder' ),
				'default'     => true,
			),
			'jurisdictions'    => array(
				'type'        => 'array',
				'description' => __( 'How precise a rate row is. Allowed: country, state, city, postcode. A more precise row outranks a broader one. Default: ["country","state"].', 'storeseeder' ),
				'items'       => array(
					'type' => 'string',
					'enum' => array( 'country', 'state', 'city', 'postcode' ),
				),
				'default'     => array( 'country', 'state' ),
			),
			'rate_ranges'      => array(
				'type'        => 'object',
				'description' => __( 'Percentage bands per class type. A zero-rated or exempt class is always zero, whatever is given here.', 'storeseeder' ),
				'properties'  => array(
					'standard' => array(
						'type'       => 'object',
						'properties' => array(
							'min' => array( 'type' => 'number' ),
							'max' => array( 'type' => 'number' ),
						),
					),
					'reduced'  => array(
						'type'       => 'object',
						'properties' => array(
							'min' => array( 'type' => 'number' ),
							'max' => array( 'type' => 'number' ),
						),
					),
					'digital'  => array(
						'type'       => 'object',
						'properties' => array(
							'min' => array( 'type' => 'number' ),
							'max' => array( 'type' => 'number' ),
						),
					),
				),
			),
		);
	}

	/**
	 * {@inheritdoc}
	 */
	protected static function output(): array {
		return array(
			'key'         => 'tax_classes',
			'description' => __( 'Array of generated tax class objects with id, name, active status, rates array, and covered regions.', 'storeseeder' ),
		);
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param array<string, mixed> $input Validated input from the MCP client.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function execute( array $input = array() ) {
		return static::dispatch( static::build_payload( $input ) );
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param array<string, mixed> $input Raw MCP input.
	 * @return array<string, mixed>
	 */
	protected static function build_payload( array $input ): array {
		$payload = array(
			'count'  => $input['count'] ?? 5,
			'locale' => $input['locale'] ?? 'en_US',
		);

		if ( isset( $input['seed'] ) ) {
			$payload['seed'] = (int) $input['seed'];
		}

		foreach ( array( 'tax_types', 'jurisdictions', 'rate_ranges' ) as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$payload[ $key ] = (array) $input[ $key ];
			}
		}

		$payload['location_coverage'] = array(
			'countries'        => $input['countries'] ?? array( 'US', 'CA', 'GB', 'AU', 'DE' ),
			'include_compound' => $input['include_compound'] ?? true,
		);

		return $payload;
	}
}
