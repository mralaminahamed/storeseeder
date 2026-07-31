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
	 * Site option holding the roles an administrator has additionally allowed.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	const ROLES_OPTION = 'storeseeder_allowed_roles';

	/**
	 * The role that can always use StoreSeeder, and the only one that may grant others.
	 *
	 * Kept out of the stored list rather than pre-checked in it: an administrator who
	 * could clear their own access would be one confirmation dialog away from a site
	 * where nobody can reach the plugin or undo the setting.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	const ADMIN_ROLE = 'administrator';

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
	 * Roles an administrator has allowed, beyond whoever holds the capability.
	 *
	 * Filtered against the roles the site actually defines, so a role deleted after being
	 * allowed stops granting anything — and so a stored value cannot smuggle in a role
	 * name that WordPress does not know.
	 *
	 * @since 1.1.0
	 *
	 * @return array<int, string> Role slugs, never including the administrator role.
	 */
	public static function allowed_roles(): array {
		$stored = get_option( self::ROLES_OPTION, array() );

		if ( ! is_array( $stored ) ) {
			return array();
		}

		$known = array_keys( self::assignable_roles() );

		return array_values( array_intersect( array_map( 'strval', $stored ), $known ) );
	}

	/**
	 * Record which roles may use StoreSeeder.
	 *
	 * Callers must check that the current user may *change* this — holding the plugin's
	 * own capability is not enough, or an allowed editor could widen access further.
	 *
	 * @since 1.1.0
	 *
	 * @param array<int, mixed> $roles Role slugs to allow.
	 *
	 * @return array<int, string> The roles as stored, after validation.
	 */
	public static function set_allowed_roles( array $roles ): array {
		$known = array_keys( self::assignable_roles() );
		$clean = array_values(
			array_unique(
				array_intersect( array_map( 'strval', $roles ), $known )
			)
		);

		update_option( self::ROLES_OPTION, $clean, false );

		return $clean;
	}

	/**
	 * The roles an administrator may choose between, slug => display name.
	 *
	 * Excludes the administrator role, which always has access.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, string>
	 */
	public static function assignable_roles(): array {
		$roles = array();

		foreach ( wp_roles()->get_names() as $slug => $name ) {
			if ( self::ADMIN_ROLE === $slug ) {
				continue;
			}

			$roles[ (string) $slug ] = translate_user_role( (string) $name );
		}

		return $roles;
	}

	/**
	 * Whether the current user holds one of the allowed roles.
	 *
	 * Roles rather than a capability, because this is the setting a site owner reasons
	 * about: "editors may generate data" is a sentence they can check, and
	 * `edit_others_posts` is not.
	 *
	 * @since 1.1.0
	 *
	 * @return bool
	 */
	private static function current_user_has_allowed_role(): bool {
		$allowed = self::allowed_roles();

		if ( array() === $allowed ) {
			return false;
		}

		$user = wp_get_current_user();

		// A logged-out visitor still gets a WP_User back, with ID 0 and no roles.
		if ( 0 === $user->ID ) {
			return false;
		}

		return array() !== array_intersect( (array) $user->roles, $allowed );
	}

	/**
	 * Whether the current user may change who has access.
	 *
	 * Deliberately not the plugin's own capability: a role granted through the setting
	 * must not be able to grant more roles, or the setting would be self-escalating.
	 *
	 * @since 1.1.0
	 *
	 * @return bool
	 */
	public static function current_user_can_manage(): bool {
		return current_user_can( self::DEFAULT_CAPABILITY );
	}

	/**
	 * Whether the current user may use StoreSeeder.
	 *
	 * Two ways in: the capability — `manage_options` unless filtered — or one of the
	 * roles an administrator allowed on the Settings page.
	 *
	 * @since 1.1.0
	 *
	 * @return bool
	 */
	public static function current_user_can(): bool {
		return current_user_can( self::capability() ) || self::current_user_has_allowed_role();
	}
}
