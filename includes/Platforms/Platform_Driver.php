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
 * @package StoreSeeder\Platforms
 */

namespace StoreSeeder\Platforms;

use StoreSeeder\Platforms\Capability;
use StoreSeeder\Platforms\Platform_Interface;

/**
 * Base implementation of a platform driver.
 *
 * @since 1.1.0
 */
abstract class Platform_Driver implements Platform_Interface {
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
	 * Resolved writer map, or null before the filter has run.
	 *
	 * Holds class names as authored, but the filter may substitute already-built Writer
	 * instances, so both forms are legal here.
	 *
	 * @since 1.1.0
	 * @var array<string, string|Writer>|null
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

		foreach ( $this->capabilities() as $resource_type => $value ) {
			$matrix[ $resource_type ] = Capability::from( $value );
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
		 * @param Platform_Interface        $platform The driver being queried.
		 */
		return apply_filters( "storeseeder_platform_supports_{$this->id()}", $matrix, $this );
	}

	/**
	 * Extra generation parameters this platform understands, for one resource.
	 *
	 * The seam for a property only one platform has. WooCommerce products carry a catalogue
	 * visibility and a tax status; Fluent Cart variations carry a payment type. Neither belongs
	 * in a canonical entity — a generator may not name a platform, and a field only one platform
	 * stores would make a fixed seed produce different data on the others.
	 *
	 * So these are *write-time hints* rather than entity fields: they arrive as generation
	 * parameters, this driver's writer reads them from `$this->params`, and every other driver
	 * neither sees nor cares. The canonical entity, and with it the same-seed guarantee, is
	 * untouched.
	 *
	 * Each entry is a JSON Schema fragment, keyed by parameter name, exactly as a controller's
	 * `get_resource_specific_params()` returns — so the REST layer, the admin form and the MCP
	 * input schema all pick it up through the paths they already have.
	 *
	 * Empty by default: a driver with no platform-specific fields says nothing rather than
	 * inventing a shape.
	 *
	 * @since 1.1.0
	 *
	 * @param string $resource_type Canonical resource name.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function fields( string $resource_type ): array {
		$fields = $this->platform_fields( $resource_type );

		/**
		 * Filters one platform's extra fields for one resource.
		 *
		 * How an extension that adds a column to its platform exposes it for seeding, without
		 * touching the driver — the field-level counterpart of
		 * `storeseeder_platform_supports_{$id}`.
		 *
		 * @since 1.1.0
		 * @hook  storeseeder_platform_fields_{$id}
		 *
		 * @param array<string, mixed> $fields        Schema fragments by name. Typed loosely
		 *                                            because a filter returns what it likes, and
		 *                                            what comes back is checked rather than
		 *                                            trusted.
		 * @param string               $resource_type Canonical resource name.
		 * @param Platform_Interface   $platform      The driver being asked.
		 */
		$filtered = (array) apply_filters( "storeseeder_platform_fields_{$this->id()}", $fields, $resource_type, $this );

		// Entries a filter invented in the wrong shape are dropped rather than handed to the REST
		// layer, which would register them as route arguments and fail on the first request. A
		// numeric key is wrong too — these are keyed by parameter name.
		$valid = array();

		foreach ( $filtered as $name => $schema ) {
			if ( is_array( $schema ) && '' !== (string) $name && ! is_numeric( $name ) ) {
				$valid[ (string) $name ] = $schema;
			}
		}

