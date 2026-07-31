<?php
/**
 * One cell of the capability matrix
 *
 * A boolean would be enough to disable a generator, but not enough to explain it.
 * "WooCommerce cannot do this at all" and "install WooCommerce Subscriptions and it
 * can" are different messages, and the second one is actionable — so an unsupported
 * capability carries its reason, and the admin renders it instead of dimming a tile
 * with no explanation.
 *
 * @since   1.1.0
 * @package StoreSeeder\Platforms
 */

namespace StoreSeeder\Platforms;

/**
 * Immutable support verdict for one resource on one platform.
 *
 * @since 1.1.0
 */
final class Capability {
	/**
	 * Whether the resource can be generated.
	 *
	 * @since 1.1.0
	 * @var bool
	 */
	private $supported;

	/**
	 * Human-readable explanation, empty when supported.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	private $reason;

	/**
	 * Slug of the plugin that would enable this, empty when none applies.
	 *
	 * Machine-readable on purpose: the admin turns it into a plugin-install link, and
	 * a third-party extension can match on it to declare that it satisfies the need.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	private $extension;

	/**
	 * Constructor.
	 *
	 * Private — use the named constructors, which document the three cases that
	 * actually occur.
	 *
	 * @since 1.1.0
	 *
	 * @param bool   $supported Whether the resource can be generated.
	 * @param string $reason    Explanation when unsupported.
	 * @param string $extension Plugin slug that would enable it.
	 */
	private function __construct( bool $supported, string $reason = '', string $extension = '' ) {
		$this->supported = $supported;
		$this->reason    = $reason;
		$this->extension = $extension;
	}

	/**
	 * The platform can generate this resource.
	 *
	 * @since 1.1.0
	 *
	 * @return self
	 */
	public static function supported(): self {
		return new self( true );
	}

	/**
	 * The platform has no equivalent concept, and no plugin changes that.
	 *
	 * @since 1.1.0
	 *
	 * @param string $reason Why the platform cannot represent this resource.
	 *
	 * @return self
	 */
	public static function unsupported( string $reason ): self {
		return new self( false, $reason );
	}

	/**
	 * The platform could generate this resource if an extension were active.
	 *
	 * @since 1.1.0
	 *
	 * @param string $slug  Plugin slug, e.g. 'woocommerce-subscriptions'.
	 * @param string $label Display name of that plugin.
	 *
	 * @return self
	 */
	public static function missing_extension( string $slug, string $label ): self {
		return new self(
			false,
			sprintf(
				/* translators: %s: name of the plugin that would enable this generator. */
				__( 'Requires %s.', 'storeseeder' ),
				$label
			),
			$slug
		);
	}

	/**
	 * Normalise a driver-supplied value into a Capability.
	 *
	 * Drivers may return a bare `true` for the common case, so that a matrix of
	 * seventeen mostly-supported resources does not need seventeen constructor calls.
	 *
	 * @since 1.1.0
	 *
	 * @param mixed $value Capability, or bool.
	 *
	 * @return self
	 */
	public static function from( $value ): self {
		if ( $value instanceof self ) {
			return $value;
		}

		return $value
			? self::supported()
			: self::unsupported( __( 'Not supported by this platform.', 'storeseeder' ) );
	}

	/**
	 * Whether the resource can be generated.
	 *
	 * @since 1.1.0
	 *
	 * @return bool
	 */
	public function is_supported(): bool {
		return $this->supported;
	}

	/**
	 * Explanation when unsupported.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public function get_reason(): string {
		return $this->reason;
	}

	/**
	 * Plugin slug that would enable this resource.
	 *
	 * @since 1.1.0
	 *
	 * @return string Empty when no plugin applies.
	 */
	public function get_extension(): string {
		return $this->extension;
	}

	/**
	 * REST representation.
	 *
	 * @since 1.1.0
	 *
	 * @return array{supported: bool, reason: string, extension: string}
	 */
	public function to_array(): array {
		return array(
			'supported' => $this->supported,
			'reason'    => $this->reason,
			'extension' => $this->extension,
		);
	}
}
