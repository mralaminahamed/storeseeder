<?php
/**
 * Abstract Generator Class for StoreSeeder
 *
 * Base class providing common functionality for all data generators in the plugin.
 * Implements the Template Method pattern for consistent generation workflows across
 * all generator types. Handles FakerPHP integration, logging, validation, and
 * WordPress hooks for extensibility.
 *
 * @since   1.0.0
 * @package StoreSeeder\Generation
 */

namespace StoreSeeder\Generation;

use Exception;
use Faker\Factory;
use Faker\Generator as Faker_Generator;
use Faker\Provider\DateTime;
use StoreSeeder\Platforms\Locale;
use StoreSeeder\Platforms\Platform_Interface;
use StoreSeeder\Recipes\Registry as Recipe_Registry;
use WP_Error;
use wpdb;

/**
 * Abstract Generator Class
 *
 * Provides the foundation for all data generators in StoreSeeder.
 * Implements common functionality including FakerPHP integration, WordPress
 * database access, logging, validation, and the core generation workflow.
 * Uses the Template Method pattern to ensure consistent behavior across all generators.
 *
 * Key Features:
 * - FakerPHP integration with locale support
 * - WordPress database abstraction
 * - Comprehensive logging system
 * - Parameter validation and sanitization
 * - WordPress action/filter hooks for extensibility
 * - Batch processing with memory management
 *
 * @since 1.0.0
 */
abstract class Generator {
	/**
	 * FakerPHP generator instance
	 *
	 * Holds the FakerPHP generator instance configured with the appropriate locale
	 * and providers for generating realistic test data. Initialized in set_faker().
	 *
	 * @since 1.0.0
	 * @var Faker_Generator
	 */
	protected Faker_Generator $faker;

	/**
	 * WordPress database instance
	 *
	 * Reference to the global WordPress database object for performing
	 * secure database operations using wpdb methods and prepared statements.
	 *
	 * @since 1.0.0
	 * @var wpdb
	 */
	protected wpdb $wpdb;

	/**
	 * Maximum items to generate per batch
	 *
	 * Limits the number of items that can be generated in a single batch
	 * to prevent memory exhaustion and timeout issues. Can be overridden
	 * by child classes for specific requirements.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	protected int $max_batch_size = 100;

	/**
	 * Locale for FakerPHP generator
	 *
	 * Stores the locale code (e.g., 'en_US', 'fr_FR') used to configure
	 * the FakerPHP generator for locale-specific data generation.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected string $locale;

	/**
	 * Generation parameters from REST API
	 *
	 * Stores the parameters passed from the REST API request for customizing
	 * the data generation process. Includes options like count, locale, seed,
	 * and generator-specific parameters.
	 *
	 * @since 1.0.0
	 * @var array<string, mixed>
	 */
	protected array $generation_params = array();

	/**
	 * Failures collected while generating the current batch
	 *
	 * Individual items are allowed to fail without aborting the batch, but the
	 * reason has to survive the loop — otherwise a batch where every item failed
	 * is indistinguishable from a batch that was never asked to do anything, and
	 * the caller reports success for zero rows.
	 *
	 * @since 1.0.0
	 * @var array<int, string>
	 */
	protected array $generation_errors = array();

	/**
	 * The platform this run writes to
	 *
	 * Injected by the controller once the target has been resolved, because the
	 * decision belongs to the request, not to the generator. Null only in the preview
	 * path, which never persists anything and so needs no platform at all.
	 *
	 * @since 1.1.0
	 * @var Platform_Interface|null
	 */
	protected $platform = null;

	/**
	 * Get the failures collected during the last generate() call.
	 *
	 * Reset at the start of every generate(), so it always describes the most
	 * recent batch. Empty when every item succeeded.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string> Failure messages, in the order they occurred.
	 */
	public function get_generation_errors(): array {
		return $this->generation_errors;
	}

