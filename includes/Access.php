<?php
/**
 * Who may use StoreSeeder
 *
 * Generation is gated on one capability, checked from four places: the admin menu, the
 * REST endpoints, the MCP abilities and the AJAX notice handler. They have to agree — a
 * site that grants a shop manager the REST routes but not the admin page has a broken
 * plugin, not a configured one — so the capability is decided here and nowhere else.
 *
 * This sits at the root of includes/ rather than inside a layer directory on purpose.
 * Access control spans all four layers; placing it in Rest/ would make the admin menu
 * depend on the REST layer to answer a question that has nothing to do with REST.
 *
 * @since   1.1.0
 * @package StoreSeeder
 */

namespace StoreSeeder;

defined( 'ABSPATH' ) || exit;

/**
 * Capability gate.
 *
 * @since 1.1.0
 */
final class Access {
	/**
	 * Capability required when nothing overrides it.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	const DEFAULT_CAPABILITY = 'manage_options';

	/**
	 * The capability required to generate data and reach the admin page.
	 *
	 * @since 1.1.0
	 *
	 * @return string A non-empty capability name.
	 */
	public static function capability(): string {
		/**
		 * Filters the capability required to use StoreSeeder.
		 *
		 * Governs the admin menu, every REST endpoint, every MCP ability and the AJAX
		 * handlers at once, so widening access cannot leave one surface behind. Test
		 * data is written straight to store tables, so widen it deliberately —
		 * `edit_shop_orders` for a shop manager is a reasonable choice, `read` is not.
		 *
		 * @since 1.1.0
		 * @hook  storeseeder_capability
		 *
		 * @param mixed $capability Capability name, a string when unfiltered. Typed loosely
		 *                          because a filter may return anything, and the check below
		 *                          rejects a value current_user_can() cannot use.
		 */
		$capability = apply_filters( 'storeseeder_capability', self::DEFAULT_CAPABILITY );

		// An empty or non-string return would be passed to current_user_can(), which
		// treats an unknown capability as granted for a super admin and denied for
		// everyone else — unpredictable either way. Fail back to the default instead.
		if ( ! is_string( $capability ) || '' === $capability ) {
			return self::DEFAULT_CAPABILITY;
		}

		return $capability;
	}

	/**
	 * Whether the current user may use StoreSeeder.
	 *
	 * @since 1.1.0
	 *
	 * @return bool
	 */
	public static function current_user_can(): bool {
		return current_user_can( self::capability() );
	}
}
