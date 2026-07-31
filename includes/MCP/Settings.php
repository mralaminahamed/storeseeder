<?php
/**
 * What an AI client is allowed to do through MCP
 *
 * Three switches, in the order an administrator thinks about them: is the AI surface on at
 * all, may an agent look, may an agent write. They are separate because the risks are
 * separate — a preview reads nothing from the store and creates nothing, while a generate
 * run inserts rows that someone then has to clear out.
 *
 * @since   1.1.0
 * @package StoreSeeder\MCP
 */

namespace StoreSeeder\MCP;

defined( 'ABSPATH' ) || exit;

/**
 * The MCP toggles.
 *
 * @since 1.1.0
 */
final class Settings {
	/**
	 * Whether StoreSeeder exposes an MCP server at all.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	const ENABLED_OPTION = 'storeseeder_mcp_enabled';

	/**
	 * Whether the read-only preview tools are exposed.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	const PREVIEW_OPTION = 'storeseeder_mcp_preview_enabled';

	/**
	 * Whether the generate tools — the ones that write rows — are exposed.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	const GENERATE_OPTION = 'storeseeder_mcp_generate_enabled';

	/**
	 * The toggles, keyed as the REST route and the admin use them.
	 *
	 * All three default to on, which is what the plugin did before they existed: an
	 * upgrade must not silently withdraw tools an agent is already calling. Turning any
	 * of them off is a decision, and this is where it is recorded.
	 *
	 * @since 1.1.0
	 *
	 * @return array{enabled: bool, preview: bool, generate: bool}
	 */
	public static function all(): array {
		$settings = array(
			'enabled'  => self::option( self::ENABLED_OPTION ),
			'preview'  => self::option( self::PREVIEW_OPTION ),
			'generate' => self::option( self::GENERATE_OPTION ),
		);

		/**
		 * Filters the MCP toggles.
		 *
		 * The seam for deciding this in code rather than in the database — a staging
		 * site that allows generation and a production site that allows neither can then
		 * share one option table, and a `wp-config.php` constant can override an
		 * administrator's choice where policy says it should.
		 *
		 * Every gate reads through here, so returning `generate => false` withdraws the
		 * eighteen generate tools wherever they are registered.
		 *
		 * @since 1.1.0
		 * @hook  storeseeder_mcp_settings
		 *
		 * @param array{enabled: bool, preview: bool, generate: bool} $settings The stored toggles.
		 */
		$filtered = (array) apply_filters( 'storeseeder_mcp_settings', $settings );

		foreach ( array_keys( $settings ) as $key ) {
			// A filter that drops a key must not turn a documented boolean into null. The
			// analyser reads the declared shape and calls the isset() redundant; it is
			// redundant only for filters that behave, which is not a guarantee.
			$settings[ $key ] = isset( $filtered[ $key ] ) && wp_validate_boolean( $filtered[ $key ] ); // @phpstan-ignore isset.offset
		}

		return $settings;
	}

	/**
	 * Whether StoreSeeder exposes an MCP server.
	 *
	 * @since 1.1.0
	 *
	 * @return bool
	 */
	public static function enabled(): bool {
		return self::all()['enabled'];
	}

	/**
	 * Whether the read-only preview tools are exposed.
	 *
	 * @since 1.1.0
	 *
	 * @return bool
	 */
	public static function preview_enabled(): bool {
		$settings = self::all();

		// Gated by the master switch as well as its own, so the switch means what it says
		// rather than only meaning it in the one place that happens to check both.
		return $settings['enabled'] && $settings['preview'];
	}

	/**
	 * Whether the generate tools are exposed.
	 *
	 * @since 1.1.0
	 *
	 * @return bool
	 */
	public static function generate_enabled(): bool {
		$settings = self::all();

		return $settings['enabled'] && $settings['generate'];
	}

	/**
	 * Store a change to one or more toggles.
	 *
	 * Partial by design: the admin saves the switch that moved, so an absent key means
	 * "leave it as it was" rather than "turn it off".
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $changes Any of `enabled`, `preview`, `generate`.
	 *
	 * @return array{enabled: bool, preview: bool, generate: bool} The toggles after the change.
	 */
	public static function update( array $changes ): array {
		$options = array(
			'enabled'  => self::ENABLED_OPTION,
			'preview'  => self::PREVIEW_OPTION,
			'generate' => self::GENERATE_OPTION,
		);

		foreach ( $options as $key => $option ) {
			if ( ! array_key_exists( $key, $changes ) ) {
				continue;
			}

			update_option( $option, wp_validate_boolean( $changes[ $key ] ) ? '1' : '', true );
		}

		return self::all();
	}

	/**
	 * Read one toggle from the database.
	 *
	 * Stored as `'1'` and `''` rather than as booleans, because `update_option( …, false )`
	 * writes an empty string anyway and reading it back as a boolean is then the caller's
	 * problem — done in one place here instead of three.
	 *
	 * @since 1.1.0
	 *
	 * @param string $option Option name.
	 *
	 * @return bool
	 */
	private static function option( string $option ): bool {
		return wp_validate_boolean( get_option( $option, '1' ) );
	}
}