		return $valid;
	}

	/**
	 * This driver's own extra fields, before the filter.
	 *
	 * @since 1.1.0
	 *
	 * @param string $resource_type Canonical resource name.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	protected function platform_fields( string $resource_type ): array {
		unset( $resource_type );

		return array();
	}

	/**
	 * Existing records in the store, for a control that has to name one.
	 *
	 * Parameters like `customer_id` and `product_id` are foreign keys into the target platform, and
	 * the admin rendered them as a number box — usable only by someone who already knew the id. To
	 * offer a list instead, something has to read the store, and only the driver knows how: an
	 * Eloquent model on Fluent Cart, a CRUD query on WooCommerce.
	 *
	 * Concrete and empty by default, like `Writer::delete()`, rather than a new method on
	 * `Platform_Interface`. A third-party driver written against the interface must keep loading;
	 * one that does not implement this simply offers no suggestions, and the control falls back to
	 * accepting a plain id.
	 *
	 * Read-only, and never called during generation — this exists for the admin's benefit.
	 *
	 * @since 1.1.0
	 *
	 * @param string $resource_type Canonical resource name, e.g. Resource::CUSTOMER.
	 * @param string $query         What the user typed. Empty means "the first few".
	 * @param int    $limit         How many to return at most.
	 *
	 * @return array<int, array{id: int|string, label: string}>
	 */
	public function search( string $resource_type, string $query = '', int $limit = 20 ): array {
		$results = $this->platform_search( $resource_type, $query, max( 1, min( 50, $limit ) ) );

		/**
		 * Filters one platform's search results for one resource.
		 *
		 * For a driver that stores a resource somewhere its own author did not anticipate, and for
		 * changing what a result is labelled with.
		 *
		 * @since 1.1.0
		 *
		 * @param array<int, array{id: int|string, label: string}> $results       The results.
		 * @param string                                           $resource_type Canonical resource name.
		 * @param string                                           $query         The search term.
		 */
		// Cast rather than checked: a filter returns whatever it likes, and PHPStan reads the
		// documented type above as a guarantee. `fields()` casts its own filter result the same way.
		$filtered = (array) apply_filters( "storeseeder_platform_search_{$this->id()}", $results, $resource_type, $query );

		return $this->clean_results( $filtered );
	}

	/**
	 * Where a resource lives in wp-admin, or null when it has no screen of its own.
	 *
	 * A recipe finishes by telling someone it made 180 products. The next thing they want is to
	 * look at them, and until now the completion panel was eight numbers that went nowhere — a
	 * receipt where a door belonged.
	 *
	 * Only the driver can answer: WooCommerce's orders moved to `admin.php?page=wc-orders` under
	 * HPOS while its products stayed on `edit.php`, and Fluent Cart's are hash routes inside one
	 * page. Deriving any of that from a resource name would be guessing.
	 *
	 * Concrete and returning null rather than abstract, the same way `Writer::delete()` and
	 * `search()` are: a driver written against `Platform_Interface` before this existed keeps
	 * loading, and its counts render as plain text exactly as they do today.
	 *
	 * @since 1.2.0
	 *
	 * @param string $resource_type Canonical resource name.
	 *
	 * @return string|null Absolute admin URL, or null.
	 */
	public function admin_url( string $resource_type ): ?string {
		$path = $this->admin_path( $resource_type );

		/**
		 * Filters where a resource's admin screen is, for one platform.
		 *
		 * @since 1.2.0
		 * @hook  storeseeder_platform_admin_url_{$platform}
		 *
		 * @param mixed  $path          Admin-relative path, or null when the driver has no screen
		 *                              for this resource.
		 * @param string $resource_type Canonical resource name.
		 */
		$filtered = apply_filters( "storeseeder_platform_admin_url_{$this->id()}", $path, $resource_type );

		if ( ! is_string( $filtered ) || '' === $filtered ) {
			return null;
		}

		// `admin_url()` rather than concatenation: a site in a subdirectory, or one where wp-admin
		// has been moved, still gets a link that works.
		return admin_url( $filtered );
	}

	/**
	 * One driver's own admin screens, admin-relative. Overridden by drivers that have them.
	 *
	 * @since 1.2.0
	 *
	 * @param string $resource_type Canonical resource name.
	 *
	 * @return string|null Path relative to wp-admin, or null.
	 */
	protected function admin_path( string $resource_type ): ?string {
		unset( $resource_type );

		return null;
	}

	/**
	 * One driver's own search. Overridden by drivers that can offer suggestions.
	 *
	 * @since 1.1.0
	 *
	 * @param string $resource_type Canonical resource name.
	 * @param string $query         The search term.
	 * @param int    $limit         Maximum results.
	 *
	 * @return array<int, array{id: int|string, label: string}>
	 */
	protected function platform_search( string $resource_type, string $query, int $limit ): array {
		unset( $resource_type, $query, $limit );

		return array();
	}

	/**
	 * How a product reads in a suggestion list.
	 *
	 * Shared so both drivers format alike: the SKU when there is one, because that is what a
	 * shopkeeper recognises, and the id always, because the id is what the field sends and what a
	 * support conversation ends up quoting.
	 *
	 * @since 1.1.0
	 *
	 * @param string     $name Product name.
	 * @param string     $sku  SKU, or '' when it has none.
	 * @param int|string $id   Product id.
	 *
	 * @return string
	 */
	protected function product_label( string $name, string $sku, $id ): string {
		$name = '' !== trim( $name ) ? $name : __( '(untitled)', 'storeseeder' );

		if ( '' !== trim( $sku ) ) {
			return sprintf(
				/* translators: 1: product name, 2: SKU, 3: product id. */
				__( '%1$s — %2$s (#%3$s)', 'storeseeder' ),
				$name,
				$sku,
				(string) $id
			);
		}

		return sprintf(
			/* translators: 1: product name, 2: product id. */
			__( '%1$s (#%2$s)', 'storeseeder' ),
			$name,
			(string) $id
		);
	}

	/**
	 * How a customer reads in a suggestion list.
	 *
	 * The email carries the weight here: two customers share a name far more often than an address.
	 *
	 * @since 1.1.0
	 *
	 * @param string     $name  Display name.
	 * @param string     $email Email address.
	 * @param int|string $id    Customer id.
	 *
	 * @return string
	 */
	protected function customer_label( string $name, string $email, $id ): string {
		$name = '' !== trim( $name ) ? $name : __( '(no name)', 'storeseeder' );

		if ( '' !== trim( $email ) ) {
			return sprintf(
				/* translators: 1: customer name, 2: email address. */
				__( '%1$s — %2$s', 'storeseeder' ),
				$name,
				$email
			);
		}

		return sprintf(
			/* translators: 1: customer name, 2: customer id. */
			__( '%1$s (#%2$s)', 'storeseeder' ),
			$name,
			(string) $id
		);
	}

	/**
	 * Drop anything from a filtered result set that is not a usable suggestion.
	 *
	 * A filter returns whatever it likes, and a malformed entry would reach the admin as an option
	 * with no id to send back — the same defensive shape `fields()` uses on its own filter.
	 *
	 * @since 1.1.0
	 *
	 * @param array<mixed> $results Raw results.
	 *
	 * @return array<int, array{id: int|string, label: string}>
	 */
	private function clean_results( array $results ): array {
		$clean = array();

		foreach ( $results as $result ) {
			if ( ! is_array( $result ) || ! isset( $result['id'], $result['label'] ) ) {
				continue;
			}

			if ( ! is_scalar( $result['id'] ) || ! is_scalar( $result['label'] ) ) {
				continue;
			}

			$clean[] = array(
				'id'    => is_numeric( $result['id'] ) ? (int) $result['id'] : (string) $result['id'],
				'label' => (string) $result['label'],
			);
		}

		return $clean;
	}

	/**
	 * Every extra field this platform declares, keyed by resource.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, array<string, array<string, mixed>>>
	 */
	public function all_fields(): array {
		$all = array();

		foreach ( Resource::all() as $resource_type ) {
			$fields = $this->fields( $resource_type );

			if ( array() !== $fields ) {
				$all[ $resource_type ] = $fields;
			}
		}

		return $all;
	}

	/**
	 * Extensions of this platform that are installed, and what they are.
	 *
	 * Capabilities already say *whether* a resource can be generated; this says what the
	 * site actually has, which is a different question and the one a support conversation
	 * starts with — "Fluent Cart 1.6.0, Pro 1.5.3" is a fact, "subscriptions unavailable"
	 * is a consequence. Reported per platform because every platform has its own add-ons:
	 * WooCommerce Subscriptions, StoreEngine's addons, Fluent Cart Pro.
	 *
	 * Empty by default, so a driver that has none says nothing rather than inventing a
	 * shape.
	 *
	 * @since 1.1.0
	 *
	 * @return array<int, array{slug: string, label: string, active: bool, version: string|null}>
	 */
	public function extensions(): array {
		return array();
	}

	/**
	 * The writer for one resource.
	 *
	 * @since 1.1.0
	 *
	 * @param string $resource_type Canonical resource name.
	 *
	 * @return Writer|null
	 */
	public function writer( string $resource_type ): ?Writer {
		if ( isset( $this->writers[ $resource_type ] ) ) {
			return $this->writers[ $resource_type ];
		}

		$map = $this->resolved_writer_map();

		if ( ! isset( $map[ $resource_type ] ) ) {
			return null;
		}

		$writer = $map[ $resource_type ];

		if ( is_string( $writer ) ) {
			if ( ! class_exists( $writer ) ) {
				return null;
			}

			$writer = new $writer();
		}

		if ( ! $writer instanceof Writer ) {
			return null;
		}

		$this->writers[ $resource_type ] = $writer;

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
			 * @param Platform_Interface           $platform The driver being queried.
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