	/**
	 * Constructor
	 *
	 * Initializes the generator with a reference to the WordPress database object.
	 * Sets up the foundation for database operations and ensures proper integration
	 * with WordPress database abstraction layer.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function __construct() {
		global $wpdb;

		$this->wpdb = $wpdb;
	}

	/**
	 * Set FakerPHP instance
	 *
	 * Configures and initializes the FakerPHP generator with the specified locale
	 * and additional providers. Adds DateTime provider for enhanced data generation
	 * capabilities including timestamps.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function set_faker(): void {
		$this->faker = Factory::create( $this->get_faker_locale() );

		// Add additional providers directly to avoid recursive calls.
		$this->faker->addProvider( new DateTime( $this->faker ) );
	}

	/**
	 * Get FakerPHP instance
	 *
	 * Returns the configured FakerPHP generator instance for generating
	 * realistic test data. Ensures the generator is properly initialized
	 * with locale and providers before use.
	 *
	 * @since 1.0.0
	 *
	 * @return Faker_Generator The configured FakerPHP generator instance.
	 */
	public function get_faker(): Faker_Generator {
		return $this->faker;
	}

	/**
	 * Set locale for FakerPHP generator
	 *
	 * Configures the locale for the FakerPHP generator to produce locale-specific
	 * test data. The locale affects names, addresses, phone numbers, and other
	 * culturally-specific data patterns.
	 *
	 * @since 1.0.0
	 *
	 * @param string $locale Locale code (e.g., 'en_US', 'fr_FR', 'de_DE').
	 *
	 * @return void
	 */
	public function set_locale( string $locale = 'en_US' ): void {
		$this->locale = $locale;
	}

	/**
	 * Get locale for FakerPHP generator
	 *
	 * Returns the currently configured locale code for the FakerPHP generator.
	 * Used internally for generator configuration and can be overridden by
	 * child classes for specific locale handling requirements.
	 *
	 * @since 1.0.0
	 *
	 * @return string The configured locale code (e.g., 'en_US').
	 */
	public function get_faker_locale(): string {
		return $this->locale;
	}

