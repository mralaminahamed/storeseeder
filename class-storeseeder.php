<?php
/**
 * Main Plugin Class for StoreSeeder
 *
 * The main plugin class that orchestrates the entire StoreSeeder plugin functionality.
 * This class handles plugin initialization, admin interface setup, REST API registration,
 * asset management, and WordPress admin color scheme integration.
 *
 * @package StoreSeeder
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreSeeder\Rest\Controllers\Product;
use StoreSeeder\Rest\Controllers\Customer;
use StoreSeeder\Rest\Controllers\Order;
use StoreSeeder\Rest\Controllers\Coupon;
use StoreSeeder\Rest\Controllers\Product_Variation;
use StoreSeeder\Rest\Controllers\Shipping_Plan;
use StoreSeeder\Rest\Controllers\Tax_Class;
use StoreSeeder\Rest\Controllers\Transaction;
use StoreSeeder\Rest\Controllers\Cart_Session;
use StoreSeeder\Rest\Controllers\Attribute;
use StoreSeeder\Rest\Controllers\Refund;
use StoreSeeder\Rest\Controllers\Log;
use StoreSeeder\Rest\Controllers\Shipping_Class;
use StoreSeeder\Rest\Controllers\Label;
use StoreSeeder\Rest\Controllers\Order_Tax_Rate;
use StoreSeeder\Rest\Controllers\Product_Download;
use StoreSeeder\Rest\Controllers\Subscription;
use StoreSeeder\MCP\MCP_Server;
use StoreSeeder\Platforms\Registry;
use StoreSeeder\Platforms\Resolver;

/**
 * Main Plugin Class for StoreSeeder
 *
 * This class serves as the central orchestrator for the StoreSeeder plugin,
 * providing comprehensive test data generation capabilities for Fluent Cart stores.
 * It manages specialized generators, implements real-time validation, features a
 * modern React Router v7 interface, integrates with WordPress admin color schemes,
 * and provides advanced parameter configuration options.
 *
 * Key Features:
 * - Specialized data generators (Products, Customers, Orders, Coupons, etc.)
 * - Real-time validation and dependency checking
 * - Modern React-based admin interface with Router v7
 * - WordPress admin color scheme integration
 * - Advanced parameter configuration system
 * - REST API endpoints for programmatic access
 * - Comprehensive logging and error handling
 * - Multi-locale support for international data generation
 *
 * @since 1.0.0
 * @version 1.0.0
 */
class StoreSeeder {

	/**
	 * User meta key recording that the MCP hint was dismissed
	 *
	 * @since 1.0.1
	 * @var string
	 */
	const MCP_NOTICE_DISMISSED_META = 'storeseeder_mcp_notice_dismissed';

	/**
	 * AJAX action, and nonce action, for dismissing the MCP hint
	 *
	 * @since 1.0.1
	 * @var string
	 */
	const MCP_NOTICE_DISMISS_ACTION = 'storeseeder_dismiss_mcp_notice';

	/**
	 * Single instance of the plugin class
	 *
	 * Implements the singleton pattern to ensure only one instance of the plugin
	 * exists throughout the WordPress execution lifecycle. This prevents multiple
	 * initializations and ensures consistent state management.
	 *
	 * @since 1.0.0
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Plugin version number
	 *
	 * Stores the current version of the StoreSeeder plugin.
	 * Used for asset versioning, database migrations, and compatibility checks.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public string $version = STORESEEDER_VERSION;

	/**
	 * Get single instance of the plugin class
	 *
	 * Implements the singleton pattern to ensure only one instance of the plugin
	 * exists throughout the WordPress execution lifecycle. This method provides
	 * global access to the plugin instance while preventing multiple instantiations.
	 *
	 * @since 1.0.0
	 *
	 * @return self The single plugin instance.
	 */
	public static function get_instance(): self {
		return self::$instance ??= new self();
	}

