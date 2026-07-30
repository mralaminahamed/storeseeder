<?php
/**
 * Abstract Platform Driver
 *
 * Convenience base for the platforms shipped with the plugin. Implementing the
 * Platform interface directly is entirely supported — this class only removes the
 * repetitive parts: lazily building writers, normalising the capability matrix, and
 * applying the two per-driver filters.
 *
 * @since   1.1.0
 * @package StoreSeeder\Abstracts
 */

namespace StoreSeeder\Abstracts;

use StoreSeeder\Platform\Capability;
use StoreSeeder\Platform\Platform;

/**
 * Base implementation of a platform driver.
 *
 * @since 1.1.0
 */
abstract class Platform_Driver implements Platform {
	/**
	 * Instantiated writers, keyed by resource.
	 *
	 * Built one at a time. A run touches exactly one resource, so instantiating all
	 * seventeen up front would be waste on every request.
	 *
	 * @since 1.1.0
	 * @var array<string, Writer>
	 */
	private array $writers = array();

	/**
	 * Resolved writer class map, or null before the filter has run.
	 *
	 * @since 1.1.0
	 * @var array<string, string>|null
	 */
	private $writer_map = null;

	/**
	 * Writer class names, keyed by canonical resource.
	 *
	 * Class names rather than instances, so nothing is constructed until it is needed.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, string> Resource constant => fully-qualified Writer class.
	 */
	abstract protected function writer_classes(): array;

	/**
	 * Raw capability matrix for this platform.
	 *
	 * May return bare booleans for brevity; Capability::from() normalises them. Prefer
	 * an explicit Capability whenever the answer is "no", so the admin can say why.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, Capability|bool>
	 */
	abstract protected function capabilities(): array;

	/**
	 * Which resources this platform can generate.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, Capability>
	 */
	public function supports(): array {
		$matrix = array();

		foreach ( $this->capabilities() as $resource => $value ) {
			$matrix[ $resource ] = Capability::from( $value );
		}

		/**
		 * Filters one platform's capability matrix.
		 *
		 * Runs last, so it is both the site owner's override and the way a
		 * third-party extension announces that it satisfies a requirement the
		 * driver reported as missing.
		 *
		 * @since 1.1.0
		 *
		 * @param array<string, Capability> $matrix   Capabilities keyed by resource.
		 * @param Platform                  $platform The driver being queried.
		 */
		return apply_filters( "storeseeder_platform_supports_{$this->id()}", $matrix, $this );
	}

	/**
	 * The writer for one resource.
	 *
	 * @since 1.1.0
	 *
	 * @param string $resource Canonical resource name.
	 *
	 * @return Writer|null
	 */
	public function writer( string $resource ): ?Writer {
		if ( isset( $this->writers[ $resource ] ) ) {
			return $this->writers[ $resource ];
		}

		$map = $this->resolved_writer_map();

		if ( ! isset( $map[ $resource ] ) ) {
			return null;
		}

		$writer = $map[ $resource ];

		if ( is_string( $writer ) ) {
			if ( ! class_exists( $writer ) ) {
				return null;
			}

			$writer = new $writer();
		}

		if ( ! $writer instanceof Writer ) {
			return null;
		}

		$this->writers[ $resource ] = $writer;

		return $writer;
	}

	/**
	 * Writer map after the per-platform filter has been applied.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, string|Writer>
	 */
	private function resolved_writer_map(): array {
		if ( null === $this->writer_map ) {
			/**
			 * Filters the writer map for one platform.
			 *
			 * Lets a site replace how a single resource is persisted, or add a
			 * resource the shipped driver does not cover, without subclassing.
			 * Values may be class names or Writer instances.
			 *
			 * @since 1.1.0
			 *
			 * @param array<string, string|Writer> $writers  Resource => writer.
			 * @param Platform                     $platform The driver being queried.
			 */
			$this->writer_map = apply_filters(
				"storeseeder_platform_writers_{$this->id()}",
				$this->writer_classes(),
				$this
			);
		}

		return $this->writer_map;
	}

	/**
	 * Whether a companion plugin is active.
	 *
	 * Used by capability matrices to answer "could this platform do it with help" —
	 * WooCommerce has no subscriptions of its own, StoreEngine gates several resources
	 * behind its addons.
	 *
	 * Checks the network-activated list too. The plugin's original Fluent Cart check
	 * read only `active_plugins`, which reports false on multisite for a platform
	 * activated across the network.
	 *
	 * @since 1.1.0
	 *
	 * @param string $basename Plugin basename, e.g. 'woocommerce/woocommerce.php'.
	 *
	 * @return bool
	 */
	protected function is_plugin_active( string $basename ): bool {
		if ( in_array( $basename, (array) get_option( 'active_plugins', array() ), true ) ) {
			return true;
		}

		if ( is_multisite() ) {
			$network = (array) get_site_option( 'active_sitewide_plugins', array() );

			return isset( $network[ $basename ] );
		}

		return false;
	}
}
