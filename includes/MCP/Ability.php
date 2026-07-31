<?php
/**
 * Abstract base class for all StoreSeeder MCP Ability execute callbacks.
 *
 * Each concrete Ability class maps one ability ID to one StoreSeeder REST
 * endpoint. The shared generate() method handles building the payload,
 * dispatching a WP_REST_Request internally (no HTTP round-trip), and
 * returning the response array to the Abilities API.
 *
 * Using WP_REST_Request internally keeps authentication trivial — the
 * current user is already verified by the permission_callback before
 * execute() is ever called.
 *
 * @package StoreSeeder\MCP
 * @since   2.1.0
 */

namespace StoreSeeder\MCP;

defined( 'ABSPATH' ) || exit;

use WP_Error;
use WP_REST_Request;

/**
 * Abstract_Ability
 *
 * @since 1.0.0
 */
abstract class Ability {

	/**
	 * The REST route base (e.g. "products", "customers", "orders").
	 * Concrete classes must define this constant.
	 *
	 * @since 1.0.0
	 */
	const REST_BASE = '';

	/**
	 * REST namespace shared by all StoreSeeder endpoints.
	 *
	 * @since 1.0.0
	 */
	const REST_NAMESPACE = 'storeseeder/v1';

	/**
	 * Ability category every StoreSeeder ability belongs to.
	 *
	 * @since 1.1.0
	 */
	const CATEGORY = 'storeseeder';

	/**
	 * The ability id this class registers as.
	 *
	 * Derived from REST_BASE rather than declared, so an ability's id and the endpoint
	 * it dispatches to cannot disagree — which would otherwise be a silent bug, the
	 * ability appearing to work while pointing at the wrong generator. Verified to
	 * reproduce all seventeen shipped ids exactly. Override if a third-party ability
	 * needs a different name.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public static function ability_id(): string {
		return 'storeseeder/generate-' . str_replace( '_', '-', static::REST_BASE );
	}

	/**
	 * Human-readable name shown to MCP clients.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	abstract public static function label(): string;

	/**
	 * What this ability does, written for an AI client deciding whether to call it.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	abstract public static function description(): string;

	/**
	 * Resource-specific input properties, merged over the common ones.
	 *
	 * Lives beside build_payload() on purpose: the schema declares the flat input an
	 * MCP client sends, and build_payload() re-nests it for the REST endpoint. Kept in
	 * separate files, those two drift.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	protected static function input_properties(): array {
		return array();
	}

	/**
	 * The response envelope this ability returns.
	 *
	 * @since 1.1.0
	 *
	 * @return array{key: string, description: string}
	 */
	abstract protected static function output(): array;

	/**
	 * The full ability definition, as the Abilities API expects it.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, mixed>
	 */
	public static function definition(): array {
		$output = static::output();

		return array(
			'label'               => static::label(),
			'description'         => static::description(),
			'category'            => self::CATEGORY,
			'input_schema'        => static::input_schema(),
			'output_schema'       => static::output_schema( $output['key'], $output['description'] ),
			'execute_callback'    => array( static::class, 'execute' ),
			'permission_callback' => array( self::class, 'permission_callback' ),
		);
	}

	/**
	 * Input schema: the common count/locale/seed parameters plus this ability's own.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, mixed>
	 */
	protected static function input_schema(): array {
		$common = array(
			'count'  => array(
				'type'        => 'integer',
				'description' => __( 'Number of items to generate (1–100). Required.', 'storeseeder' ),
				'minimum'     => 1,
				'maximum'     => 100,
			),
			'locale' => array(
				'type'        => 'string',
				'description' => __( 'Faker locale for generated data (e.g. en_US, fr_FR, de_DE, ja_JP). Affects names, addresses, and phone numbers. Default: en_US.', 'storeseeder' ),
				'default'     => 'en_US',
			),
			'seed'   => array(
				'type'        => 'integer',
				'description' => __( 'Optional integer seed for reproducible data generation. Omit for random output.', 'storeseeder' ),
				'minimum'     => 1,
			),
		);

		return array(
			'type'       => 'object',
			'required'   => array( 'count' ),
			'properties' => array_merge( $common, static::input_properties() ),
		);
	}

	/**
	 * Output schema for the generated-items envelope.
	 *
	 * @since 1.1.0
	 *
	 * @param string $resource_key      Key in the response object holding the items array.
	 * @param string $items_description Human-readable description of the items.
	 *
	 * @return array<string, mixed>
	 */
	protected static function output_schema( string $resource_key, string $items_description ): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'message'     => array(
					'type'        => 'string',
					'description' => __( 'Human-readable summary of the generation result.', 'storeseeder' ),
				),
				$resource_key => array(
					'type'        => 'array',
					'description' => $items_description,
					'items'       => array( 'type' => 'object' ),
				),
			),
		);
	}

	/**
	 * Shared permission callback for every StoreSeeder ability.
	 *
	 * Mirrors the REST layer: only manage_options may generate data. Abilities
	 * dispatch through the REST API, which checks this again, so this is the outer of
	 * two gates rather than the only one.
	 *
	 * @since 1.1.0
	 *
	 * @return bool
	 */
	public static function permission_callback(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Entry point called by the Abilities API.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $input Validated input from the MCP client.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function execute( array $input = array() ) {
		return static::dispatch( $input );
	}

	/**
	 * Dispatch an internal REST request and return the decoded response body.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $params Parameters to forward as JSON body.
	 * @return array<string, mixed>|WP_Error
	 */
	protected static function dispatch( array $params ) {
		$route = '/' . static::REST_NAMESPACE . '/' . static::REST_BASE . '/generate';

		$request = new WP_REST_Request( 'POST', $route );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( (string) wp_json_encode( $params ) );

		$response = rest_do_request( $request );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$server = rest_get_server();
		$data   = $server->response_to_data( $response, false );

		if ( $response->is_error() ) {
			$status  = $response->get_status();
			$message = isset( $data['message'] ) ? (string) $data['message'] : __( 'Unknown REST error.', 'storeseeder' ); // @phpstan-ignore isset.offset
			return new WP_Error( 'ecfp_rest_error', $message, array( 'status' => $status ) );
		}

		return $data;
	}

	/**
	 * Normalise the raw ability input, extracting the resource-specific
	 * parameters into the nested arrays the REST controller expects.
	 *
	 * Concrete classes override this when their endpoint expects nested params.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $input Raw MCP input.
	 * @return array<string, mixed> Payload ready for the REST endpoint.
	 */
	protected static function build_payload( array $input ): array {
		return $input;
	}
}