	/**
	 * Generate test data
	 *
	 * Orchestrates the complete data generation process using the Template Method pattern.
	 * Handles parameter validation, dependency checking, batch processing, and error handling.
	 * Fires WordPress actions at key points to allow for extensibility and monitoring.
	 *
	 * Process Flow:
	 * 1. Validate generation count and parameters
	 * 2. Apply WordPress filters to generation parameters
	 * 3. Set random seed if provided for reproducible results
	 * 4. Generate items in a loop with error handling
	 * 5. Apply filters to each generated item
	 * 6. Fire completion actions
	 *
	 * @since 1.0.0
	 *
	 * @param int $count Number of items to generate (1-100 per batch).
	 *
	 * @return array<int, array<string, mixed>>|WP_Error Array of generated items or error object.
	 */
	public function generate( int $count ) {
		$resource_type = $this->get_resource_type();

		// Validate count.
		$validation_result = $this->validate_count( $count );
		if ( is_wp_error( $validation_result ) ) {
			return $validation_result;
		}

		/**
		 * Filters the generation parameters for a specific resource type.
		 *
		 * Allows developers to modify generation parameters before they are used
		 * by the generator. Useful for customizing default values, adding validation,
		 * or implementing custom generation logic.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, mixed> $generation_params Current generation parameters.
		 */
		$this->generation_params = apply_filters( "storeseeder_generation_params_{$resource_type}", $this->generation_params );

		// Apply seed for reproducibility if provided.
		$seed = $this->generation_params['seed'] ?? null;
		if ( $seed ) {
			$this->faker->seed( (int) $seed );
			$this->log( "Seeded Faker with value: {$seed}", 'info' );
		}

		$results                 = array();
		$this->generation_errors = array();

		try {
			for ( $i = 0; $i < $count; $i++ ) {
				/**
				 * Fires before generating a single item of the specified resource type.
				 *
				 * Allows developers to perform actions before individual item generation,
				 * such as logging, validation, or setup operations.
				 *
				 * @since 1.0.0
				 * @hook  storeseeder_before_generate_single_item_{$resource_type}
				 */
				do_action( "storeseeder_before_generate_single_item_{$resource_type}" );

				try {
					$item_result = $this->generate_single_item();

					if ( is_wp_error( $item_result ) ) {
						$this->log( 'Single item generation failed: ' . $item_result->get_error_message(), 'warning' );
						$this->generation_errors[] = $item_result->get_error_message();
						continue;
					}

					/**
					 * Filters the generated item of the specified resource type.
					 *
					 * Allows developers to modify or validate generated items before they are
					 * added to the results array. Useful for custom validation, data transformation,
					 * or additional processing.
					 *
					 * @since 1.0.0
					 * @hook  storeseeder_generated_item_{$resource_type}
					 *
					 * @param array<string, mixed>|WP_Error $item_result The generated item result.
					 * @param int                           $i           The current item index in the generation loop.
					 */
					$item_result = apply_filters( "storeseeder_generated_item_{$resource_type}", $item_result, $i );

					if ( $item_result && ! is_wp_error( $item_result ) ) {
						$results[] = $item_result;
					}

					/**
					 * Fires after generating a single item of the specified resource type.
					 *
					 * Allows developers to perform actions after individual item generation,
					 * such as cleanup, logging, or triggering related processes.
					 *
					 * @since 1.0.0
					 * @hook  storeseeder_after_generate_single_item_{$resource_type}
					 *
					 * @param array<string, mixed>|WP_Error $item_result The generated item result.
					 * @param int                           $i           The current item index in the generation loop.
					 */
					do_action( "storeseeder_after_generate_single_item_{$resource_type}", $item_result, $i );
				} catch ( Exception $e ) {
					$this->log( "Per-item exception: {$e->getMessage()}", 'error' );
					$this->generation_errors[] = $e->getMessage();
					continue;
				}
			}

			/**
			 * Fires after completing a batch generation of the specified resource type.
			 *
			 * Allows developers to perform batch-level operations after all items have been
			 * generated, such as cache clearing, search index updates, or notification sending.
			 *
			 * @since 1.0.0
			 * @hook  storeseeder_after_batch_generate_{$resource_type}
			 *
			 * @param array<int, mixed> $results All successfully generated items in the batch.
			 * @param int               $count   Total number of items attempted to generate.
			 */
			do_action( "storeseeder_after_batch_generate_{$resource_type}", $results, $count );

			return $results;
		} catch ( Exception $e ) {
			$this->log( 'Batch generation exception: ' . $e->getMessage(), 'error' );
			return new WP_Error(
				'generation_failed',
				sprintf(
				/* translators: %s: Error message */
					__( 'Generation failed: %s', 'storeseeder' ),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Generate a single item
	 *
	 * Abstract method that must be implemented by all concrete generator classes.
	 * Contains the specific logic for generating one item of the resource type
	 * (product, customer, order, etc.). Should handle all business logic,
	 * validation, and database operations for creating a single item.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>|WP_Error Generated item data array or error object.
	 */
	final protected function generate_single_item() {
		$entity = $this->build_entity();

		if ( is_wp_error( $entity ) ) {
			return $entity;
		}

		/**
		 * Filters every canonical entity before it is handed to a platform writer.
		 *
		 * The one to hook when the change is not resource-specific — stamping a run id
		 * on everything generated, say, which through the per-resource filter would mean
		 * seventeen callbacks. Runs first, so the per-resource filter still has the last
		 * word.
		 *
		 * @since 1.1.0
		 * @hook  storeseeder_canonical_entity
		 *
		 * @param array<string, mixed> $entity        The canonical entity.
		 * @param string               $resource_type The canonical resource name, e.g. `cart_session`.
		 * @param Generator            $generator     The generator that built it.
		 */
		$entity = apply_filters(
			'storeseeder_canonical_entity',
			$entity,
			$this->get_resource_type(),
			$this
		);

		/**
		 * Filters the canonical entity before it is handed to a platform writer.
		 *
		 * The entity is platform-neutral at this point: money is an integer in the
		 * currency's minor unit and statuses use the StoreSeeder\Platforms\Status
		 * vocabulary. A filter here therefore applies to every platform equally,
		 * which is what makes it the right place to change generated data.
		 *
		 * @since 1.1.0
		 * @hook  storeseeder_canonical_{$resource_type}
		 *
		 * @param array<string, mixed> $entity    The canonical entity.
		 * @param Generator            $generator The generator that built it.
		 */
		$entity = apply_filters(
			"storeseeder_canonical_{$this->get_resource_type()}",
			$entity,
			$this
		);

		$writer = $this->get_writer();

		if ( is_wp_error( $writer ) ) {
			return $writer;
		}

		$resource = $this->get_resource_type();
		$platform = $this->platform->id();

		/**
		 * Fires before one entity is written to a platform.
		 *
		 * @since 1.1.0
		 *
		 * @param array<string, mixed> $entity The canonical entity about to be written.
		 */
		do_action( "storeseeder_before_write_{$platform}_{$resource}", $entity );

		$result = $writer->write( $entity );

		// Recorded here rather than in each writer: the ledger is what lets the admin offer
		// to delete generated data without touching the store's own rows, and eighteen
		// writers each remembering to record would be eighteen chances to forget.
		if ( ! is_wp_error( $result ) && isset( $result['id'] ) ) {
			Ledger::record( $platform, $resource, $result['id'] );
		}

		/**
		 * Fires after one entity has been written to a platform.
		 *
		 * Runs for failures too — $result is a WP_Error then — so a listener can
		 * observe the whole batch rather than only its successful half.
		 *
		 * @since 1.1.0
		 *
		 * @param array<string, mixed>|WP_Error $result The persisted item, or the failure.
		 * @param array<string, mixed>          $entity The canonical entity.
		 */
		do_action( "storeseeder_after_write_{$platform}_{$resource}", $result, $entity );

		return $result;
	}

	/**
	 * Build one platform-neutral entity
	 *
	 * Concrete generators implement this with FakerPHP and loaded sample data only. It
	 * must name no platform: no models, no table names, no platform-specific status
	 * strings, and no database reads. That restriction is what lets one generator feed
	 * every platform, and lets a fixed seed produce the same data on all of them.
	 *
	 * Resolving foreign keys is deliberately *not* part of this — an order's line items
	 * need real product variations, and each platform stores and randomises those
	 * differently. That work belongs to the writer. See StoreSeeder\Platforms\Writer.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, mixed>|WP_Error The canonical entity, or why one could not be built.
	 */
	abstract protected function build_entity();

	/**
	 * The writer for this generator's resource on the target platform
	 *
	 * @since 1.1.0
	 *
	 * @return \StoreSeeder\Platforms\Writer|WP_Error
	 */
	protected function get_writer() {
		$resource = $this->get_resource_type();

		if ( null === $this->platform ) {
			return new WP_Error(
				'storeseeder_no_platform',
				__( 'No target platform was set for this run.', 'storeseeder' )
			);
		}

		$writer = $this->platform->writer( $resource );

		if ( null === $writer ) {
			return new WP_Error(
				'storeseeder_unsupported_resource',
				sprintf(
					/* translators: 1: resource name, 2: platform display name. */
					__( '%1$s cannot be generated for %2$s.', 'storeseeder' ),
					$resource,
					$this->platform->label()
				)
			);
		}

		// Handed over per run rather than at construction: the faker is created after
		// the generator, and re-seeded per request.
		$writer->set_faker( $this->get_faker() );
		$writer->set_params( $this->generation_params );

		return $writer;
	}

	/**
	 * Set the platform this run writes to
	 *
	 * @since 1.1.0
	 *
	 * @param Platform_Interface $platform Resolved target platform.
	 *
	 * @return void
	 */
	public function set_platform( Platform_Interface $platform ): void {
		$this->platform = $platform;
	}

	/**
	 * The platform this run writes to
	 *
	 * @since 1.1.0
	 *
	 * @return Platform_Interface|null
	 */
	public function get_platform(): ?Platform_Interface {
		return $this->platform;
	}

	/**
	 * Get the resource type name
	 *
	 * Abstract method that must be implemented by all concrete generator classes.
	 * Returns a string identifier for the type of resource being generated
	 * (e.g., 'product', 'customer', 'order'). Used for logging, filtering,
	 * and WordPress action/filter hook naming.
	 *
	 * @since 1.0.0
	 *
	 * @return string Resource type identifier (lowercase, no spaces).
	 */
	abstract protected function get_resource_type(): string;

	/**
	 * Get supported data types for this generator
	 *
	 * @return array Supported types with descriptions
	 */
	abstract public function get_supported_types(): array;

	/**
	 * Get generator description
	 *
	 * @return string Description
	 */
	abstract public function get_description(): string;

	/**
	 * Set generation parameters
	 *
	 * Stores the parameters received from the REST API request for use during
	 * the generation process. These parameters control various aspects of
	 * data generation including count, locale, seed, and generator-specific options.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $params Generation parameters from REST API request.
	 *
	 * @return void
	 */
	public function set_generation_params( array $params ): void {
		$this->generation_params = $params;
	}

	/**
	 * Build a read-only preview of what generation would produce
	 *
	 * Nothing is persisted: rows come from FakerPHP and loaded sample data only,
	 * which is what makes this safe to call on every parameter change in the
	 * admin's live preview table.
	 *
	 * @since 1.0.1
	 *
	 * @param int $count Number of preview rows to build. Clamped to 1–25.
	 *
	 * @return array{columns: array<int, array{key: string, label: string}>, rows: array<int, array<string, array{v: mixed, kind: string}>>}
	 */
	public function preview( int $count ): array {
		// Clamped so a preview stays cheap no matter what the caller asks for —
		// it feeds a table that redraws on every parameter change.
		$count = max( 1, min( 25, $count ) );
		$rows  = array();

		for ( $i = 0; $i < $count; $i++ ) {
			$rows[] = $this->build_preview_row();
		}

		return array(
			'columns' => $this->get_preview_columns(),
			'rows'    => $rows,
		);
	}

	/**
	 * Column definitions for the preview table
	 *
	 * Each column is an array of:
	 *   - 'key'   (string) matching a key in every preview row.
	 *   - 'label' (string) header text.
	 *
	 * Concrete generators override this to describe their own resource.
	 *
	 * @since 1.0.1
	 *
	 * @return array<int, array{key: string, label: string}>
	 */
	protected function get_preview_columns(): array {
		return array(
			array(
				'key'   => 'id',
				'label' => __( 'ID', 'storeseeder' ),
			),
			array(
				'key'   => 'value',
				'label' => __( 'Value', 'storeseeder' ),
			),
		);
	}

	/**
	 * Build one representative preview row
	 *
	 * Uses FakerPHP and loaded sample data only — this must never write to the
	 * database, because it runs on every keystroke in the admin.
	 *
	 * Each cell is an array of:
	 *   - 'v'    (mixed)  display value.
	 *   - 'kind' (string) rendering hint: mono | money | num | status | badge | stars | text.
	 *
	 * @since 1.0.1
	 *
	 * @return array<string, array{v: mixed, kind: string}>
	 */
	protected function build_preview_row(): array {
		return array(
			'id'    => array(
				'v'    => $this->get_faker()->numberBetween( 10000, 99999 ),
				'kind' => 'mono',
			),
			'value' => array(
				'v'    => $this->get_faker()->words( 2, true ),
				'kind' => 'text',
			),
		);
	}

	/**
	 * Validate the requested count
	 *
	 * @since 1.0.0
	 *
	 * @param int $count Number of items to generate.
	 *
	 * @return true|WP_Error True if count is valid, WP_Error with details if invalid.
	 */
	protected function validate_count( int $count ) {
		if ( $count <= 0 ) {
			return new WP_Error(
				'invalid_count',
				__( 'Count must be a positive number.', 'storeseeder' )
			);
		}

		if ( $count > $this->max_batch_size ) {
			return new WP_Error(
				'count_too_large',
				sprintf(
				/* translators: %d: Maximum batch size */
					__( 'Count cannot exceed %d items per batch.', 'storeseeder' ),
					$this->max_batch_size
				)
			);
		}

		return true;
	}

	/**
	 * Load sample data for the current locale
	 *
	 * Loads locale-specific sample data from JSON files for use in data generation.
	 * Each generator can override this method to specify which data files to load
	 * based on the resource type and current locale setting.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> Array of sample data arrays loaded from JSON files.
	 */
	protected function load_sample_data(): array {
		// Default implementation returns empty array.
		// Child classes should override this method to load their specific data.
		return array();
	}

	/**
	 * Load JSON file and return decoded data
	 *
	 * Helper method to load and decode JSON files containing sample data.
	 * Uses WordPress Filesystem API for secure file operations with proper
	 * error handling and JSON decoding.
	 *
	 * @since 1.0.0
	 *
	 * @param string $file_path Path to JSON file.
	 *
	 * @return array|null Decoded JSON data or null on failure.
	 */
	protected function load_json_file( string $file_path ): ?array {
		// Initialize WordPress Filesystem if not already available.
		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			require_once ABSPATH . '/wp-admin/includes/file.php';
			WP_Filesystem();
		}

		// Check if file exists using WP Filesystem.
		if ( ! $wp_filesystem->exists( $file_path ) ) {
			$this->log( "Sample data file not found: {$file_path}", 'warning' );
			return null;
		}

		// Read file content using WP Filesystem.
		$json_content = $wp_filesystem->get_contents( $file_path );
		if ( false === $json_content ) {
			$this->log( "Failed to read sample data file: {$file_path}", 'error' );
			return null;
		}

		// Decode JSON content.
		$data = json_decode( $json_content, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$this->log( "Failed to decode JSON from file: {$file_path} - " . json_last_error_msg(), 'error' );
			return null;
		}

		return $data;
	}

	/**
	 * Get sample data file path for a specific resource and locale
	 *
	 * Returns the first candidate that exists, or — when none do — the most specific one, so a
	 * caller that wants to report a miss still has a path to name.
	 *
	 * @since 1.0.0
	 *
	 * @param string $resource_type The resource type (e.g., 'products', 'customers').
	 * @param string $filename      The filename without extension.
	 *
	 * @return string Full path to the sample data file.
	 */
	protected function get_sample_data_path( string $resource_type, string $filename ): string {
		$candidates = $this->sample_data_candidates( $resource_type, $filename );

		foreach ( $candidates as $candidate ) {
			if ( file_exists( $candidate ) ) {
				return $candidate;
			}
		}

		return $candidates[0];
	}

	/**
	 * Every place a sample data file could be, most specific first.
	 *
	 * Two axes, and they are deliberately ordered rather than merged. A recipe is a *vocabulary*
	 * — the words that make one coherent shop — and it overrides only what it ships, so a fashion
	 * recipe silently inherits postcode patterns it has no business redefining.
	 *
	 * The locale axis is the one that had a bug. This method used to build a single path and stop,
	 * so a locale with no data file fell through `load_json_file()`'s null to whatever inline
	 * literals the generator carried — every non-`en_US` store came out full of "Widget" and
	 * "Gadget", with a `WP_DEBUG_LOG` line as the only signal. Seventy-two of the seventy-three
	 * locales the admin offers. The same shape as the bug `Platforms\Locale` exists to prevent, and
	 * the reason a translated recipe is a content problem rather than a code one now.
	 *
	 * Bundled data is searched before the downloaded archive: a recipe ships with the plugin and
	 * needs no consent, while the remote repository is an optional extra that may never have been
	 * fetched.
	 *
	 * @since 1.2.0
	 *
	 * @param string $resource_type The resource type (e.g., 'products', 'customers').
	 * @param string $filename      The filename without extension.
	 *
	 * @return string[] Absolute paths, most specific first. Never empty.
	 */
	protected function sample_data_candidates( string $resource_type, string $filename ): array {
		$locale     = $this->get_faker_locale();
		$upload_dir = wp_upload_dir();
		$remote     = $upload_dir['basedir'] . '/storeseeder-sample-data-fluent-cart';
		$bundled    = STORESEEDER_PLUGIN_PATH . 'data';

		$locales = array_unique( array( $locale, Locale::DEFAULT_LOCALE ) );
		$recipe  = $this->sample_data_recipe();

		$candidates = array();

		foreach ( $locales as $code ) {
			if ( '' !== $recipe ) {
				$candidates[] = "{$bundled}/recipes/{$recipe}/{$resource_type}/{$code}/{$filename}.json";
			}
		}

		foreach ( $locales as $code ) {
			$candidates[] = "{$bundled}/default/{$resource_type}/{$code}/{$filename}.json";
			$candidates[] = "{$remote}/{$resource_type}/{$code}/{$filename}.json";
		}

		return $candidates;
	}

	/**
	 * One named word list, from the active recipe or the caller's own default.
	 *
	 * The default is passed in rather than looked up because it is a constant on the generator —
	 * `Product_Tag::LABELS`, `Brand::STEMS`. Those constants stay: they are the shipped vocabulary,
	 * and moving them into JSON would mean a store with no recipe reading a file to learn words
	 * the class already knows.
	 *
	 * A recipe file that exists but holds nothing usable falls back too. An empty list would reach
	 * `randomElement()` and fail on every item, which is a worse answer than generic words.
	 *
	 * @since 1.2.0
	 *
	 * The directory is named rather than derived from the resource. `product_category` would
	 * pluralise to `product_categorys`, and no rule that handles it also handles
	 * `shipping_classes` — the same reason generators carry an explicit `resource` alongside their
	 * REST route instead of deriving one from the other.
	 *
	 * @param string   $directory Vocabulary directory, e.g. `products`.
	 * @param string   $filename  JSON file name, without the extension.
	 * @param string[] $fallback  Words to use when the recipe ships none.
	 *
	 * @return string[] Never empty, as long as $fallback is not.
	 */
	protected function vocabulary( string $directory, string $filename, array $fallback ): array {
		$loaded = $this->load_json_file( $this->get_sample_data_path( $directory, $filename ) );

		if ( null === $loaded ) {
			return $fallback;
		}

		// A file may hold either a bare list or a map of lists; take the named key when the file
		// is a map, so one file can carry several lists the way `product_names.json` does.
		$words = isset( $loaded[ $filename ] ) && is_array( $loaded[ $filename ] ) ? $loaded[ $filename ] : $loaded;

		$words = array_values(
			array_filter(
				$words,
				static function ( $word ): bool {
					return is_string( $word ) && '' !== trim( $word );
				}
			)
		);

		return array() === $words ? $fallback : $words;
	}

	/**
	 * The recipe whose vocabulary this run should prefer, or '' for none.
	 *
	 * Validated against the registry rather than trusted, because the value reaches a filesystem
	 * path. `sanitize_key()` already forbids a separator, and matching a registered id forbids
	 * everything else.
	 *
	 * @since 1.2.0
	 *
	 * @return string Registered recipe id, or ''.
	 */
	protected function sample_data_recipe(): string {
		$requested = sanitize_key( (string) ( $this->generation_params['recipe'] ?? '' ) );

		if ( '' === $requested ) {
			return '';
		}

		return null === Recipe_Registry::instance()->get( $requested ) ? '' : $requested;
	}

	/**
	 * Log generation activity
	 *
	 * Records generation activities for debugging and monitoring purposes.
	 * Only logs when WP_DEBUG_LOG is enabled to avoid performance impact
	 * in production environments. Includes structured logging with resource
	 * type, log level, and additional context information.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $message Log message describing the activity.
	 * @param string               $level   Log level ('info', 'warning', 'error', 'debug').
	 * @param array<string, mixed> $context Additional context data for debugging.
	 *
	 * @return void
	 */
	public function log( string $message, string $level = 'info', array $context = array() ): void {
		if ( function_exists( 'error_log' ) && WP_DEBUG_LOG ) {
			$context['resource_type'] = $this->get_resource_type();
			$log_message              = sprintf(
				'[StoreSeeder] [%s] [%s] %s %s',
				strtoupper( $level ),
				$this->get_resource_type(),
				$message,
				! empty( $context ) ? '- Context: ' . wp_json_encode( $context ) : ''
			);
			error_log( $log_message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}
}
