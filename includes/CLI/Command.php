<?php
/**
 * Base class for StoreSeeder's WP-CLI commands
 *
 * Every command dispatches through the REST API rather than calling generators directly.
 * That is the whole design: the controllers own parameter validation, platform resolution
 * and the capability check, so a CLI run and an HTTP request cannot disagree about what is
 * valid or what a parameter means. A second code path would drift the first time a
 * generator gained a parameter.
 *
 * The pieces that decide *what* to send are static and free of WP_CLI, so they can be
 * tested without the CLI runtime present — see tests/php/src/CLI/.
 *
 * @since   1.1.0
 * @package StoreSeeder\CLI
 */

namespace StoreSeeder\CLI;

use StoreSeeder\Access;
use StoreSeeder\Rest\Registry as Rest_Registry;
use WP_Error;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * Shared behaviour for the CLI commands.
 *
 * @since 1.1.0
 */
abstract class Command {
	/**
	 * The command as typed, minus the `wp storeseeder ` prefix.
	 *
	 * @since 1.1.0
	 */
	const NAME = '';

	/**
	 * One-line description, shown by `wp help storeseeder`.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	abstract public static function shortdesc(): string;

	/**
	 * The command's parameters, in WP-CLI's synopsis format.
	 *
	 * @since 1.1.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function synopsis(): array {
		return array();
	}

	/**
	 * Run the command.
	 *
	 * @since 1.1.0
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Flags.
	 *
	 * @return void
	 */
	abstract public function __invoke( array $args, array $assoc_args );

	/**
	 * Every accepted way of naming a resource, mapped to its REST base.
	 *
	 * Both spellings are accepted because both are real: the REST base is `cart-sessions`
	 * while the canonical resource is `cart_session`, and no singularisation rule survives
	 * `shipping_classes`. Making someone remember which one the CLI wants would be a
	 * pointless quiz.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, string> Alias => REST base.
	 */
	public static function resource_aliases(): array {
		$aliases = array();

		// The registry hands back instances, keyed by base.
		foreach ( Rest_Registry::instance()->all() as $rest_base => $controller ) {
			$aliases[ $rest_base ]                   = $rest_base;
			$aliases[ $controller->resource_type() ] = $rest_base;
			// `cart_sessions` and `cart-sessions` are the same thing to a person.
			$aliases[ str_replace( '-', '_', $rest_base ) ] = $rest_base;
		}

		return $aliases;
	}

	/**
	 * Resolve whatever the user typed to a REST base.
	 *
	 * @since 1.1.0
	 *
	 * @param string $requested Resource or REST base, as typed.
	 *
	 * @return string|WP_Error The REST base, or an error naming what is valid.
	 */
	public static function resolve_resource( string $requested ) {
		$aliases = self::resource_aliases();
		$key     = strtolower( trim( $requested ) );

		if ( isset( $aliases[ $key ] ) ) {
			return $aliases[ $key ];
		}

		$bases = array_values( array_unique( array_values( $aliases ) ) );
		sort( $bases );

		return new WP_Error(
			'storeseeder_unknown_resource',
			sprintf(
				/* translators: 1: resource as typed, 2: comma-separated list of valid resources. */
				__( 'Unknown resource "%1$s". Available: %2$s', 'storeseeder' ),
				$requested,
				implode( ', ', $bases )
			)
		);
	}

	/**
	 * The parameters one endpoint accepts.
	 *
	 * Read from the controller rather than restated here, so a generator that gains a
	 * parameter gains it on the command line at the same moment.
	 *
	 * @since 1.1.0
	 *
	 * @param string $rest_base The endpoint.
	 *
	 * @return array<string, mixed>
	 */
	public static function endpoint_params( string $rest_base ): array {
		$controller = Rest_Registry::instance()->get( $rest_base );

		return null === $controller ? array() : $controller->get_generation_params();
	}

