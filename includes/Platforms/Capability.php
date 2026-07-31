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
	 * Canonical fields this platform cannot store, even though it stores the resource.
	 *
	 * @since 1.1.0
	 * @var array<int, string>
	 */
	private $ignored_fields;

	/**
	 * Constructor.
	 *
	 * Private — use the named constructors, which document the four cases that
	 * actually occur.
	 *
	 * @since 1.1.0
	 *
	 * @param bool               $supported      Whether the resource can be generated.
	 * @param string             $reason         Explanation when unsupported.
	 * @param string             $extension      Plugin slug that would enable it.
	 * @param array<int, string> $ignored_fields Fields the platform cannot store.
	 */
	private function __construct( bool $supported, string $reason = '', string $extension = '', array $ignored_fields = array() ) {
		$this->supported      = $supported;
		$this->reason         = $reason;
		$this->extension      = $extension;
		$this->ignored_fields = $ignored_fields;
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
	 * Supported, but with some of the entity's fields ignored.
	 *
	 * The honest middle ground, and the one this class was missing. A platform can store a
	 * resource without storing everything a canonical entity carries: WooCommerce has customers
	 * but no separate customer record, so `with_account` cannot mean what it means elsewhere; it
	 * has shipping classes but keeps their cost on the shipping *method*, so a class's `cost` has
	 * nowhere to go.
	 *
	 * Dropping those in the writer and saying nothing is what produced the `include_images` bug —
	 * a control the admin offers that does nothing. Naming them lets the UI mark them and the
	 * REST response report them.
	 *
	 * @since 1.1.0
	 *
	 * @param array<int, string> $ignored Canonical field names this platform cannot store.
	 *
	 * @return self
	 */
	public static function supported_except( array $ignored ): self {
		return new self( true, '', '', array_values( array_unique( $ignored ) ) );
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
	 * Canonical fields this platform ignores.
	 *
	 * @since 1.1.0
	 *
	 * @return array<int, string>
	 */
	public function get_ignored_fields(): array {
		return $this->ignored_fields;
	}

	/**
	 * Whether one field is ignored by this platform.
	 *
	 * @since 1.1.0
	 *
	 * @param string $field Canonical field name.
	 *
	 * @return bool
	 */
	public function ignores( string $field ): bool {
		return in_array( $field, $this->ignored_fields, true );
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
			'supported'      => $this->supported,
			'reason'         => $this->reason,
			'extension'      => $this->extension,
			'ignored_fields' => $this->ignored_fields,
		);
	}
}
