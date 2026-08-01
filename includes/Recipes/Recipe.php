<?php
/**
 * One store recipe
 *
 * A recipe is a *vocabulary*, not a dataset. It supplies the words and the numeric bands that make
 * every generator produce one coherent shop — grocer, boutique — and then the existing pipeline
 * runs unchanged.
 *
 * That distinction is the whole design. Shipping records instead would bypass the generator layer,
 * and the generator layer is where every invariant lives: money as integer minor units, the
 * canonical status vocabulary, no platform names in an entity, a fixed seed reproducing the same
 * data everywhere. A vocabulary pack needs none of that re-implemented, and every existing
 * parameter keeps working on top of it.
 *
 * @since   1.2.0
 * @package StoreSeeder\Recipes
 */

namespace StoreSeeder\Recipes;

use StoreSeeder\Platforms\Resource;

defined( 'ABSPATH' ) || exit;

/**
 * An immutable, validated recipe manifest.
 *
 * @since 1.2.0
 */
final class Recipe {

	/**
	 * Accent names a recipe may claim.
	 *
	 * Token names rather than colours: a hex value in a manifest would sit outside the theme and
	 * look wrong in one of the two, and a third-party recipe should not be able to break either.
	 *
	 * @since 1.2.0
	 * @var string[]
	 */
	const ACCENTS = array( 'accent', 'green', 'amber', 'sky', 'violet', 'red' );

	/**
	 * Recipe id, also the directory its vocabulary lives in.
	 *
	 * @since 1.2.0
	 * @var string
	 */
	private string $id;

	/**
	 * Human-readable name.
	 *
	 * @since 1.2.0
	 * @var string
	 */
	private string $name;

	/**
	 * One or two sentences on what kind of shop this builds.
	 *
	 * @since 1.2.0
	 * @var string
	 */
	private string $description;

	/**
	 * Icon name from the admin's icon registry.
	 *
	 * @since 1.2.0
	 * @var string
	 */
	private string $icon;

	/**
	 * Accent token name, one of self::ACCENTS.
	 *
	 * @since 1.2.0
	 * @var string
	 */
	private string $accent;

	/**
	 * Locale codes this recipe ships vocabulary for.
	 *
	 * @since 1.2.0
	 * @var string[]
	 */
	private array $locales;

	/**
	 * What to create, in dependency order.
	 *
	 * @since 1.2.0
	 * @var array<int, array{resource: string, count: int}>
	 */
	private array $plan;

	/**
	 * Per-resource generation parameters.
	 *
	 * @since 1.2.0
	 * @var array<string, array<string, mixed>>
	 */
	private array $params;

	/**
	 * Constructor.
	 *
	 * Private-by-convention: build through from_manifest(), which is where validation lives.
	 *
	 * @since 1.2.0
	 *
	 * @param string                                          $id          Recipe id.
	 * @param string                                          $name        Display name.
	 * @param string                                          $description Short description.
	 * @param string                                          $icon        Icon name.
	 * @param string                                          $accent      Accent token name.
	 * @param string[]                                        $locales     Locale codes shipped.
	 * @param array<int, array{resource: string, count: int}> $plan        Ordered plan.
	 * @param array<string, array<string, mixed>>             $params      Per-resource parameters.
	 */
	private function __construct(
		string $id,
		string $name,
		string $description,
		string $icon,
		string $accent,
		array $locales,
		array $plan,
		array $params
	) {
		$this->id          = $id;
		$this->name        = $name;
		$this->description = $description;
		$this->icon        = $icon;
		$this->accent      = $accent;
		$this->locales     = $locales;
		$this->plan        = $plan;
		$this->params      = $params;
	}

	/**
	 * Build a recipe from a decoded manifest, or null if it cannot be trusted.
	 *
	 * Null rather than an exception, and a dropped entry rather than a fatal: a recipe can come
	 * from a third-party plugin through `storeseeder_recipes`, and a malformed one must not white
	 * -screen the admin. The caller logs what it dropped.
	 *
	 * @since 1.2.0
	 *
	 * @param mixed $manifest Decoded manifest, expected to be an array.
	 *
	 * @return Recipe|null The recipe, or null when the manifest is unusable.
	 */
	public static function from_manifest( $manifest ): ?self {
		if ( ! is_array( $manifest ) ) {
			return null;
		}

		$id = sanitize_key( (string) ( $manifest['id'] ?? '' ) );

		// The id becomes a path segment. `sanitize_key` already forbids a separator; requiring it
		// to survive the round trip unchanged forbids anything else that would surprise.
		if ( '' === $id || (string) ( $manifest['id'] ?? '' ) !== $id ) {
			return null;
		}

		$name = trim( (string) ( $manifest['name'] ?? '' ) );

		if ( '' === $name ) {
			return null;
		}

		$plan = self::read_plan( $manifest['plan'] ?? array() );

		// A recipe with nothing to create is not a recipe. Better to drop it than to offer a card
		// whose button does nothing.
		if ( array() === $plan ) {
			return null;
		}

		$accent = (string) ( $manifest['accent'] ?? 'accent' );

		return new self(
			$id,
			$name,
			trim( (string) ( $manifest['description'] ?? '' ) ),
			sanitize_key( (string) ( $manifest['icon'] ?? 'box' ) ),
			in_array( $accent, self::ACCENTS, true ) ? $accent : 'accent',
			self::read_locales( $manifest['locales'] ?? array() ),
			$plan,
			self::read_params( $manifest['params'] ?? array() )
		);
	}

