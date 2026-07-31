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

use StoreSeeder\Access;

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
	 * Each ability maps to one existing REST controller / generator pair — twice over,
	 * since a generator has a read-only preview tool as well as the one that writes. The
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

		// Which kinds of tool this site offers is a setting, and the registry applies it.
		// Nothing is registered when the AI surface is off, so there is nothing to call.
		foreach ( Registry::instance()->tools() as $id => $args ) {
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

		// No server at all when the AI surface is off, so /wp-json/storeseeder-mcp/mcp
		// stops existing rather than answering with an empty tool list. A client then
		// fails to connect, which is the honest signal.
		if ( ! Settings::enabled() ) {
			return;
		}

		$tool_ids = Registry::instance()->tool_ids();

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
	 * What this site would need for MCP, and what it has.
	 *
	 * Reported rather than inferred in the admin: MCP depends on two plugins that are not
	 * StoreSeeder's to install, and "it does not work" is a support ticket while "the
	 * Abilities API is missing" is an afternoon's fix. Static because the admin asks before
	 * any server is built.
	 *
	 * @since 1.1.0
	 *
	 * @return array{available: bool, abilities_api: bool, adapter: bool, abilities: int, tools: int, enabled: bool, preview: bool, generate: bool, can_manage: bool, route: string}
	 */
	public static function status(): array {
		$abilities_api = function_exists( 'wp_register_ability' );
		// The adapter's own init action is the honest signal: the class list has moved
		// between releases, and a missing class name would read as a missing plugin.
		$adapter = did_action( 'mcp_adapter_init' ) > 0 || class_exists( '\WP\MCP\Core\McpAdapter' );

		$settings = Settings::all();

		return array_merge(
			$settings,
			array(
				'available'     => $abilities_api && $adapter,
				'abilities_api' => $abilities_api,
				'adapter'       => $adapter,
				// Generators, not tools: the number of things that can be generated does
				// not change when a switch withdraws one kind of tool for them.
				'abilities'     => count( Registry::instance()->ids() ),
				// What an AI client would actually see listed, under these settings.
				'tools'         => count( Registry::instance()->tool_ids() ),
				// Changing what an agent may do to the store is an administrator's call,
				// so the admin renders the switches read-only for everyone else rather
				// than offering a control that would 403.
				'can_manage'    => Access::current_user_can_manage(),
				// Where an MCP client points once both are present.
				'route'         => rest_url( self::REST_NAMESPACE . '/' . self::REST_ROUTE ),
			)
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