	/**
	 * Turn CLI flags into a REST request body, rejecting anything the endpoint does not know.
	 *
	 * A misspelled flag would otherwise be dropped in silence — `--lokale=de_DE` would
	 * generate a hundred rows in English and report success.
	 *
	 * @since 1.1.0
	 *
	 * @param string                $rest_base  The endpoint.
	 * @param array<string, string> $assoc_args Flags as WP-CLI parsed them.
	 *
	 * @return array<string, mixed>|WP_Error The request body, or an error naming the flag.
	 */
	public static function build_payload( string $rest_base, array $assoc_args ) {
		$schema  = self::endpoint_params( $rest_base );
		$payload = array();

		foreach ( $assoc_args as $key => $value ) {
			// WP-CLI's own flags are not endpoint parameters.
			if ( in_array( $key, array( 'format', 'porcelain', 'user', 'quiet', 'debug' ), true ) ) {
				continue;
			}

			if ( ! isset( $schema[ $key ] ) ) {
				$known = array_keys( $schema );
				sort( $known );

				return new WP_Error(
					'storeseeder_unknown_parameter',
					sprintf(
						/* translators: 1: flag name, 2: endpoint, 3: comma-separated list of accepted parameters. */
						__( '"%1$s" is not a parameter of %2$s. Accepted: %3$s', 'storeseeder' ),
						$key,
						$rest_base,
						implode( ', ', $known )
					)
				);
			}

			$payload[ $key ] = self::coerce( $value, $schema[ $key ] );
		}

		return $payload;
	}

	/**
	 * Give a flag the type its schema declares.
	 *
	 * Everything arrives from the shell as a string, and the REST validator rejects the
	 * string "5" where an integer is required — so the coercion has to happen here rather
	 * than being reported as a user error.
	 *
	 * @since 1.1.0
	 *
	 * @param mixed                $value  The flag's value.
	 * @param array<string, mixed> $config That parameter's schema.
	 *
	 * @return mixed
	 */
	private static function coerce( $value, array $config ) {
		$type = isset( $config['type'] ) ? $config['type'] : 'string';

		if ( 'integer' === $type || 'number' === $type ) {
			return is_numeric( $value ) ? $value + 0 : $value;
		}

		if ( 'boolean' === $type ) {
			// WP-CLI turns `--flag` into true already; a `--flag=false` arrives as a string.
			return filter_var( $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE ) ?? $value;
		}

		if ( 'array' === $type && is_string( $value ) ) {
			// `--payment_methods=stripe,paypal` is how a list is written on a command line.
			return array_map( 'trim', explode( ',', $value ) );
		}

		if ( 'object' === $type && is_string( $value ) ) {
			$decoded = json_decode( $value, true );

			return is_array( $decoded ) ? $decoded : $value;
		}

		return $value;
	}

	/**
	 * Dispatch a request through the REST server, in-process.
	 *
	 * @since 1.1.0
	 *
	 * @param string               $method HTTP method.
	 * @param string               $route  Route, relative to the plugin's namespace.
	 * @param array<string, mixed> $body   Request body.
	 *
	 * @return array<string, mixed>|WP_Error The response data, or the error it returned.
	 */
	protected function dispatch( string $method, string $route, array $body = array() ) {
		$request = new WP_REST_Request( $method, '/storeseeder/v1' . $route );

		foreach ( $body as $key => $value ) {
			$request->set_param( $key, $value );
		}

		$response = rest_do_request( $request );

		if ( $response->is_error() ) {
			return $response->as_error();
		}

		return (array) $response->get_data();
	}

	/**
	 * Stop unless the current user may generate data.
	 *
	 * WP-CLI runs with no user by default, and StoreSeeder writes rows into a live store,
	 * so the command asks for a user rather than assuming that shell access means consent
	 * to write to this particular site's tables. It also keeps one answer to "who may
	 * generate?" across the admin, REST, MCP and the command line.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	protected function require_access(): void {
		if ( Access::current_user_can() ) {
			return;
		}

		\WP_CLI::error(
			sprintf(
				/* translators: %s: capability name, e.g. manage_options. */
				__( 'No user context, so the "%s" capability cannot be verified. Re-run with --user=<id|login|email> for a user who has it.', 'storeseeder' ),
				Access::capability()
			)
		);
	}
}