	/**
	 * Read and validate the ordered plan.
	 *
	 * An array rather than a map, and the order is the dependency order: brands and categories
	 * before products, products and customers before orders. A JSON object has no guaranteed key
	 * order, so expressing this as `{"product": 180}` would leave the sequence to the parser and
	 * eventually produce orders with no line items.
	 *
	 * @since 1.2.0
	 *
	 * @param mixed $raw Plan as read from the manifest.
	 *
	 * @return array<int, array{resource: string, count: int}> Valid entries, in order.
	 */
	private static function read_plan( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$known = Resource::all();
		$plan  = array();
		$seen  = array();

		foreach ( $raw as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$resource_type = sanitize_key( (string) ( $entry['resource'] ?? '' ) );
			$count         = (int) ( $entry['count'] ?? 0 );

			// An unknown resource is dropped rather than fatal: a recipe written against a newer
			// StoreSeeder, or against a resource a third party added and then removed, should lose
			// that line and keep the rest.
			if ( ! in_array( $resource_type, $known, true ) || $count < 1 ) {
				continue;
			}

			// One entry per resource. Two would run the generator twice and double the counts the
			// card promised.
			if ( isset( $seen[ $resource_type ] ) ) {
				continue;
			}

			$seen[ $resource_type ] = true;
			$plan[]                 = array(
				'resource' => $resource_type,
				'count'    => $count,
			);
		}

		return $plan;
	}

	/**
	 * Read the locale list, falling back to the one locale every recipe ships.
	 *
	 * @since 1.2.0
	 *
	 * @param mixed $raw Locales as read from the manifest.
	 *
	 * @return string[] Locale codes.
	 */
	private static function read_locales( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array( 'en_US' );
		}

		$codes = array();

		foreach ( $raw as $code ) {
			if ( is_string( $code ) && '' !== $code ) {
				$codes[] = $code;
			}
		}

		return array() === $codes ? array( 'en_US' ) : array_values( array_unique( $codes ) );
	}

	/**
	 * Read per-resource generation parameters.
	 *
	 * These are ordinary generation parameters — `price_range`, `variation_types` — not a new
	 * mechanism. A recipe sets its price band the same way a user does, which is what keeps the
	 * "a declared parameter must change the output" contract intact: there is nothing new to honour.
	 *
	 * @since 1.2.0
	 *
	 * @param mixed $raw Parameters as read from the manifest.
	 *
	 * @return array<string, array<string, mixed>> Per-resource parameters.
	 */
	private static function read_params( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$params = array();

		foreach ( $raw as $resource_type => $values ) {
			if ( is_string( $resource_type ) && is_array( $values ) ) {
				$params[ sanitize_key( $resource_type ) ] = $values;
			}
		}

		return $params;
	}

	/**
	 * Recipe id.
	 *
	 * @since 1.2.0
	 *
	 * @return string
	 */
	public function id(): string {
		return $this->id;
	}

	/**
	 * Display name.
	 *
	 * @since 1.2.0
	 *
	 * @return string
	 */
	public function name(): string {
		return $this->name;
	}

	/**
	 * Short description.
	 *
	 * @since 1.2.0
	 *
	 * @return string
	 */
	public function description(): string {
		return $this->description;
	}

	/**
	 * Icon name.
	 *
	 * @since 1.2.0
	 *
	 * @return string
	 */
	public function icon(): string {
		return $this->icon;
	}

	/**
	 * Accent token name.
	 *
	 * @since 1.2.0
	 *
	 * @return string
	 */
	public function accent(): string {
		return $this->accent;
	}

	/**
	 * Locale codes this recipe ships vocabulary for.
	 *
	 * @since 1.2.0
	 *
	 * @return string[]
	 */
	public function locales(): array {
		return $this->locales;
	}

	/**
	 * Whether this recipe carries vocabulary for a locale.
	 *
	 * Answered so the admin can say "product names stay English" before the run rather than after.
	 * A locale it does not carry is not an error — the generator falls back to `en_US` vocabulary
	 * while FakerPHP still produces local names and addresses — but it is worth stating, because
	 * the same silence is what made the locale picker offer seventy-three and deliver one.
	 *
	 * @since 1.2.0
	 *
	 * @param string $locale Locale code.
	 *
	 * @return bool
	 */
	public function has_locale( string $locale ): bool {
		return in_array( $locale, $this->locales, true );
	}

	/**
	 * What to create, in dependency order.
	 *
	 * @since 1.2.0
	 *
	 * @return array<int, array{resource: string, count: int}>
	 */
	public function plan(): array {
		return $this->plan;
	}

	/**
	 * Generation parameters for one resource.
	 *
	 * @since 1.2.0
	 *
	 * @param string $resource_type Canonical resource name.
	 *
	 * @return array<string, mixed>
	 */
	public function params_for( string $resource_type ): array {
		return $this->params[ $resource_type ] ?? array();
	}

	/**
	 * The manifest as the REST layer sends it.
	 *
	 * The plan is left unannotated here. Whether a platform supports a resource is a property of
	 * the *request*, not of the recipe, and computing it needs a resolved target — so the route
	 * adds it. Baking it in would mean caching a capability matrix, which is exactly what
	 * `Platform::supports()` running per request exists to avoid.
	 *
	 * @since 1.2.0
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'id'          => $this->id,
			'name'        => $this->name,
			'description' => $this->description,
			'icon'        => $this->icon,
			'accent'      => $this->accent,
			'locales'     => $this->locales,
			'plan'        => $this->plan,
		);
	}
}
