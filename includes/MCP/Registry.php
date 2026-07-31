<?php
/**
 * MCP ability registry
 *
 * Holds the abilities StoreSeeder exposes to AI clients. The shipped ones are listed
 * here; the `storeseeder_mcp_abilities` filter is the supported way for anything else
 * to join.
 *
 * Shaped like StoreSeeder\Rest\Registry and StoreSeeder\Platforms\Registry on purpose.
 * All three answer "what is available?", and there was no reason for the three to be
 * discovered three different ways.
 *
 * @since   1.1.0
 * @package StoreSeeder\MCP
 */

namespace StoreSeeder\MCP;

defined( 'ABSPATH' ) || exit;

/**
 * Registry of MCP abilities.
 *
 * @since 1.1.0
 */
final class Registry {
	/**
	 * Shared instance.
	 *
	 * @since 1.1.0
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Ability class names keyed by ability id, or null before the filter has run.
	 *
	 * @since 1.1.0
	 * @var array<string, class-string<Ability>>|null
	 */
	private $abilities = null;

	/**
	 * Shared instance.
	 *
	 * @since 1.1.0
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Discard the shared instance and its memoised abilities.
	 *
	 * For tests, which add abilities through the filter between cases.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$instance = null;
	}

	/**
	 * Ability classes shipped with the plugin.
	 *
	 * @since 1.1.0
	 *
	 * @return array<int, class-string<Ability>>
	 */
	private function default_classes(): array {
		return array(
			// Core.
			Abilities\Generate_Products::class,
			Abilities\Generate_Customers::class,
			Abilities\Generate_Orders::class,
			Abilities\Generate_Coupons::class,

			// Advanced.
			Abilities\Generate_Product_Variations::class,
			Abilities\Generate_Shipping_Plans::class,
			Abilities\Generate_Tax_Classes::class,
			Abilities\Generate_Transactions::class,
			Abilities\Generate_Cart_Sessions::class,
			Abilities\Generate_Attributes::class,
			Abilities\Generate_Brands::class,
			Abilities\Generate_Refunds::class,
			Abilities\Generate_Logs::class,
			Abilities\Generate_Shipping_Classes::class,
			Abilities\Generate_Labels::class,
			Abilities\Generate_Order_Tax_Rates::class,
			Abilities\Generate_Product_Downloads::class,
			Abilities\Generate_Subscriptions::class,
			Abilities\Generate_Licenses::class,
		);
	}

	/**
	 * Every registered ability class, keyed by ability id.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, class-string<Ability>>
	 */
	public function all(): array {
		if ( null !== $this->abilities ) {
			return $this->abilities;
		}

		/**
		 * Filters the MCP abilities StoreSeeder registers.
		 *
		 * Append a class name extending StoreSeeder\MCP\Ability to expose a new tool,
		 * or remove one to withdraw it.
		 *
		 * @since 1.1.0
		 *
		 * @param array<int, mixed> $abilities Ability class names. Each entry is
		 *                                     expected to extend Ability; anything else
		 *                                     is discarded rather than trusted, since a
		 *                                     filter can return whatever it likes.
		 */
		$abilities = apply_filters( 'storeseeder_mcp_abilities', $this->default_classes() );

		$this->abilities = array();

		foreach ( $abilities as $ability ) {
			// A malformed entry must not take down ability registration, which several
			// other abilities depend on.
			if ( ! is_string( $ability ) || ! class_exists( $ability ) ) {
				continue;
			}

			if ( ! is_subclass_of( $ability, Ability::class ) ) {
				continue;
			}

			// An ability with no REST base dispatches to /storeseeder/v1//generate,
			// which no controller serves. Registering it would give an MCP client a
			// tool that always fails.
			if ( '' === $ability::REST_BASE ) {
				continue;
			}

			$this->abilities[ $ability::ability_id() ] = $ability;
		}

		return $this->abilities;
	}

	/**
	 * Ability ids, in registration order.
	 *
	 * These are the tool ids the MCP server exposes.
	 *
	 * @since 1.1.0
	 *
	 * @return array<int, string>
	 */
	public function ids(): array {
		return array_keys( $this->all() );
	}

	/**
	 * Every ability's definition, keyed by ability id.
	 *
	 * Each definition comes from the ability itself rather than from a central table,
	 * so an ability's input schema sits beside the build_payload() that consumes it.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function definitions(): array {
		$definitions = array();

		foreach ( $this->all() as $id => $ability ) {
			$definitions[ $id ] = $ability::definition();
		}

		return $definitions;
	}

	/**
	 * Every ability's read-only definition, keyed by its preview id.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function preview_definitions(): array {
		$definitions = array();

		foreach ( $this->all() as $ability ) {
			$definitions[ $ability::preview_ability_id() ] = $ability::preview_definition();
		}

		return $definitions;
	}

	/**
	 * The tools this site actually exposes, keyed by tool id.
	 *
	 * Two tools per generator — one that shows what a run would create and one that
	 * creates it — and the settings decide which of the two kinds are offered. This is the
	 * gate: a tool that is not registered cannot be called, which is a stronger promise
	 * than a tool that checks a flag once it has been.
	 *
	 * Preview comes first per resource, so a client listing tools meets the harmless one
	 * before the one that writes.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function tools(): array {
		if ( ! Settings::enabled() ) {
			return array();
		}

		$preview  = Settings::preview_enabled();
		$generate = Settings::generate_enabled();
		$tools    = array();

		foreach ( $this->all() as $id => $ability ) {
			if ( $preview ) {
				$tools[ $ability::preview_ability_id() ] = $ability::preview_definition();
			}

			if ( $generate ) {
				$tools[ $id ] = $ability::definition();
			}
		}

		return $tools;
	}

	/**
	 * The ids of the tools this site exposes.
	 *
	 * @since 1.1.0
	 *
	 * @return array<int, string>
	 */
	public function tool_ids(): array {
		return array_keys( $this->tools() );
	}
}