	/**
	 * Initialize the plugin
	 *
	 * Sets up all necessary WordPress hooks and actions for plugin functionality.
	 * Registers activation/deactivation hooks, admin menus, assets, and REST routes.
	 * Performs dependency checks to ensure Fluent Cart is available before
	 * enabling plugin features.
	 *
	 * This method is called automatically during WordPress plugin loading.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function init(): void {
		register_activation_hook( STORESEEDER_PLUGIN_FILE, array( $this, 'activate_plugin' ) );
		register_deactivation_hook( STORESEEDER_PLUGIN_FILE, array( $this, 'flush_rewrite_rules' ) );

		add_action( 'admin_notices', array( $this, 'dependency_notice' ) );
		add_action( 'wp_ajax_' . self::MCP_NOTICE_DISMISS_ACTION, array( $this, 'ajax_dismiss_mcp_notice' ) );
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'in_admin_header', array( $this, 'hide_foreign_admin_notices' ), PHP_INT_MAX );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_filter( 'admin_body_class', array( $this, 'filter_admin_body_class' ) );
		add_filter(
			'plugin_action_links_' . plugin_basename( STORESEEDER_PLUGIN_FILE ),
			array( $this, 'add_plugin_action_links' )
		);
		add_filter( 'plugin_row_meta', array( $this, 'add_plugin_row_meta' ), 10, 2 );

		$this->init_mcp();
	}

	/**
	 * Initialise the MCP server integration.
	 *
	 * Boots the MCP_Server class which registers abilities via the
	 * WordPress Abilities API and exposes them as MCP tools through the
	 * mcp-adapter plugin.
	 *
	 * If neither the abilities-api nor the mcp-adapter is present, this
	 * method exits silently — no errors are thrown so existing functionality
	 * is completely unaffected.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function init_mcp(): void {
		// Both the abilities-api action hook and the mcp-adapter action hook
		// are checked at their respective fire-times; here we just wire up the
		// MCP_Server instance unconditionally so it can listen for those hooks.
		( new MCP_Server() )->init();
	}

	/**
	 * Add admin menu page
	 *
	 * Creates the main admin menu page for the StoreSeeder interface.
	 * Adds a top-level menu item in the WordPress admin sidebar with the plugin icon.
	 * Only registers the menu if dependencies are met (Fluent Cart is active).
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function add_admin_menu(): void {
		// Skip to register the admin menu.
		if ( ! $this->check_dependencies() ) {
			return;
		}

		add_menu_page(
			__( 'StoreSeeder', 'storeseeder' ),
			__( 'StoreSeeder', 'storeseeder' ),
			'manage_options',
			'storeseeder',
			array( $this, 'render_admin_page' ),
			$this->get_menu_icon(),
			30
		);
	}

	/**
	 * Add action links to the plugin's row on the Plugins screen
	 *
	 * Puts the two things people open the plugin for — the generators and the
	 * settings — one click from the Plugins list.
	 *
	 * @since 1.0.1
	 * @hooked plugin_action_links_{basename}
	 *
	 * @param array $links Existing action links.
	 *
	 * @return array Links with the plugin's own entries first.
	 */
	public function add_plugin_action_links( array $links ): array {
		$own = array(
			'generate' => sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'admin.php?page=storeseeder' ) ),
				esc_html__( 'Generate Data', 'storeseeder' )
			),
			'settings' => sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'admin.php?page=storeseeder#/settings' ) ),
				esc_html__( 'Settings', 'storeseeder' )
			),
		);

		return array_merge( $own, $links );
	}

	/**
	 * Add row meta links to the plugin's row on the Plugins screen
	 *
	 * @since 1.0.1
	 * @hooked plugin_row_meta
	 *
	 * @param array  $links Existing row meta links.
	 * @param string $file  Plugin file the row belongs to.
	 *
	 * @return array Row meta, extended for this plugin only.
	 */
	public function add_plugin_row_meta( array $links, string $file ): array {
		if ( plugin_basename( STORESEEDER_PLUGIN_FILE ) !== $file ) {
			return $links;
		}

		$links[] = sprintf(
			'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
			esc_url( 'https://github.com/mralaminahamed/storeseeder/blob/trunk/docs/README.md' ),
			esc_html__( 'Documentation', 'storeseeder' )
		);
		$links[] = sprintf(
			'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
			esc_url( 'https://github.com/mralaminahamed/storeseeder/blob/trunk/SUPPORT.md' ),
			esc_html__( 'Support', 'storeseeder' )
		);
		$links[] = sprintf(
			'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
			esc_url( 'https://github.com/mralaminahamed/storeseeder' ),
			esc_html__( 'GitHub', 'storeseeder' )
		);

		return $links;
	}

	/**
	 * Add a screen-scoped class to the admin body on the plugin's page
	 *
	 * Gives stylesheets something to hang off when they need to reach outside
	 * `.fp-root` — the WordPress footer or notice area, for instance.
	 *
	 * Unlike the reference implementation this does not strip the `folded`
	 * class: that is the user's own admin-menu preference, the app's layout does
	 * not depend on the menu width, and quietly overriding it would be rude.
	 *
	 * @since 1.0.1
	 * @hooked admin_body_class
	 *
	 * @param string $classes Space-separated list of admin body classes.
	 *
	 * @return string Body classes, with the plugin's own class on its screen.
	 */
	public function filter_admin_body_class( string $classes ): string {
		$screen = get_current_screen();

		if ( null === $screen || 'toplevel_page_storeseeder' !== $screen->id ) {
			return $classes;
		}

		return trim( $classes . ' storeseeder-admin-page' );
	}

	/**
	 * Clear other plugins' notices from the StoreSeeder screen
	 *
	 * The plugin renders a single-page app that owns the whole content area, and
	 * every unrelated notice on the site lands on top of it — setup wizards,
	 * review nags, upgrade prompts. They describe the site, not this screen, and
	 * they push the interface down the page.
	 *
	 * Only this screen is affected: every notice still shows everywhere else, so
	 * nothing is hidden from an administrator who has not navigated here. The
	 * plugin's own hint is re-registered afterwards, since it is about this screen
	 * and is dismissible.
	 *
	 * `in_admin_header` is the last hook that fires before WordPress prints the
	 * notice queue, so removing the callbacks here catches everything registered up
	 * to that point.
	 *
	 * @since 1.0.1
	 * @hooked in_admin_header
	 *
	 * @return void
	 */
	public function hide_foreign_admin_notices(): void {
		$screen = get_current_screen();

		if ( null === $screen || 'toplevel_page_storeseeder' !== $screen->id ) {
			return;
		}

		/**
		 * Filters whether unrelated admin notices are cleared on the plugin screen.
		 *
		 * Return false to let every notice through, e.g. while debugging another
		 * plugin's warning that only appears here.
		 *
		 * @since 1.0.1
		 *
		 * @param bool $hide Whether to clear notices. Default true.
		 */
		if ( ! apply_filters( 'storeseeder_hide_foreign_admin_notices', true ) ) {
			return;
		}

		remove_all_actions( 'admin_notices' );
		remove_all_actions( 'all_admin_notices' );
		remove_all_actions( 'user_admin_notices' );
		remove_all_actions( 'network_admin_notices' );

		// The MCP hint belongs to this screen and is dismissible, so it stays.
		add_action( 'admin_notices', array( $this, 'dependency_notice' ) );
	}

	/**
	 * Whether the mcp-adapter plugin is available
	 *
	 * The MCP integration registers abilities regardless, but they are only
	 * reachable by an AI client once mcp-adapter exposes its transport.
	 *
	 * @since 1.0.1
	 *
	 * @return bool True when mcp-adapter is loaded.
	 */
	public function is_mcp_adapter_active(): bool {
		return class_exists( '\WP\MCP\Transport\HttpTransport' );
	}

	/**
	 * Build the admin menu icon
	 *
	 * Returns the StoreSeeder mark — the cart and sprout from
	 * `.wordpress-org/icon.svg`, on the same 120-unit grid — as a base64 data URI.
	 * WordPress recognises that prefix and paints the SVG as the menu item's
	 * background image.
	 *
	 * Two variants, because a background image cannot inherit a colour and so
	 * cannot follow the admin colour scheme:
	 *
	 * - `inverse` (default): the mark in near-black on a white rounded tile, the
	 *   same corner radius as the source artwork. Reads as a distinct chip in the
	 *   dark admin menu and keeps its contrast at the 60% opacity WordPress
	 *   applies to inactive items.
	 * - `monochrome`: flat strokes in the default admin icon grey (#a7aaad), no
	 *   tile — the understated look core's own icons use.
	 *
	 * The gradient from the source artwork is dropped either way: at 20px a
	 * gradient panel muddies into a single tone.
	 *
	 * Keep the geometry in step with `.wordpress-org/icon.svg` and with
	 * `BrandIcon.tsx`, which carries the full-colour version for the admin UI.
	 *
	 * @since 1.0.1
	 *
	 * @return string Data URI suitable for the add_menu_page() $icon_url argument.
	 */
	private function get_menu_icon(): string {
		/**
		 * Filters which admin menu icon variant is used.
		 *
		 * @since 1.0.1
		 *
		 * @param string $variant Either 'inverse' (near-black mark on a white tile)
		 *                        or 'monochrome' (flat admin grey, no tile).
		 */
		$variant = apply_filters( 'storeseeder_menu_icon_variant', 'inverse' );

		$svg = 'monochrome' === $variant
			? $this->build_menu_icon_svg( '#a7aaad', null )
			: $this->build_menu_icon_svg( '#1d2327', '#ffffff' );

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- WordPress requires the menu icon SVG as a base64 data URI.
		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}

	/**
	 * Draw the menu icon artwork
	 *
	 * The cart transform and both sprout leaves are copied verbatim from
	 * `.wordpress-org/icon.svg`; only the colours differ between variants.
	 *
	 * @since 1.0.1
	 *
	 * @param string      $mark       Colour for the cart strokes and sprout.
	 * @param string|null $background Tile fill, or null to draw no tile.
	 *
	 * @return string SVG markup.
	 */
	private function build_menu_icon_svg( string $mark, ?string $background ): string {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120" viewBox="0 0 120 120">';

		if ( null !== $background ) {
			// rx matches the source artwork, so the tile keeps the brand silhouette.
			$svg .= '<rect width="120" height="120" rx="29" fill="' . $background . '"/>';
		}

		// Shopping cart: the store.
		$svg .= '<g transform="translate(11,26) scale(3.7)" fill="none" stroke="' . $mark . '" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">'
			. '<circle cx="8" cy="21" r="1.4"/>'
			. '<circle cx="19" cy="21" r="1.4"/>'
			. '<path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/>'
			. '</g>';

		// Sprout: the seed being planted.
		$svg .= '<line x1="92" y1="40" x2="92" y2="20" stroke="' . $mark . '" stroke-width="2.6" stroke-linecap="round"/>'
			. '<path d="M92,27 C86.5,21 78.5,21.5 76,27 C81.5,32 89.5,31.5 92,27 Z" fill="' . $mark . '"/>'
			. '<path d="M92,22 C96,14.5 104,13.5 108.5,18.5 C104.5,25 96.5,26 92,22 Z" fill="' . $mark . '"/>';

		return $svg . '</svg>';
	}

	/**
	 * Render the admin page
	 *
	 * Outputs the HTML container element where the React admin interface will be mounted.
	 * This method serves as the callback for the WordPress add_menu_page() function,
	 * providing the entry point for the React Router v7 application.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_admin_page(): void {
		echo '<div id="storeseeder-root"></div>';
	}

	/**
	 * Enqueue admin assets
	 *
	 * Loads and enqueues all necessary JavaScript, CSS, and localization assets
	 * for the admin interface. Only loads assets on the plugin's admin page to
	 * optimize performance. Includes WordPress admin color scheme integration
	 * and locale data for the React application.
	 *
	 * @since 1.0.0
	 * @hooked admin_enqueue_scripts
	 *
	 * @param string $hook The current admin page hook suffix.
	 *
	 * @return void
	 */
	public function enqueue_admin_assets( string $hook ): void {
		global $_wp_admin_css_colors;

		if ( 'toplevel_page_storeseeder' !== $hook ) {
			return;
		}

		$asset_file = STORESEEDER_PLUGIN_PATH . 'build/admin-app.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset_data = require $asset_file;
		$deps       = $asset_data['dependencies'];
		$version    = $asset_data['version'];

		$current_color = get_user_option( 'admin_color', get_current_user_id() ) ?? 'fresh';
		$color_scheme  = $_wp_admin_css_colors[ $current_color ] ?? $_wp_admin_css_colors['fresh'];

		// Extract colors for Tailwind CSS variables.
		$admin_colors = array(
			'primary'   => $color_scheme->colors[0] ?? '#2271b1',
			'secondary' => $color_scheme->colors[1] ?? '#135e96',
			'highlight' => $color_scheme->colors[2] ?? '#043f54',
			'accent'    => $color_scheme->colors[3] ?? '#0a4b78',
		);

		wp_enqueue_script(
			'storeseeder-admin',
			STORESEEDER_PLUGIN_URL . 'build/admin-app.js',
			$deps,
			$version,
			true
		);

		wp_enqueue_style(
			'storeseeder-admin',
			STORESEEDER_PLUGIN_URL . 'build/admin-app.css',
			array(),
			$version
		);

		// Add CSS variables for admin colors.
		$css_vars = sprintf(
			':root { --wp-admin-primary: %s; --wp-admin-secondary: %s; --wp-admin-highlight: %s; --wp-admin-accent: %s; }',
			esc_attr( $admin_colors['primary'] ),
			esc_attr( $admin_colors['secondary'] ),
			esc_attr( $admin_colors['highlight'] ),
			esc_attr( $admin_colors['accent'] )
		);
		wp_add_inline_style( 'storeseeder-admin', $css_vars );

		// Get locale information for frontend display.
		$wp_locale     = get_locale();
		$faker_locale  = $this->get_faker_locale( $wp_locale );
		$locale_labels = $this->get_locale_labels();

		wp_localize_script(
			'storeseeder-admin',
			'storeseederApi',
			array(
				'restUrl'     => rest_url( 'storeseeder/v1/' ),
				'restNonce'   => wp_create_nonce( 'wp_rest' ),
				'version'     => defined( 'STORESEEDER_VERSION' ) ? STORESEEDER_VERSION : '',
				'adminColors' => $admin_colors,
				'colorScheme' => $current_color,
				'locale'      => array(
					'wordpress'  => $wp_locale,
					'faker'      => $faker_locale,
					'label'      => $locale_labels[ $faker_locale ] ?? 'English (United States)',
					'allLocales' => $locale_labels,
				),
				// Inlined so the topbar renders its target on first paint. Fetching it
				// would flash "Auto" with no platform beside it, then correct itself.
				'platforms'   => $this->rest_platforms()->get_data(),
			)
		);

		wp_set_script_translations( 'storeseeder-admin', 'storeseeder' );
	}

	/**
	 * Register REST API routes
	 *
	 * Initializes and registers all REST API controllers for data generation.
	 * Creates endpoints for all specialized generators including core generators
	 * (Products, Customers, Orders, Coupons) and enhanced generators.
	 *
	 * @since 1.0.0
	 * @hooked rest_api_init
	 *
	 * @return void
	 */
	public function register_rest_routes(): void {
		// Skip to register the admin menu.
		if ( ! $this->check_dependencies() ) {
			return;
		}

		$controllers = array(
			// Core generators.
			new Product(),
			new Customer(),
			new Coupon(),

			// Enhanced generators.
			new Cart_Session(),
			new Shipping_Plan(),
			new Tax_Class(),
			new Order(),
			new Product_Variation(),
			new Transaction(),
			new Attribute(),
			new Refund(),
			new Log(),
			new Shipping_Class(),
			new Label(),
			new Order_Tax_Rate(),
			new Product_Download(),
			new Subscription(),
		);

		foreach ( $controllers as $controller ) {
			$controller->register_routes();
		}

		// Register the sample-data download endpoint.
		register_rest_route(
			'storeseeder/v1',
			'/download-sample',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'rest_sample_data_status' ),
					'permission_callback' => array( $this, 'rest_permission_check' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'rest_download_sample_data' ),
					'permission_callback' => array( $this, 'rest_permission_check' ),
					'args'                => array(
						'force' => array(
							'type'    => 'boolean',
							'default' => false,
						),
					),
				),
			)
		);

		// Register the sample-data consent endpoint.
		register_rest_route(
			'storeseeder/v1',
			'/download-sample/consent',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_set_sample_data_consent' ),
				'permission_callback' => array( $this, 'rest_permission_check' ),
				'args'                => array(
					'granted' => array(
						'type'     => 'boolean',
						'required' => true,
					),
				),
			)
		);

		// Register the platform endpoints.
		register_rest_route(
			'storeseeder/v1',
			'/platforms',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_platforms' ),
				'permission_callback' => array( $this, 'rest_permission_check' ),
			)
		);

		register_rest_route(
			'storeseeder/v1',
			'/platforms/target',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_set_target_platform' ),
				'permission_callback' => array( $this, 'rest_permission_check' ),
				'args'                => array(
					'platform' => array(
						// Not enumerated: drivers are extensible through the
						// storeseeder_platforms filter, so a fixed enum would reject a
						// valid third-party platform. Resolver validates instead.
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);
	}

	/**
	 * REST: the platforms this site could seed, and which one it will
	 *
	 * Capabilities are recomputed here on every request rather than cached, because
	 * activating a companion plugin has to change the answer immediately — WooCommerce
	 * gains subscriptions the moment WooCommerce Subscriptions is switched on.
	 *
	 * @since 1.1.0
	 *
	 * @return WP_REST_Response
	 */
	public function rest_platforms(): WP_REST_Response {
		$registry = $this->platforms();
		$resolver = $this->platform_resolver();
		$resolved = $resolver->resolve();

		$platforms = array();

		foreach ( $registry->all() as $platform ) {
			$supports = array();

			foreach ( $platform->supports() as $resource_type => $capability ) {
				$supports[ $resource_type ] = $capability->to_array();
			}

			$platforms[] = array(
				'id'       => $platform->id(),
				'label'    => $platform->label(),
				'active'   => $platform->is_active(),
				'version'  => $platform->version(),
				'supports' => $supports,
			);
		}

		return new WP_REST_Response(
			array(
				'platforms' => $platforms,
				'stored'    => $resolver->stored(),
				'resolved'  => is_wp_error( $resolved ) ? null : $resolved->id(),
				'ambiguous' => $resolver->is_ambiguous(),
			),
			200
		);
	}

	/**
	 * REST: choose the site-wide target platform
	 *
	 * @since 1.1.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function rest_set_target_platform( WP_REST_Request $request ) {
		$stored = $this->platform_resolver()->store( (string) $request->get_param( 'platform' ) );

		if ( is_wp_error( $stored ) ) {
			return $stored;
		}

		return $this->rest_platforms();
	}

	/**
	 * Plugin activation hook
	 *
	 * Handles plugin activation tasks: switching a site that is still on plain
	 * permalinks over to post-name permalinks, then flushing rewrite rules.
	 *
	 * @since 1.0.0
	 * @hooked register_activation_hook
	 *
	 * @return void
	 */
	public function activate_plugin(): void {
		// Runs before the flush below, since changing the structure is what
		// makes the rules stale in the first place.
		$this->maybe_set_postname_permalinks();

		// Flush rewrite rules.
		$this->flush_rewrite_rules();
	}

	/**
	 * Switch to post-name permalinks when the site has none set
	 *
	 * Generated products and orders are only reachable at readable URLs once the
	 * site is off plain permalinks, so a fresh install that never picked a
	 * structure gets `/%postname%/` here.
	 *
	 * Only an empty structure is treated as "not set". Plain permalinks store an
	 * empty `permalink_structure`, so any non-empty value means the site owner
	 * already chose day-and-name, a custom pattern, or anything else — those are
	 * left untouched.
	 *
	 * Filter `storeseeder_set_postname_permalinks` to false to opt out entirely,
	 * for sites that deliberately run on plain permalinks.
	 *
	 * @since 1.0.1
	 *
	 * @return bool True when the structure was written, false when it was left alone.
	 */
	private function maybe_set_postname_permalinks(): bool {
		// Non-empty means the site already has a structure — never overwrite it.
		if ( '' !== (string) get_option( 'permalink_structure', '' ) ) {
			return false;
		}

		/**
		 * Filters whether activation may set post-name permalinks.
		 *
		 * @since 1.0.1
		 *
		 * @param bool $enable Whether to set `/%postname%/` when no structure is set.
		 */
		if ( ! apply_filters( 'storeseeder_set_postname_permalinks', true ) ) {
			return false;
		}

		global $wp_rewrite;

		if ( $wp_rewrite instanceof WP_Rewrite ) {
			// Saves the option, rebuilds the rules, and updates .htaccess when
			// the file is writable.
			$wp_rewrite->set_permalink_structure( '/%postname%/' );

			return true;
		}

		// No $wp_rewrite yet: store the structure so the flush that follows —
		// and the next page load — pick it up.
		update_option( 'permalink_structure', '/%postname%/' );

		return true;
	}

	/**
	 * Ensure sample data is available
	 *
	 * Downloads and extracts sample data from the remote repository if not already present.
	 * This ensures the plugin has access to locale-specific sample data for generation.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if sample data is available, false on failure.
	 */
	public function ensure_sample_data(): bool {
		$sample_data_dir = $this->get_sample_data_directory();

		// Check if sample data already exists.
		if ( $this->sample_data_exists() ) {
			return true;
		}

		// Create sample data directory if it doesn't exist.
		if ( ! wp_mkdir_p( $sample_data_dir ) ) {
			return false;
		}

		// Download and extract sample data.
		return $this->download_sample_data();
	}

	/**
	 * Check if sample data exists
	 *
	 * Verifies that the required sample data directories and files are present.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if sample data exists, false otherwise.
	 */
	public function sample_data_exists(): bool {
		$sample_data_dir = $this->get_sample_data_directory();

		// Check for key directories that should exist under the Fluent Cart
		// integration folder.
		$required_dirs = array( 'products', 'customers' );

		foreach ( $required_dirs as $dir ) {
			if ( ! is_dir( $sample_data_dir . '/' . $dir ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get sample data directory path
	 *
	 * Returns the path where sample data should be stored.
	 *
	 * @since 1.0.0
	 *
	 * @return string Path to sample data directory.
	 */
	public function get_sample_data_directory(): string {
		$upload_dir = wp_upload_dir();
		return $upload_dir['basedir'] . '/storeseeder-sample-data-fluent-cart';
	}

	/**
	 * Get the sample-data consent decision (site-wide).
	 *
	 * @since 1.0.0
	 *
	 * @return string 'granted', 'declined', or '' when undecided.
	 */
	public function get_sample_data_consent(): string {
		$value = get_option( 'storeseeder_sample_data_consent', '' );

		return in_array( $value, array( 'granted', 'declined' ), true ) ? $value : '';
	}

	/**
	 * Record the sample-data consent decision.
	 *
	 * @since 1.0.0
	 *
	 * @param string $value Either 'granted' or 'declined'; other values are ignored.
	 *
	 * @return void
	 */
	public function set_sample_data_consent( string $value ): void {
		if ( ! in_array( $value, array( 'granted', 'declined' ), true ) ) {
			return;
		}

		update_option( 'storeseeder_sample_data_consent', $value, false );
	}

	/**
	 * REST callback: record the sample-data consent decision.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The REST request; `granted` selects the decision.
	 * @return WP_REST_Response Consent result payload.
	 */
	public function rest_set_sample_data_consent( WP_REST_Request $request ): WP_REST_Response {
		$granted = (bool) $request->get_param( 'granted' );
		$this->set_sample_data_consent( $granted ? 'granted' : 'declined' );

		$consent = $this->get_sample_data_consent();

		return new WP_REST_Response(
			array(
				'consent' => '' === $consent ? null : $consent,
			),
			200
		);
	}

	/**
	 * REST permission check.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True when the current user may manage the plugin.
	 */
	public function rest_permission_check(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * REST callback: report sample-data status.
	 *
	 * @since 1.0.0
	 *
	 * @return WP_REST_Response Status payload.
	 */
	public function rest_sample_data_status(): WP_REST_Response {
		$exists  = $this->sample_data_exists();
		$dir     = $this->get_sample_data_directory();
		$consent = $this->get_sample_data_consent();

		return new WP_REST_Response(
			array(
				'exists'      => $exists,
				'last_synced' => $exists && is_dir( $dir ) ? gmdate( 'c', (int) filemtime( $dir ) ) : null,
				'repo_url'    => 'https://github.com/mralaminahamed/storeseeder-sample-data-fluent-cart',
				'consent'     => '' === $consent ? null : $consent,
			),
			200
		);
	}

	/**
	 * REST callback: download / re-sync sample data.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The REST request; `force` re-downloads.
	 * @return WP_REST_Response|WP_Error Sync result payload.
	 */
	public function rest_download_sample_data( WP_REST_Request $request ) {
		$force = (bool) $request->get_param( 'force' );

		if ( $force ) {
			$dir = $this->get_sample_data_directory();
			global $wp_filesystem;
			if ( ! $wp_filesystem ) {
				require_once ABSPATH . '/wp-admin/includes/file.php';
				WP_Filesystem();
			}
			if ( $wp_filesystem ) {
				foreach ( array( 'products', 'customers' ) as $subdir ) {
					$wp_filesystem->delete( $dir . '/' . $subdir, true );
				}
			}
		}

		$result = $this->ensure_sample_data();
		if ( ! $result ) {
			return new WP_Error( 'download_failed', 'Failed to download sample data', array( 'status' => 500 ) );
		}

		// A successful download implies the administrator consented.
		$this->set_sample_data_consent( 'granted' );

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => 'Sample data synced successfully.',
			),
			200
		);
	}

	/**
	 * Download sample data from remote repository
	 *
	 * Downloads the sample data archive from GitHub and extracts it to the local directory.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True on success, false on failure.
	 */
	private function download_sample_data(): bool {
		$repo_owner = 'mralaminahamed';
		$repo_name  = 'storeseeder-sample-data-fluent-cart';
		$branch     = 'trunk';

		// GitHub API URL for downloading the repository as zip.
		$download_url = "https://github.com/{$repo_owner}/{$repo_name}/archive/refs/heads/{$branch}.zip";

		$sample_data_dir = $this->get_sample_data_directory();
		$temp_zip_file   = $sample_data_dir . '/sample-data-temp.zip';
		$extracted_dir   = $sample_data_dir . '/temp-extract';

		// Initialize WordPress filesystem.
		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . '/wp-admin/includes/file.php';
			WP_Filesystem();
		}

		// Download the zip file.
		$response = wp_remote_get(
			$download_url,
			array(
				'timeout' => 300, // 5 minutes timeout
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$zip_content = wp_remote_retrieve_body( $response );
		if ( empty( $zip_content ) ) {
			return false;
		}

		// Save zip file temporarily.
		if ( ! $wp_filesystem->put_contents( $temp_zip_file, $zip_content ) ) {
			return false;
		}

		// Extract the zip file.
		if ( ! $this->extract_zip( $temp_zip_file, $extracted_dir ) ) {
			$wp_filesystem->delete( $temp_zip_file );
			return false;
		}

		// Move extracted contents to the final location.
		$extracted_contents = $wp_filesystem->dirlist( $extracted_dir );
		if ( ! empty( $extracted_contents ) ) {
			$source_dir = $wp_filesystem->dirlist( $extracted_dir );
			$source_dir = $extracted_dir . '/' . key( $source_dir );
			$this->move_directory_contents( $source_dir, $sample_data_dir );
		}

		// Clean up temporary files.
		$wp_filesystem->delete( $temp_zip_file );
		$wp_filesystem->delete( $extracted_dir, true );

		return $this->sample_data_exists();
	}

	/**
	 * Extract ZIP file
	 *
	 * Extracts a ZIP file to the specified directory using WordPress filesystem.
	 *
	 * @since 1.0.0
	 *
	 * @param string $zip_file Path to the ZIP file.
	 * @param string $extract_to Directory to extract to.
	 *
	 * @return bool True on success, false on failure.
	 */
	private function extract_zip( string $zip_file, string $extract_to ): bool {
		if ( ! class_exists( 'ZipArchive' ) ) {
			$this->debug_log( 'StoreSeeder: ZipArchive class not available' );
			return false;
		}

		$zip = new ZipArchive();
		if ( $zip->open( $zip_file ) !== true ) {
			$this->debug_log( 'StoreSeeder: Failed to open zip file' );
			return false;
		}

		// Guard against zip-slip: reject any entry that escapes the target dir.
		for ( $i = 0; $i < $zip->numFiles; $i++ ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- ZipArchive built-in property.
			$entry_name = $zip->getNameIndex( $i );
			if (
				false === $entry_name
				|| '' === $entry_name
				|| 0 === strpos( $entry_name, '/' )
				|| 0 === strpos( $entry_name, '\\' )
				|| 1 === preg_match( '#(?:^|[/\\\\])\.\.(?:[/\\\\]|$)#', $entry_name )
			) {
				$this->debug_log( 'StoreSeeder: Unsafe path in zip archive: ' . ( false === $entry_name ? '(invalid)' : $entry_name ) );
				$zip->close();
				return false;
			}
		}

		if ( ! $zip->extractTo( $extract_to ) ) {
			$this->debug_log( 'StoreSeeder: Failed to extract zip file' );
			$zip->close();
			return false;
		}

		$zip->close();
		return true;
	}

	/**
	 * Write a message to the PHP error log, but only when debugging is enabled.
	 *
	 * @since 1.0.0
	 *
	 * @param string $message Message to log.
	 *
	 * @return void
	 */
	private function debug_log( string $message ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * Move directory contents
	 *
	 * Moves all contents from one directory to another.
	 *
	 * @since 1.0.0
	 *
	 * @param string $source_dir Source directory.
	 * @param string $dest_dir Destination directory.
	 *
	 * @return void
	 */
	private function move_directory_contents( string $source_dir, string $dest_dir ): void {
		global $wp_filesystem;

		$items = $wp_filesystem->dirlist( $source_dir );
		foreach ( $items as $item ) {
			$source_path = $source_dir . '/' . $item['name'];
			$dest_path   = $dest_dir . '/' . $item['name'];

			if ( 'd' === $item['type'] ) {
				// Directory.
				if ( ! $wp_filesystem->exists( $dest_path ) ) {
					$wp_filesystem->mkdir( $dest_path );
				}
				$this->move_directory_contents( $source_path, $dest_path );
			} else {
				// File.
				$wp_filesystem->move( $source_path, $dest_path );
			}
		}
	}

	/**
	 * Flush rewrite rules on activation and deactivation
	 *
	 * Ensures WordPress rewrite rules are properly flushed when the plugin is
	 * activated or deactivated. This maintains clean URL routing and prevents
	 * conflicts with custom endpoints.
	 *
	 * @since 1.0.0
	 * @hooked register_deactivation_hook
	 *
	 * @return void
	 */
	public function flush_rewrite_rules(): void {
		\flush_rewrite_rules();
	}

	/**
	 * Display dependency notice
	 *
	 * Shows an admin notice when required dependencies are not met.
	 * Specifically checks for Fluent Cart plugin activation and displays
	 * an error message if it's not available, preventing plugin functionality.
	 *
	 * @since 1.0.0
	 * @hooked admin_notices
	 *
	 * @return void
	 */
	public function dependency_notice(): void {
		if ( ! $this->check_dependencies() ) {
			// Naming the supported platforms rather than one of them: on a site with
			// none of them installed, "requires Fluent Cart" reads as an instruction
			// to install the wrong thing.
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %s: comma-separated list of supported e-commerce platforms. */
						__( 'StoreSeeder needs a supported e-commerce platform to seed. Install and activate one of: %s.', 'storeseeder' ),
						implode( ', ', $this->platforms()->labels() )
					)
				)
			);
		}

		$screen = get_current_screen();

		/*
		 * The MCP hint is confined to the plugin's own screen. mcp-adapter is
		 * optional, so nagging about it on every admin page would be noise —
		 * but without any hint at all, MCP silently does nothing and there is
		 * no way to find out why.
		 */
		if ( null !== $screen && 'toplevel_page_storeseeder' === $screen->id && ! $this->is_mcp_adapter_active() && ! $this->is_mcp_notice_dismissed() ) {
			printf(
				'<div class="notice notice-info is-dismissible storeseeder-mcp-notice"><p>%s</p></div>',
				sprintf(
					/* translators: 1: opening anchor tag, 2: closing anchor tag */
					esc_html__( 'StoreSeeder MCP server: the %1$smcp-adapter%2$s plugin is not installed. Install it to let AI clients (Claude Desktop, VS Code Copilot, and similar) run the generators.', 'storeseeder' ),
					'<a href="https://github.com/WordPress/mcp-adapter/releases" target="_blank" rel="noopener noreferrer">',
					'</a>'
				)
			);

			$this->print_mcp_notice_dismiss_script();
		}
	}

	/**
	 * Whether the current user has dismissed the MCP hint
	 *
	 * Stored per user rather than per site: one administrator closing a hint is
	 * not a decision for their colleagues.
	 *
	 * @since 1.0.1
	 *
	 * @return bool True when this user has dismissed it.
	 */
	private function is_mcp_notice_dismissed(): bool {
		$user_id = get_current_user_id();

		if ( 0 === $user_id ) {
			return false;
		}

		return (bool) get_user_meta( $user_id, self::MCP_NOTICE_DISMISSED_META, true );
	}

	/**
	 * Print the script that remembers a dismissal
	 *
	 * WordPress adds the notice's close button on DOM ready and then only hides
	 * the notice client-side, so without this the hint returns on the next page
	 * load. The listener is delegated from the document because the button does
	 * not exist yet when this runs.
	 *
	 * Inlined rather than enqueued so that dismissal keeps working even if the
	 * admin bundle is missing — the notice is most useful exactly when something
	 * about the install is off.
	 *
	 * @since 1.0.1
	 *
	 * @return void
	 */
	private function print_mcp_notice_dismiss_script(): void {
		$script = sprintf(
			'document.addEventListener( "click", function ( event ) {
	if ( ! event.target.classList.contains( "notice-dismiss" ) ) {
		return;
	}
	if ( ! event.target.closest( ".storeseeder-mcp-notice" ) ) {
		return;
	}
	var body = new FormData();
	body.append( "action", %s );
	body.append( "nonce", %s );
	fetch( %s, { method: "POST", credentials: "same-origin", body: body } );
} );',
			wp_json_encode( self::MCP_NOTICE_DISMISS_ACTION ),
			wp_json_encode( wp_create_nonce( self::MCP_NOTICE_DISMISS_ACTION ) ),
			wp_json_encode( admin_url( 'admin-ajax.php' ) )
		);

		wp_print_inline_script_tag( $script );
	}

	/**
	 * Record that the current user dismissed the MCP hint
	 *
	 * @since 1.0.1
	 * @hooked wp_ajax_storeseeder_dismiss_mcp_notice
	 *
	 * @return void
	 */
	public function ajax_dismiss_mcp_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do that.', 'storeseeder' ) ), 403 );
		}

		check_ajax_referer( self::MCP_NOTICE_DISMISS_ACTION, 'nonce' );

		update_user_meta( get_current_user_id(), self::MCP_NOTICE_DISMISSED_META, 1 );

		wp_send_json_success( array( 'dismissed' => true ) );
	}

	/**
	 * Check if Fluent Cart plugin is active
	 *
	 * @since      1.0.0
	 * @deprecated 1.1.0 Fluent Cart is one driver among several. Ask the driver, or
	 *                   ask the registry whether anything at all is seedable.
	 *
	 * @return bool True if Fluent Cart is active, false otherwise.
	 */
	public function is_fluent_cart_active(): bool {
		$platform = $this->platforms()->get( 'fluent-cart' );

		return null !== $platform && $platform->is_active();
	}

	/**
	 * The platform driver registry
	 *
	 * @since 1.1.0
	 *
	 * @return Registry
	 */
	public function platforms(): Registry {
		return Registry::instance();
	}

	/**
	 * Resolver for the target platform
	 *
	 * @since 1.1.0
	 *
	 * @return Resolver
	 */
	public function platform_resolver(): Resolver {
		return new Resolver( $this->platforms() );
	}

	/**
	 * Check plugin dependencies
	 *
	 * True when at least one supported e-commerce platform is active. It is
	 * deliberately not "Fluent Cart is active" any more: gating the admin menu and the
	 * REST routes on one platform is what made every other one unreachable.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if all dependencies are met, false otherwise.
	 */
	public function check_dependencies(): bool {
		return $this->platforms()->has_active();
	}

	/**
	 * Prevent cloning of the plugin instance
	 *
	 * Prevents cloning of the singleton instance to maintain the singleton pattern.
	 * Throws an exception if attempted, ensuring only one plugin instance exists.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function __clone() {
	}

	/**
	 * Prevent unserialization of the plugin instance
	 *
	 * Prevents unserialization of the singleton instance to maintain the singleton pattern.
	 * Throws a RuntimeException if attempted, ensuring plugin integrity and preventing
	 * multiple instances through deserialization attacks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 * @throws RuntimeException When attempting to unserialize the singleton instance.
	 */
	public function __wakeup() {
		throw new RuntimeException( 'Cannot unserialize singleton' );
	}

	/**
	 * Get FakerPHP locale for display purposes
	 *
	 * Converts WordPress locale codes to FakerPHP compatible locale codes for
	 * the admin interface display. Applies filters for customization and provides
	 * fallback logic for unsupported locales. Used by the React frontend to
	 * display current locale information.
	 *
	 * @since 1.0.0
	 *
	 * @param string $locale WordPress locale code (e.g., 'en_US', 'fr_FR').
	 *
	 * @return string FakerPHP compatible locale code (defaults to 'en_US').
	 */
	public function get_faker_locale( string $locale ): string {
		/**
		 * Filters the locale used for test data generation.
		 *
		 * Allows developers to override the default locale used by StoreSeeder
		 * for generating test data. Useful for generating data in specific languages
		 * or regional formats regardless of the site's locale setting.
		 *
		 * @since 1.0.0
		 * @hook  storeseeder_locale
		 *
		 * @param string $locale The current WordPress locale code (e.g., 'en_US').
		 */
		$custom_locale = apply_filters( 'storeseeder_locale', $locale );

		// Get supported locales.
		$supported_locales = array_keys( $this->get_locale_labels() );

		// Direct match.
		if ( in_array( $custom_locale, $supported_locales, true ) ) {
			return $custom_locale;
		}

		// Try language fallback.
		$language = substr( $custom_locale, 0, 2 );
		foreach ( $supported_locales as $locale_code ) {
			if ( strpos( $locale_code, $language . '_' ) === 0 ) {
				return $locale_code;
			}
		}

		// Default fallback.
		return 'en_US';
	}

	/**
	 * Get human-readable labels for all supported FakerPHP locales
	 *
	 * Returns a comprehensive array of supported FakerPHP locales with their
	 * human-readable labels for use in the admin interface. Includes major
	 * world languages and regions for international data generation support.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string> Associative array mapping locale codes to display labels.
	 */
	public function get_locale_labels(): array {
		return array(
			'ar_SA'      => 'Arabic (Saudi Arabia)',
			'at_AT'      => 'Austrian German',
			'bg_BG'      => 'Bulgarian (Bulgaria)',
			'bn_BD'      => 'Bangla (Bangladesh)',
			'cs_CZ'      => 'Czech (Czech Republic)',
			'da_DK'      => 'Danish (Denmark)',
			'de_AT'      => 'German (Austria)',
			'de_CH'      => 'German (Switzerland)',
			'de_DE'      => 'German (Germany)',
			'el_CY'      => 'Greek (Cyprus)',
			'el_GR'      => 'Greek (Greece)',
			'en_AU'      => 'English (Australia)',
			'en_GB'      => 'English (Great Britain)',
			'en_HK'      => 'English (Hong Kong)',
			'en_IN'      => 'English (India)',
			'en_NG'      => 'English (Nigeria)',
			'en_NZ'      => 'English (New Zealand)',
			'en_PH'      => 'English (Philippines)',
			'en_SG'      => 'English (Singapore)',
			'en_UG'      => 'English (Uganda)',
			'en_US'      => 'English (United States)',
			'en_ZA'      => 'English (South Africa)',
			'es_AR'      => 'Spanish (Argentina)',
			'es_ES'      => 'Spanish (Spain)',
			'es_PE'      => 'Spanish (Peru)',
			'es_VE'      => 'Spanish (Venezuela)',
			'et_EE'      => 'Estonian (Estonia)',
			'fa_IR'      => 'Persian (Iran)',
			'fi_FI'      => 'Finnish (Finland)',
			'fr_BE'      => 'French (Belgium)',
			'fr_CA'      => 'French (Canada)',
			'fr_CH'      => 'French (Switzerland)',
			'fr_FR'      => 'French (France)',
			'he_IL'      => 'Hebrew (Israel)',
			'hr_HR'      => 'Croatian (Croatia)',
			'hu_HU'      => 'Hungarian (Hungary)',
			'hy_AM'      => 'Armenian (Armenia)',
			'id_ID'      => 'Indonesian (Indonesia)',
			'is_IS'      => 'Icelandic (Iceland)',
			'it_CH'      => 'Italian (Switzerland)',
			'it_IT'      => 'Italian (Italy)',
			'ja_JP'      => 'Japanese (Japan)',
			'ka_GE'      => 'Georgian (Georgia)',
			'kk_KZ'      => 'Kazakh (Kazakhstan)',
			'ko_KR'      => 'Korean (South Korea)',
			'lt_LT'      => 'Lithuanian (Lithuania)',
			'lv_LV'      => 'Latvian (Latvia)',
			'me_ME'      => 'Montenegrin (Montenegro)',
			'mn_MN'      => 'Mongolian (Mongolia)',
			'ms_MY'      => 'Malay (Malaysia)',
			'nb_NO'      => 'Norwegian Bokmål (Norway)',
			'ne_NP'      => 'Nepali (Nepal)',
			'nl_BE'      => 'Dutch (Belgium)',
			'nl_NL'      => 'Dutch (Netherlands)',
			'pl_PL'      => 'Polish (Poland)',
			'pt_AO'      => 'Portuguese (Angola)',
			'pt_BR'      => 'Portuguese (Brazil)',
			'pt_PT'      => 'Portuguese (Portugal)',
			'ro_MD'      => 'Romanian (Moldova)',
			'ro_RO'      => 'Romanian (Romania)',
			'ru_RU'      => 'Russian (Russia)',
			'sk_SK'      => 'Slovak (Slovakia)',
			'sl_SI'      => 'Slovenian (Slovenia)',
			'sr_Cyrl_RS' => 'Serbian Cyrillic (Serbia)',
			'sr_Latn_RS' => 'Serbian Latin (Serbia)',
			'sr_RS'      => 'Serbian (Serbia)',
			'sv_SE'      => 'Swedish (Sweden)',
			'th_TH'      => 'Thai (Thailand)',
			'tr_TR'      => 'Turkish (Turkey)',
			'uk_UA'      => 'Ukrainian (Ukraine)',
			'vi_VN'      => 'Vietnamese (Vietnam)',
			'zh_CN'      => 'Chinese (China)',
			'zh_TW'      => 'Chinese (Taiwan)',
		);
	}
}
