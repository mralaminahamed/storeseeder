<?php
/**
 * Target platform resolution
 *
 * Turns whatever the caller asked for — an explicit id, or nothing at all — into the
 * one platform a run will write to, or into an error explaining why it cannot be
 * decided. Guessing is not an option here: picking the wrong target writes rows into
 * the wrong store, and nothing about that failure is obvious afterwards.
 *
 * @since   1.1.0
 * @package StoreSeeder\Platform
 */

namespace StoreSeeder\Platform;

use WP_Error;

/**
 * Resolves and stores the target platform.
 *
 * @since 1.1.0
 */
final class Resolver {
	/**
	 * Sentinel meaning "decide for me".
	 *
	 * @since 1.1.0
	 * @var string
	 */
	const AUTO = 'auto';

	/**
	 * Option holding the site-wide target.
	 *
	 * Site-wide rather than per-browser: two administrators on one site must not be
	 * able to seed different platforms without either of them noticing. Empty means
	 * auto.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	const OPTION = 'storeseeder_target_platform';

	/**
	 * The driver registry.
	 *
	 * @since 1.1.0
	 * @var Registry
	 */
	private Registry $registry;

	/**
	 * Constructor.
	 *
	 * @since 1.1.0
	 *
	 * @param Registry|null $registry Driver registry. Defaults to the shared one.
	 */
	public function __construct( ?Registry $registry = null ) {
		$this->registry = $registry ?? Registry::instance();
	}

	/**
	 * Resolve the platform a run should write to.
	 *
	 * @since 1.1.0
	 *
	 * @param string|null $requested Platform id, 'auto', or null for auto.
	 *
	 * @return Platform|WP_Error
	 */
	public function resolve( ?string $requested = null ) {
		/**
		 * Filters the requested target platform before it is resolved.
		 *
		 * Applied to the raw request, so returning an id here forces every run onto
		 * that platform regardless of the stored option — and the id is still
		 * validated afterwards, so a typo fails loudly rather than silently.
		 *
		 * @since 1.1.0
		 *
		 * @param string|null $requested Platform id, 'auto', or null.
		 */
		$requested = apply_filters( 'storeseeder_target_platform', $requested );

		if ( is_string( $requested ) && '' !== $requested && self::AUTO !== $requested ) {
			return $this->resolve_explicit( $requested );
		}

		return $this->resolve_auto();
	}

	/**
	 * Resolve a specific requested id.
	 *
	 * @since 1.1.0
	 *
	 * @param string $id Platform id.
	 *
	 * @return Platform|WP_Error
	 */
	private function resolve_explicit( string $id ) {
		$platform = $this->registry->get( $id );

		if ( null === $platform ) {
			return new WP_Error(
				'storeseeder_unknown_platform',
				sprintf(
					/* translators: %s: the platform identifier that was requested. */
					__( 'Unknown target platform: %s.', 'storeseeder' ),
					$id
				),
				array( 'status' => 400 )
			);
		}

		if ( ! $platform->is_active() ) {
			return new WP_Error(
				'storeseeder_platform_inactive',
				sprintf(
					/* translators: %s: platform display name. */
					__( '%s is not active on this site.', 'storeseeder' ),
					$platform->label()
				),
				array( 'status' => 400 )
			);
		}

		return $platform;
	}

	/**
	 * Resolve without an explicit request.
	 *
	 * @since 1.1.0
	 *
	 * @return Platform|WP_Error
	 */
	private function resolve_auto() {
		$active = $this->registry->active();
		$stored = $this->stored();

		// A stored target that has since been deactivated falls through to the
		// single-platform case rather than erroring, so deactivating a plugin does not
		// leave the admin stuck behind an error it cannot clear from the UI.
		if ( '' !== $stored && isset( $active[ $stored ] ) ) {
			return $active[ $stored ];
		}

		if ( 1 === count( $active ) ) {
			return reset( $active );
		}

		if ( array() === $active ) {
			return new WP_Error(
				'storeseeder_no_platform',
				__( 'No supported e-commerce platform is active on this site.', 'storeseeder' ),
				array( 'status' => 400 )
			);
		}

		return new WP_Error(
			'storeseeder_platform_required',
			__( 'More than one supported platform is active. Choose which one to seed.', 'storeseeder' ),
			array(
				'status'     => 409,
				'candidates' => array_values(
					array_map(
						static function ( Platform $platform ): array {
							return array(
								'id'    => $platform->id(),
								'label' => $platform->label(),
							);
						},
						$active
					)
				),
			)
		);
	}

	/**
	 * The stored site-wide target.
	 *
	 * @since 1.1.0
	 *
	 * @return string Platform id, or '' for auto.
	 */
	public function stored(): string {
		$stored = get_option( self::OPTION, '' );

		return is_string( $stored ) ? $stored : '';
	}

	/**
	 * Store the site-wide target.
	 *
	 * @since 1.1.0
	 *
	 * @param string $id Platform id, or '' / 'auto' to clear it.
	 *
	 * @return true|WP_Error
	 */
	public function store( string $id ) {
		if ( '' === $id || self::AUTO === $id ) {
			update_option( self::OPTION, '', false );

			return true;
		}

		$platform = $this->registry->get( $id );

		if ( null === $platform ) {
			return new WP_Error(
				'storeseeder_unknown_platform',
				sprintf(
					/* translators: %s: the platform identifier that was requested. */
					__( 'Unknown target platform: %s.', 'storeseeder' ),
					$id
				),
				array( 'status' => 400 )
			);
		}

		update_option( self::OPTION, $platform->id(), false );

		return true;
	}

	/**
	 * Whether the target cannot be decided without asking.
	 *
	 * Drives the prompt on the generator page: true only when more than one platform
	 * is active *and* no site-wide target has been chosen.
	 *
	 * @since 1.1.0
	 *
	 * @return bool
	 */
	public function is_ambiguous(): bool {
		$active = $this->registry->active();

		if ( count( $active ) < 2 ) {
			return false;
		}

		$stored = $this->stored();

		return '' === $stored || ! isset( $active[ $stored ] );
	}
}
