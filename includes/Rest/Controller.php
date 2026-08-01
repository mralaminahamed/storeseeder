<?php
/**
 * Abstract REST Controller Class for StoreSeeder
 *
 * Base class for all REST API controllers providing common functionality for
 * data generation endpoints. Extends WordPress REST Controller with additional
 * features for parameter validation, schema generation, and generator integration.
 *
 * @package StoreSeeder\Rest
 * @since   1.0.0
 */

namespace StoreSeeder\Rest;

use StoreSeeder\Access;
use StoreSeeder\Generation\Generator;
use StoreSeeder\Generation\Ledger;
use StoreSeeder\Platforms\Capability;
use StoreSeeder\Platforms\Locale;
use StoreSeeder\Platforms\Platform_Driver;
use StoreSeeder\Platforms\Platform_Interface;
use StoreSeeder\Platforms\Registry as Platform_Registry;
use StoreSeeder\Platforms\Resolver;
use StoreSeeder\Platforms\Resource;
use StoreSeeder\Recipes\Registry as Recipe_Registry;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Abstract REST Controller Class
 *
 * Provides the foundation for all REST API controllers in StoreSeeder.
 * Extends WordPress REST Controller with specialized functionality for data
 * generation endpoints, including parameter validation, schema generation,
 * and seamless integration with generator classes.
 *
 * Key Features:
 * - WordPress REST API integration
 * - Automatic parameter validation and sanitization
 * - JSON Schema generation for API documentation
 * - Generator class integration
 * - WordPress action/filter hooks
 * - Comprehensive error handling
 *
 * @since 1.0.0
 */
abstract class Controller extends WP_REST_Controller {


	/**
	 * REST API namespace
	 *
	 * Defines the namespace for all REST API endpoints in the plugin.
	 * Follows WordPress REST API conventions for versioning and organization.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	protected $namespace = 'storeseeder/v1';

	/**
	 * Get REST base for the endpoint
	 *
	 * Abstract method that must be implemented by all concrete controller classes.
	 * Defines the base path segment for the REST API endpoint (e.g., 'products',
	 * 'customers', 'orders'). This forms part of the complete endpoint URL.
	 *
	 * @since 1.0.0
	 *
	 * @return string REST base path segment (lowercase, plural form).
	 */
	abstract protected function get_rest_base(): string;

	/**
	 * Get generator instance
	 *
	 * Abstract method that must be implemented by all concrete controller classes.
	 * Returns a properly configured generator instance for the specific resource type.
	 * The generator handles the actual data creation logic for the endpoint.
	 *
	 * @since 1.0.0
	 *
	 * @return Generator Configured generator instance for the resource type.
	 */
	abstract protected function get_generator_instance(): Generator;

	/**
	 * Get resource type name
	 *
	 * Abstract method that must be implemented by all concrete controller classes.
	 * Returns the singular form of the resource type (e.g., 'product', 'customer').
	 * Used for API responses, logging, and WordPress action/filter naming.
	 *
	 * @since 1.0.0
	 *
	 * @return string Resource type identifier (singular, lowercase).
	 */
	abstract protected function get_resource_type(): string;

	/**
	 * Get human-readable label for the resource type
	 *
	 * Abstract method that must be implemented by all concrete controller classes.
	 * Returns a human-readable, translated label for the resource type (e.g., 'Product', 'Order').
	 * Used in API documentation, error messages, and user-facing text.
	 *
	 * @since 1.0.0
	 *
	 * @return string Human-readable resource type label (title case, translated).
	 */
	abstract protected function get_resource_type_label(): string;

