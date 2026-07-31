<?php
/**
 * StoreSeeder MCP Server Registration
 *
 * Registers the MCP server via the WordPress mcp-adapter, exposing all
 * StoreSeeder data-generation abilities as MCP tools so AI clients such as
 * Claude Desktop and VS Code Copilot can invoke them with natural language.
 *
 * Dependencies (must be installed on the WordPress site):
 *   - abilities-api  (included in WordPress 6.9+, or download from GitHub)
 *   - mcp-adapter    (download from https://github.com/WordPress/mcp-adapter/releases)
 *
 * @package StoreSeeder\MCP
 * @since   2.1.0
 */

namespace StoreSeeder\MCP;

defined( 'ABSPATH' ) || exit;

/**
 * MCP_Server
 *
 * Hooks into `mcp_adapter_init` and creates one MCP server that exposes
 * every registered StoreSeeder ability as an MCP tool.
 *
 * @since 1.0.0
 */
class MCP_Server {

	/**
	 * Unique server identifier used by the mcp-adapter.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const SERVER_ID = 'storeseeder';

	/**
	 * REST API namespace for the MCP endpoint.
	 * Results in: /wp-json/storeseeder-mcp/mcp
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const REST_NAMESPACE = 'storeseeder-mcp';

	/**
	 * REST API route segment.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const REST_ROUTE = 'mcp';

	/**
	 * Register all WordPress hooks required by this class.
	 *
	 * Called from the plugin's main init() method.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init(): void {
		// Register ability categories + abilities as soon as the Abilities API is ready.
		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_ability_categories' ) );
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );

		// Create the MCP server once the MCP Adapter is initialised.
		add_action( 'mcp_adapter_init', array( $this, 'register_mcp_server' ) );
	}

	/**
	 * Register the ability category that groups all StoreSeeder abilities.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_ability_categories(): void {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		wp_register_ability_category(
			Ability::CATEGORY,
			array(
				'label'       => __( 'StoreSeeder', 'storeseeder' ),
				'description' => __( 'Generate realistic test data for Fluent Cart stores: products, customers, orders, coupons, variations, shipping plans, tax classes, transactions, cart sessions, product attributes, shipping classes, labels, order tax lines, product downloads, and subscriptions.', 'storeseeder' ),
			)
		);
	}

	/**
	 * Register all StoreSeeder abilities with the Abilities API.
	 *
	 * Each ability maps 1-to-1 with an existing REST controller / generator pair. The
	 * definitions come from the abilities themselves, through the registry.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		// Guard: StoreSeeder must be active and Fluent Cart present.
		if ( ! $this->dependencies_met() ) {
			return;
		}

		foreach ( Registry::instance()->definitions() as $id => $args ) {
			$name = strtolower( $id );

			// An empty name would register an unreachable ability. The registry already
			// refuses abilities that could produce one, so this is belt and braces --
			// and it is what tells the analyser the name is non-empty.
			if ( ! $name ) {
				continue;
			}

			wp_register_ability( $name, $args );
		}
	}

	/**
	 * Create the MCP server and expose all StoreSeeder abilities as tools.
	 *
	 * @since 1.0.0
	 * @param mixed $adapter The MCP adapter instance provided by the hook.
	 * @return void
	 */
	public function register_mcp_server( $adapter ): void {
		if ( ! $this->dependencies_met() ) {
			return;
		}

		$tool_ids = Registry::instance()->ids();

		$adapter->create_server(
			self::SERVER_ID,
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			__( 'StoreSeeder', 'storeseeder' ),
			__( 'Generate realistic test data for Fluent Cart stores. Supports products, customers, orders, coupons, product variations, shipping plans, tax classes, payment transactions, cart sessions, product attributes, shipping classes, labels, order tax lines, product downloads, and subscriptions.', 'storeseeder' ),
			STORESEEDER_VERSION,
			array(
				\WP\MCP\Transport\HttpTransport::class,
			),
			\WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler::class,
			\WP\MCP\Infrastructure\Observability\NullMcpObservabilityHandler::class,
			$tool_ids, // abilities exposed as tools.
			array(),   // resources (none).
			array()    // prompts (none).
		);
	}

	/**
	 * Return true only when both Fluent Cart and the Abilities API are active.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	private function dependencies_met(): bool {
		return function_exists( 'wp_register_ability' )
				&& storeseeder()->check_dependencies();
	}
}
