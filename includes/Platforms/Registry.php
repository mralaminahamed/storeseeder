<?php
/**
 * Platform registry
 *
 * Holds the drivers StoreSeeder knows about. The shipped ones are registered here; the
 * `storeseeder_platforms` filter is the supported way for anything else to join, and is
 * deliberately the only extension point needed to add a whole platform.
 *
 * @since   1.1.0
 * @package StoreSeeder\Platforms
 */

namespace StoreSeeder\Platforms;

use StoreSeeder\Platforms\Drivers\Fluent_Cart\Platform as Fluent_Cart;

/**
 * Registry of available platform drivers.
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
	 * Drivers keyed by id, or null before the filter has run.
	 *
	 * Memoised for the request: the set of installed plugins cannot change mid-request,
	 * and this is consulted by the menu gate, the REST gate and every generation call.
	 * Capabilities are *not* memoised — those are recomputed per call, because they
	 * depend on which companion plugins are active.
	 *
	 * @since 1.1.0
	 * @var array<string, Platform_Interface>|null
	 */
	private $platforms = null;

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
	 * Discard the shared instance and its memoised driver list.
	 *
	 * For tests, which add drivers through the filter between cases.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$instance = null;
	}

	/**
	 * Every registered driver, active or not.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, Platform_Interface> Keyed by platform id.
	 */
	public function all(): array {
		if ( null !== $this->platforms ) {
			return $this->platforms;
		}

		/**
		 * Filters the registered platform drivers.
		 *
		 * The extension point for adding a platform. Append an object implementing
		 * StoreSeeder\Platforms\Platform_Interface — extending
		 * StoreSeeder\Platforms\Platform_Driver is the shortest route — and every
		 * generator, the REST API and the admin pick it up with no further wiring.
		 *
		 * @since 1.1.0
		 *
		 * @param array<int, mixed> $platforms Drivers, in preference order. Each entry
		 *                                     is expected to implement Platform_Interface;
		 *                                     anything else is discarded rather than
		 *                                     trusted, since a filter can return
		 *                                     whatever it likes.
		 */
		$platforms = apply_filters( 'storeseeder_platforms', array( new Fluent_Cart() ) );

		$this->platforms = array();

		foreach ( $platforms as $platform ) {
			// A malformed entry from a third-party filter must not take down the admin.
			if ( ! $platform instanceof Platform_Interface ) {
				continue;
			}

			$this->platforms[ $platform->id() ] = $platform;
		}

		return $this->platforms;
	}

	/**
	 * One driver by id.
	 *
	 * @since 1.1.0
	 *
	 * @param string $id Platform id.
	 *
	 * @return Platform_Interface|null Null when no driver claims that id.
	 */
	public function get( string $id ): ?Platform_Interface {
		$platforms = $this->all();

		return $platforms[ $id ] ?? null;
	}

	/**
	 * Drivers whose platform is installed and loaded.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, Platform_Interface> Keyed by platform id.
	 */
	public function active(): array {
		return array_filter(
			$this->all(),
			static function ( Platform_Interface $platform ): bool {
				return $platform->is_active();
			}
		);
	}

	/**
	 * Whether anything at all can be seeded.
	 *
	 * Replaces the old single-platform dependency check: the admin menu and the REST
	 * routes exist when at least one supported platform is present.
	 *
	 * @since 1.1.0
	 *
	 * @return bool
	 */
	public function has_active(): bool {
		return array() !== $this->active();
	}

	/**
	 * Display labels of every registered driver.
	 *
	 * Used by the dependency notice, which has to name the platforms a site could
	 * install rather than naming one.
	 *
	 * @since 1.1.0
	 *
	 * @return array<int, string>
	 */
	public function labels(): array {
		return array_values(
			array_map(
				static function ( Platform_Interface $platform ): string {
					return $platform->label();
				},
				$this->all()
			)
		);
	}
}