	/**
	 * The REST base this controller serves
	 *
	 * Public counterpart to get_rest_base(), which is protected so subclasses declare
	 * it without it becoming API. The registry needs to key controllers by base
	 * without reaching into them.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public function rest_base(): string {
		return $this->get_rest_base();
	}

	/**
	 * The canonical resource this controller generates
	 *
	 * Public counterpart to get_resource_type(), for the same reason as rest_base():
	 * subclasses declare it protected so it does not become API, but callers outside the
	 * controller legitimately need the mapping. The CLI accepts either spelling —
	 * `cart-sessions` or `cart_session` — and needs this to relate the two.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public function resource_type(): string {
		return $this->get_resource_type();
	}


	/**
	 * Register REST API routes
	 */
	public function register_routes(): void {
		$rest_base = $this->get_rest_base();

		/**
		 * Filters the REST API parameters for every generation endpoint.
		 *
		 * Runs before the per-endpoint filter, so a parameter added here can still be
		 * removed for one resource. Use it for something that belongs on all seventeen
		 * endpoints — a `dry_run` flag, an extra `meta_options` key — instead of
		 * hooking each base in turn.
		 *
		 * @since 1.1.0
		 * @hook  storeseeder_rest_params
		 *
		 * @param array<string, mixed> $params    Default generation parameters.
		 * @param string               $rest_base The endpoint being registered, e.g. `cart-sessions`.
		 */
		$params = apply_filters( 'storeseeder_rest_params', $this->get_generation_params(), $rest_base );

		/**
		 * Filters the REST API parameters for a specific endpoint.
		 *
		 * Allows developers to modify the parameter schema for specific endpoints,
		 * such as adding custom parameters, changing validation rules, or adding
		 * new options for particular resource types.
		 *
		 * @since 1.0.0
		 * @hook  storeseeder_rest_params_{$rest_base}
		 *
		 * @param array<string, mixed> $params Default generation parameters from get_generation_params().
		 */
		$params = apply_filters( "storeseeder_rest_params_{$rest_base}", $params );

		register_rest_route(
			$this->namespace,
			'/' . $rest_base . '/generate',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'generate_items' ),
					'permission_callback' => array( $this, 'generate_items_permissions_check' ),
					'args'                => $params,
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $rest_base . '/preview',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'preview_items' ),
					'permission_callback' => array( $this, 'generate_items_permissions_check' ),
					'args'                => $params,
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}

	/**
	 * Preview items endpoint callback
	 *
	 * Returns a read-only sample of what generation would produce, built from
	 * FakerPHP and sample data alone — nothing is written to the database. The
	 * response carries 'columns' (table headers) and 'rows' (cells), which is the
	 * shape the admin's live preview table consumes.
	 *
	 * Locale handling mirrors generate_items() so the preview matches the rows a
	 * real run would create.
	 *
	 * @since 1.0.1
	 *
	 * @param WP_REST_Request $request Full data about the REST API request.
	 *
	 * @return WP_REST_Response|WP_Error REST response with preview data or error details.
	 */
	public function preview_items( WP_REST_Request $request ) {
		$generator = $this->get_generator_instance();
		$params    = $this->apply_recipe_params( $request->get_params() );

		$generator->set_locale( $this->resolve_locale( $params, $generator ) );
		$generator->set_faker();
		$generator->set_generation_params( $params );

		$count = isset( $params['count'] ) ? (int) $params['count'] : 10;

		return rest_ensure_response( $this->name_pinned_entities( $generator->preview( $count ), $params ) );
	}

	/**
	 * Fold a recipe's parameters in under the request's own.
	 *
	 * A recipe is a vocabulary, but a vocabulary alone is a half-recipe: grocery product names at
	 * $9.99–$999.99 in Size/Color is not a grocery store. So a manifest also carries ordinary
	 * generation parameters — `price_range`, `variation_types` — and they arrive here.
	 *
	 * Ordinary is the point. There is no new mechanism to honour, no fourth surface to keep in
	 * step: the recipe sets its price band the same way a caller does, through a parameter the
	 * generator already reads.
	 *
	 * @since 1.2.0
	 *
	 * @param array<string, mixed> $params Request parameters.
	 *
	 * @return array<string, mixed> Parameters with the recipe's defaults filled in.
	 */
	protected function apply_recipe_params( array $params ): array {
		$requested = isset( $params['recipe'] ) ? (string) $params['recipe'] : '';

		if ( '' === $requested ) {
			return $params;
		}

		$recipe = Recipe_Registry::instance()->get( $requested );

		// An unknown recipe is not an error. The id also reaches `Generator::sample_data_recipe()`,
		// which discards it the same way, so the run produces default-vocabulary data rather than
		// failing — a stale saved configuration should not become an unusable one.
		if ( null === $recipe ) {
			return $params;
		}

		// Union, not array_merge: the request wins. A recipe proposes a price band; a caller who
		// set one meant it, and silently overriding them would make the parameter they declared
		// stop changing the output — the exact defect the parameter campaign existed to remove.
		return $params + $recipe->params_for( $this->get_resource_type() );
	}

	/**
	 * Show the record a run is pinned to, rather than three invented ones.
	 *
	 * A preview row comes from FakerPHP — it must, because it runs on every keystroke and may not
	 * touch the database. So a run pinned to one customer previewed three different names, which
	 * says the opposite of what the run will do.
	 *
	 * The substitution happens here rather than in the generator for the same reason the pinning
	 * does: naming the record means reading the store, and only the driver knows how. One lookup
	 * per preview request, and only when a pin is actually set.
	 *
	 * A column the generator does not have is left alone, so this is a no-op for every resource
	 * without one.
	 *
	 * @since 1.1.0
	 *
	 * The shape is `Generator::preview()`'s and is documented there; it is loose here because the
	 * substitution writes into a nested cell, and pinning the exact array shape through that only
	 * buys an argument with the analyser.
	 *
	 * @param array<string, mixed> $preview The preview as built.
	 * @param array<string, mixed> $params  Request parameters.
	 *
	 * @return array<string, mixed>
	 */
	protected function name_pinned_entities( array $preview, array $params ): array {
		// Which id parameter fills which preview column.
		$pins = array(
			'customer_id' => array( Resource::CUSTOMER, 'customer' ),
			'product_id'  => array( Resource::PRODUCT, 'product' ),
		);

		foreach ( $pins as $param => $target ) {
			list( $resource_type, $column ) = $target;

			$id = isset( $params[ $param ] ) ? (int) $params[ $param ] : 0;

			if ( $id < 1 ) {
				continue;
			}

			if ( ! $this->preview_has_column( $preview, $column ) ) {
				continue;
			}

			$label = $this->pinned_label( $params, $resource_type, $id );

			if ( null === $label ) {
				continue;
			}

			$rows = array();

			foreach ( (array) $preview['rows'] as $row ) {
				if ( is_array( $row ) && isset( $row[ $column ] ) && is_array( $row[ $column ] ) ) {
					$row[ $column ]['v'] = $label;
				}

				$rows[] = $row;
			}

			$preview['rows'] = $rows;
		}

		return $preview;
	}

	/**
	 * Whether the preview carries a column, so a substitution has somewhere to land.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $preview The preview.
	 * @param string               $column  Column key.
	 *
	 * @return bool
	 */
	private function preview_has_column( array $preview, string $column ): bool {
		foreach ( (array) ( $preview['columns'] ?? array() ) as $definition ) {
			if ( is_array( $definition ) && isset( $definition['key'] ) && $column === $definition['key'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * What the pinned record is called, according to the target platform.
	 *
	 * Null when the platform cannot be resolved, cannot search, or does not know the id — all of
	 * which leave the faker name in place rather than blanking the column.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $params        Request parameters.
	 * @param string               $resource_type Canonical resource name.
	 * @param int                  $id            The pinned id.
	 *
	 * @return string|null
	 */
	private function pinned_label( array $params, string $resource_type, int $id ) {
		$platform = $this->resolve_platform( $params );

		// A preview is not the place to refuse: an unresolved platform already shows as an error on
		// the run itself, and blanking the table on top of that helps nobody.
		if ( is_wp_error( $platform ) || ! $platform instanceof Platform_Driver ) {
			return null;
		}

		foreach ( $platform->search( $resource_type, (string) $id, 5 ) as $result ) {
			if ( (int) $result['id'] === $id ) {
				return $result['label'];
			}
		}

		return null;
	}

	/**
	 * Generate items endpoint callback
	 *
	 * Handles POST requests to the generation endpoint, validates parameters,
	 * configures the generator, and returns the generated data. Includes
	 * comprehensive error handling, locale configuration, and WordPress action hooks
	 * for extensibility. Fires actions before and after generation for monitoring.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full data about the REST API request.
	 *
	 * @return WP_REST_Response|WP_Error REST response with generated data or error details.
	 */
	public function generate_items( WP_REST_Request $request ) {
		$rest_base = $this->get_rest_base();

		$count = $request->get_param( 'count' );

		if ( ! $count || $count <= 0 ) {
			return new WP_Error(
				'invalid_count',
				__( 'Count parameter is required and must be greater than 0.', 'storeseeder' ),
				array( 'status' => 400 )
			);
		}

		// Pass all request parameters to the generator.
		$params    = $this->apply_recipe_params( $request->get_params() );
		$generator = $this->get_generator_instance();

		// Which store the rows land in is a property of the request, not of the
		// generator, so it is resolved here and injected. Resolution can legitimately
		// fail — with more than one platform active there is no safe default, and
		// guessing would write rows into the wrong store silently.
		$platform = $this->resolve_platform( $params );

		if ( is_wp_error( $platform ) ) {
			return $platform;
		}

		$generator->set_platform( $platform );

		$generator->set_locale( $this->resolve_locale( $params, $generator ) );
		$generator->set_faker();
		$generator->set_generation_params( $params );

		// Tag the ledger rows this request writes. Set here rather than passed down because
		// `Generator::write_entity()` is the single place that records, and threading an argument
		// through eighteen writers would be eighteen chances to drop it.
		Ledger::set_run( isset( $params['recipe_run'] ) ? (string) $params['recipe_run'] : '' );

		$result = $generator->generate( (int) $count );

		// Cleared immediately: the static outlives the request under WP-CLI and in tests, and a
		// leaked run id would quietly file unrelated rows under a recipe someone could then undo.
		Ledger::set_run( '' );

		if ( is_wp_error( $result ) ) {
			$generator->log( 'Generation failed: ' . $result->get_error_message(), 'error', $params );
			return $result;
		}

		$total_output = count( $result );
		$errors       = $generator->get_generation_errors();

		// Items may fail individually without aborting the batch. If none survived,
		// the request did not succeed, and answering 200 with "0 items created"
		// hides a reason the generator already took the trouble to explain.
		if ( 0 === $total_output && ! empty( $errors ) ) {
			return new WP_Error(
				'generation_failed',
				$errors[0],
				array(
					'status' => 500,
					'errors' => array_values( array_unique( $errors ) ),
				)
			);
		}

		$failed = count( $errors );

		$message = sprintf(
			// translators: Total output.
			_n( '%1$s item successfully created.', '%1$s items successfully created.', $total_output, 'storeseeder' ),
			$total_output
		);

		if ( $failed > 0 ) {
			$message .= ' ' . sprintf(
				// translators: %s: number of items that could not be created.
				_n( '%s could not be created.', '%s could not be created.', $failed, 'storeseeder' ),
				$failed
			);
		}

		/**
		 * Filters the success message returned by the REST API generation endpoint.
		 *
		 * Allows developers to customize the success message or add additional information
		 * to the API response based on the generated results.
		 *
		 * @since 1.0.0
		 * @hook  storeseeder_rest_message
		 *
		 * @param string $message         The default success message.
		 * @param array  $result          The generation results array.
		 * @param string $resource_type   The type of resource that was generated.
		 */
		$message = apply_filters( 'storeseeder_rest_message', $message, $result, $this->get_resource_type() );

		$response = array(
			'message'                  => $message,
			$this->get_resource_type() => $result,
		);

		// Only present on a partial batch, so existing clients see an unchanged
		// response shape whenever everything succeeded.
		if ( $failed > 0 ) {
			$response['failed'] = $failed;
			$response['errors'] = array_values( array_unique( $errors ) );
		}

		// Likewise only present when something was actually ignored: what the target could not
		// use, whether it belonged to another platform or is a field this one cannot store.
		$ignored = $this->ignored_params( $platform, $request );

		if ( array() !== $ignored ) {
			$response['ignored'] = $ignored;
		}

		/**
		 * Filters the REST API response data.
		 *
		 * Allows developers to modify the response data before it's returned to the client.
		 *
		 * @since 1.0.0
		 * @hook storeseeder_rest_response
		 *
		 * @param array           $response     The REST API response data array.
		 * @param array           $result       The generation results array.
		 * @param string          $resource_type The type of resource that was generated.
		 * @param WP_REST_Request $request      Full data about the original request.
		 */
		$response = apply_filters( 'storeseeder_rest_response', $response, $result, $this->get_resource_type(), $request );

		return new WP_REST_Response( $response, 200 );
	}

	/**
	 * Resolve the target platform and confirm it can generate this resource
	 *
	 * Two failures are possible and they mean different things. An unresolvable target
	 * is a request problem — nothing was chosen and more than one platform is active.
	 * An unsupported resource is a capability problem, and it is answered rather than
	 * ignored: writing nothing and reporting success would leave the caller believing
	 * rows exist.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $params Request parameters.
	 *
	 * @return Platform_Interface|WP_Error
	 */
	protected function resolve_platform( array $params ) {
		$requested = isset( $params['platform'] ) ? (string) $params['platform'] : null;
		$platform  = ( new Resolver() )->resolve( $requested );

		if ( is_wp_error( $platform ) ) {
			return $platform;
		}

		$resource_type = $this->get_resource_type();
		$capabilities  = $platform->supports();
		$capability    = $capabilities[ $resource_type ] ?? null;

		if ( null === $capability || ! $capability->is_supported() ) {
			$reason = null === $capability
				? __( 'Not supported by this platform.', 'storeseeder' )
				: $capability->get_reason();

			return new WP_Error(
				'storeseeder_unsupported_resource',
				sprintf(
					/* translators: 1: resource label, 2: platform display name, 3: reason. */
					__( '%1$s cannot be generated for %2$s. %3$s', 'storeseeder' ),
					$this->get_resource_type_label(),
					$platform->label(),
					$reason
				),
				array(
					'status'    => 400,
					'platform'  => $platform->id(),
					'resource'  => $resource_type,
					'extension' => null !== $capability ? $capability->get_extension() : '',
				)
			);
		}

		// A driver that claims a resource but ships no writer for it is a bug in the
		// driver, and one worth naming plainly: without this check the run proceeds,
		// fails once per requested item, and reports a generic "generation failed"
		// that says nothing about which of the two halves is missing.
		if ( null === $platform->writer( $resource_type ) ) {
			return new WP_Error(
				'storeseeder_missing_writer',
				sprintf(
					/* translators: 1: platform display name, 2: resource name. */
					__( '%1$s reports that it can generate %2$s but provides no writer for it. This is a fault in the platform driver.', 'storeseeder' ),
					$platform->label(),
					$resource_type
				),
				array(
					'status'   => 500,
					'platform' => $platform->id(),
					'resource' => $resource_type,
				)
			);
		}

		return $platform;
	}

	/**
	 * Narrow the requested locale to one that can actually be generated in
	 *
	 * The REST schema already rejects an unknown locale, so in practice this only
	 * fills in the default when none was sent. It still logs a substitution, because
	 * the alternative -- quietly producing English for a locale the caller asked for --
	 * is the bug this replaced.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $params    Request parameters.
	 * @param Generator            $generator Generator, used for logging.
	 *
	 * @return string A supported locale code.
	 */
	protected function resolve_locale( array $params, Generator $generator ): string {
		$requested = isset( $params['locale'] ) ? (string) $params['locale'] : Locale::DEFAULT_LOCALE;

		if ( Locale::is_supported( $requested ) ) {
			return $requested;
		}

		$resolved = Locale::resolve( $requested );

		$generator->log(
			"Unsupported locale '{$requested}' requested; generating in '{$resolved}' instead.",
			'warning'
		);

		return $resolved;
	}

	/**
	 * Check if user has permission to generate items
	 *
	 * @param  WP_REST_Request $request Full data about the request.
	 * @return bool|WP_Error
	 */
	public function generate_items_permissions_check( $request ) {
		// Access::current_user_can() rather than a literal capability, so the admin
		// menu, the MCP abilities and these routes cannot be granted separately.
		if ( ! Access::current_user_can() ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Sorry, you are not allowed to generate test data.', 'storeseeder' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Get generation endpoint parameters
	 *
	 * Returns the complete parameter schema for the generation endpoint,
	 * including base parameters (count, locale, seed) and resource-specific
	 * parameters defined by child classes. Used for parameter validation,
	 * API documentation, and request processing.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>> Parameter schema definitions.
	 */
	public function get_generation_params(): array {
		$base_params = array(
			'count'         => array(
				'description'       => __( 'Number of items to generate.', 'storeseeder' ),
				'type'              => 'integer',
				'minimum'           => 1,
				'maximum'           => 100,
				'required'          => true,
				'sanitize_callback' => 'absint',
				'validate_callback' => array( $this, 'validate_count' ),
			),
			'locale'        => array(
				// Enumerated from Locale, not a fixed list. A hardcoded six here was
				// what made the admin's locale picker a lie: it offered seventy-three
				// and the API accepted six, silently generating English for the rest.
				'description'       => __( 'Locale for generated data (e.g., en_US, fr_FR, ja_JP).', 'storeseeder' ),
				'type'              => 'string',
				'default'           => Locale::DEFAULT_LOCALE,
				'enum'              => Locale::codes(),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'seed'          => array(
				'description'       => __( 'Random seed for reproducible data generation.', 'storeseeder' ),
				'type'              => 'integer',
				'minimum'           => 1,
				'sanitize_callback' => 'absint',
			),
			'recipe'        => array(
				// Not enumerated, for the same reason `platform` is not: the registry is
				// filterable, so an enum here would reject a valid third-party recipe. An
				// unregistered id is ignored rather than rejected — a recipe is a preference
				// for a vocabulary, and a run that cannot find it should still produce data.
				'description'       => __( 'Store recipe whose vocabulary this run should use, e.g. grocery. Falls back to the default vocabulary when unknown.', 'storeseeder' ),
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_key',
			),
			'recipe_run'    => array(
				// Stamped on every ledger row the run writes, so undoing a whole recipe is one
				// action rather than one purge per resource. Opaque to the server: the client
				// mints it, because a recipe is many requests and only the client knows they
				// belong together.
				'description'       => __( 'Groups the rows written by one recipe run so they can be removed together.', 'storeseeder' ),
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_key',
			),
			'platform'      => array(
				// Not enumerated: the set of drivers is extensible through the
				// storeseeder_platforms filter, so an enum here would reject a valid
				// third-party platform. Resolver validates it instead, and says which
				// ones exist when it rejects.
				'description'       => __( 'Target e-commerce platform to seed. Defaults to the site setting, or the only active platform.', 'storeseeder' ),
				'type'              => 'string',
				'default'           => Resolver::AUTO,
				'sanitize_callback' => 'sanitize_key',
			),
			'status'        => array(
				'description'       => __( 'Status filter for generated items.', 'storeseeder' ),
				'type'              => 'string',
				'enum'              => array( 'active', 'inactive', 'draft', 'pending', 'completed', 'cancelled' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'date_range'    => array(
				'description' => __( 'Date range for generated items.', 'storeseeder' ),
				'type'        => 'object',
				'properties'  => array(
					'start' => array(
						'description'       => __( 'Start date (YYYY-MM-DD format).', 'storeseeder' ),
						'type'              => 'string',
						'format'            => 'date',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => array( $this, 'validate_date' ),
					),
					'end'   => array(
						'description'       => __( 'End date (YYYY-MM-DD format).', 'storeseeder' ),
						'type'              => 'string',
						'format'            => 'date',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => array( $this, 'validate_date' ),
					),
				),
			),
			'relationships' => array(
				'description' => __( 'Control relationship creation with existing data.', 'storeseeder' ),
				'type'        => 'object',
				'properties'  => array(
					'create_missing' => array(
						'description' => __( 'Create missing related items if needed.', 'storeseeder' ),
						'type'        => 'boolean',
						'default'     => true,
					),
					'link_existing'  => array(
						'description' => __( 'Link to existing items when possible.', 'storeseeder' ),
						'type'        => 'boolean',
						'default'     => true,
					),
				),
			),
			'meta_options'  => array(
				'description' => __( 'Metadata generation options.', 'storeseeder' ),
				'type'        => 'object',
				'properties'  => array(
					'include_meta'  => array(
						'description' => __( 'Include additional metadata.', 'storeseeder' ),
						'type'        => 'boolean',
						'default'     => true,
					),
					'custom_fields' => array(
						'description' => __( 'Generate custom fields.', 'storeseeder' ),
						'type'        => 'boolean',
						'default'     => false,
					),
				),
			),
		);

		// Merge with resource-specific parameters.
		$resource_params = $this->get_resource_specific_params();

		$canonical = array_merge( $base_params, $resource_params );

		// Platform fields never overwrite a canonical parameter: that is what every platform
		// agrees on, and letting one driver redefine `count` or `locale` would break the
		// same-seed guarantee for all the others. Merged *into* the canonical set rather than
		// over it, which is the difference the test caught.
		return $canonical + $this->get_platform_params();
	}



	/**
	 * Every registered platform's extra parameters for this resource, merged.
	 *
	 * The union rather than the resolved platform's own, because routes are registered on
	 * `rest_api_init` and no platform has been resolved at that point — there is no per-request
	 * schema to register. So the endpoint accepts any driver's field, discovery lists them all
	 * with the platform named in each description, and a field sent to a platform that does not
	 * have it is reported back in the response rather than dropped in silence. See
	 * `ignored_params()`.
	 *
	 * A driver declaring a field that collides with a canonical parameter loses: the canonical
	 * one is what every platform agrees on, and letting a driver redefine it would break the
	 * same-seed guarantee for everyone else.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	protected function get_platform_params(): array {
		$fields = array();

		foreach ( Platform_Registry::instance()->all() as $platform ) {
			if ( ! $platform instanceof Platform_Driver ) {
				continue;
			}

			foreach ( $platform->fields( $this->get_resource_type() ) as $name => $schema ) {
				// First declaration wins, and a canonical parameter always beats a platform one:
				// array_merge() in get_generation_params() puts these last, so a collision would
				// otherwise let a driver redefine a field every platform agrees on.
				if ( ! isset( $fields[ $name ] ) ) {
					$fields[ $name ] = $schema;
				}
			}
		}

		return $fields;
	}

	/**
	 * Parameters the caller sent that the resolved platform will not use.
	 *
	 * Two kinds, and both are worth saying out loud: a field belonging to a different platform,
	 * and a canonical field this platform's capability declares it cannot store. Reporting them
	 * is the whole point — a control that silently does nothing is the bug this mechanism was
	 * built to stop repeating.
	 *
	 * @since 1.1.0
	 *
	 * @param Platform_Interface $platform The resolved target.
	 * @param WP_REST_Request    $request  The request, read for what the caller actually sent.
	 *
	 * @return array<int, string>
	 */
	protected function ignored_params( Platform_Interface $platform, WP_REST_Request $request ): array {
		$resource = $this->get_resource_type();
		$mine     = $platform instanceof Platform_Driver ? $platform->fields( $resource ) : array();
		$ignored  = array();

		// What the caller *sent*, not what the schema resolved. Every declared argument with a
		// default is present in the merged parameters whether or not anyone asked for it, so
		// comparing against those would report every other platform's field on every run — and a
		// warning that always fires is one nobody reads.
		$sent = array_merge(
			(array) $request->get_json_params(),
			(array) $request->get_body_params(),
			(array) $request->get_query_params()
		);

		// Another platform's field, accepted by the schema and meaningless here.
		foreach ( array_keys( $this->get_platform_params() ) as $name ) {
			if ( array_key_exists( $name, $sent ) && ! isset( $mine[ $name ] ) ) {
				$ignored[] = $name;
			}
		}

		// A canonical field this platform stores the resource without.
		$capability = $platform->supports()[ $resource ] ?? null;

		if ( $capability instanceof Capability ) {
			foreach ( $capability->get_ignored_fields() as $field ) {
				$ignored[] = $field;
			}
		}

		return array_values( array_unique( $ignored ) );
	}

	/**
	 * Validate date parameter
	 *
	 * Validates that date parameters are in the correct YYYY-MM-DD format.
	 * Used for date range parameters in generation requests to ensure
	 * proper date handling and prevent invalid date-related errors.
	 *
	 * @since 1.0.0
	 *
	 * @param string          $value   Date string to validate.
	 * @param WP_REST_Request $request Request object containing all parameters.
	 * @param string          $param   Parameter name being validated.
	 *
	 * @return bool|WP_Error True if date format is valid, WP_Error if invalid.
	 */
	public function validate_date( string $value, WP_REST_Request $request, string $param ) {
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return new WP_Error(
				'invalid_date',
				__( 'Date must be in YYYY-MM-DD format.', 'storeseeder' )
			);
		}
		return true;
	}

	/**
	 * Validate count parameter
	 *
	 * Validates the count parameter for generation requests, ensuring it's
	 * a positive integer within the allowed range (1-100). Prevents invalid
	 * or potentially harmful generation requests.
	 *
	 * @since 1.0.0
	 *
	 * @param int|string|mixed $value    Parameter value to validate (should be numeric).
	 * @param WP_REST_Request  $request  Request object containing all parameters.
	 * @param string           $param    Parameter name being validated.
	 *
	 * @return bool|WP_Error True if count is valid, WP_Error with details if invalid.
	 */
	public function validate_count( $value, WP_REST_Request $request, string $param ) {
		$int_value = (int) $value;
		if ( ! is_numeric( $value ) || $int_value <= 0 || $int_value > 100 ) {
			return new WP_Error(
				'invalid_count',
				__( 'Count must be a number between 1 and 100.', 'storeseeder' )
			);
		}

		return true;
	}

	/**
	 * Get public schema for the endpoint
	 *
	 * Returns the JSON Schema definition for the REST API endpoint response.
	 * Used for API documentation, client validation, and ensuring consistent
	 * response structures. Includes base properties and resource-specific extensions.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> JSON Schema definition for the API response.
	 */
	public function get_public_item_schema(): array {
		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			// translators: Resource type.
			'title'      => sprintf( __( '%s Generation Response', 'storeseeder' ), $this->get_resource_type_label() ),
			'type'       => 'object',
			'properties' => array(
				'generated' => array(
					'description' => __( 'Number of items generated.', 'storeseeder' ),
					'type'        => 'integer',
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
			),
		);

		// Add resource-specific properties.
		$resource_properties = $this->get_resource_specific_properties();
		if ( ! empty( $resource_properties ) ) {
			$schema['properties'] = array_merge( $schema['properties'], $resource_properties );
		}

		return $schema;
	}

	/**
	 * Get resource-specific schema properties
	 *
	 * Returns additional JSON Schema properties specific to the resource type.
	 * Can be overridden by child controller classes to add custom response
	 * properties for their specific resource type (e.g., product attributes,
	 * customer fields). Returns empty array by default.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> Resource-specific schema properties array.
	 */
	protected function get_resource_specific_properties(): array {
		return array();
	}

	/**
	 * Get resource-specific generation parameters
	 *
	 * Returns additional parameter definitions specific to the resource type.
	 * Can be overridden by child controller classes to add custom parameters
	 * for their specific generation requirements (e.g., product categories,
	 * customer demographics). Returns empty array by default.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> Resource-specific parameter definitions.
	 */
	protected function get_resource_specific_params(): array {
		return array();
	}

	/**
	 * Sanitize array parameter
	 *
	 * Sanitizes array parameters for REST API requests using WordPress
	 * sanitization functions. Ensures all array values are properly cleaned
	 * to prevent XSS and other injection attacks. Returns empty array if
	 * input is not an array.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Parameter value that should be an array.
	 *
	 * @return array<string, mixed> Sanitized array with cleaned values.
	 */
	public function sanitize_array( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		return array_map( 'sanitize_text_field', $value );
	}

	/**
	 * Log generation errors
	 *
	 * @param \Exception $exception     The exception that occurred.
	 * @param string     $resource_type The resource type being generated.
	 * @param int        $count         Number of items being generated.
	 */
	protected function log_generation_error( \Exception $exception, string $resource_type, int $count ): void {
		$message = sprintf(
			'Failed to generate %d %s: %s',
			$count,
			$resource_type,
			$exception->getMessage()
		);
	}
}
