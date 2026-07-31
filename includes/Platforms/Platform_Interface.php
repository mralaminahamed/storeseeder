<?php
/**
 * Platform contract
 *
 * A platform is one e-commerce plugin StoreSeeder can seed. Implementations live in
 * includes/Platforms/, but nothing requires that — a driver registered through the
 * `storeseeder_platforms` filter from a third-party plugin is equally valid, and that
 * is the point of the interface being this small.
 *
 * @since   1.1.0
 * @package StoreSeeder\Platforms
 */

namespace StoreSeeder\Platforms;

use StoreSeeder\Platforms\Writer;

/**
 * One seedable e-commerce platform.
 *
 * @since 1.1.0
 */
interface Platform_Interface {
	/**
	 * Stable machine identifier.
	 *
	 * Stored in the storeseeder_target_platform option and sent in REST requests, so
	 * changing it for an existing driver silently resets sites to auto.
	 *
	 * @since 1.1.0
	 *
	 * @return string Lowercase, hyphenated, e.g. 'fluent-cart'.
	 */
	public function id(): string;

	/**
	 * Display name, translated.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public function label(): string;

	/**
	 * Whether the platform is installed and loaded right now.
	 *
	 * Called on most requests, so it must stay cheap — a constant or class_exists
	 * check, not a database query.
	 *
	 * @since 1.1.0
	 *
	 * @return bool
	 */
	public function is_active(): bool;

	/**
	 * The platform's own version, when it exposes one.
	 *
	 * @since 1.1.0
	 *
	 * @return string|null Null when inactive or when the platform publishes no version.
	 */
	public function version(): ?string;

	/**
	 * Which resources this platform can generate, and why not when it cannot.
	 *
	 * Computed per call rather than declared, because support is conditional: a
	 * resource may exist only while an extension is also active. Never cache the
	 * result in an option — a plugin activation has to change the answer immediately.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, Capability> Keyed by Resource constant. Absent keys are
	 *                                   treated as unsupported.
	 */
	public function supports(): array;

	/**
	 * The writer for one resource.
	 *
	 * @since 1.1.0
	 *
	 * @param string $resource_type One of the Resource constants.
	 *
	 * @return Writer|null Null when this platform cannot write that resource.
	 */
	public function writer( string $resource_type ): ?Writer;
}
