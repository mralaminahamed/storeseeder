<?php
/**
 * Recipe registry
 *
 * Holds the recipes a run can be built from. The shipped ones are read from `data/recipes/`;
 * `storeseeder_recipes` is the supported way for anything else to join.
 *
 * That filter is the point of the registry rather than an afterthought. `storeseeder_sample_data_source`
 * swaps the *whole* remote repository, so before this a third party could not *add* vocabulary — only
 * replace everything and take responsibility for the lot. Registering one recipe from your own plugin
 * is the same shape as registering a platform through `storeseeder_platforms`.
 *
 * Mirrors StoreSeeder\Rest\Registry and StoreSeeder\Platforms\Registry deliberately: three registries
 * answering "what is available?" should not be discovered three different ways.
 *
 * @since   1.2.0
 * @package StoreSeeder\Recipes
 */

namespace StoreSeeder\Recipes;

use StoreSeeder\Platforms\Locale;

use StoreSeeder\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * Registry of store recipes.
 *
 * @since 1.2.0
 */
final class Registry {

	/**
	 * Shared instance.
	 *
	 * @since 1.2.0
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Recipes keyed by id, or null before the filter has run.
	 *
	 * @since 1.2.0
	 * @var array<string, Recipe>|null
	 */
	private $recipes = null;

	/**
	 * Shared instance.
	 *
	 * @since 1.2.0
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
	 * Forget the resolved recipes.
	 *
	 * For tests, and for a plugin that registers a recipe after the registry has already been
	 * read. Nothing in a request path calls it.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function reset(): void {
		$this->recipes = null;
	}

	/**
	 * Every registered recipe, keyed by id.
	 *
	 * @since 1.2.0
	 *
	 * @return array<string, Recipe>
	 */
	public function all(): array {
		if ( null !== $this->recipes ) {
			return $this->recipes;
		}

		$manifests = $this->bundled_manifests();

		/**
		 * Filters the recipe manifests available to a run.
		 *
		 * Append a manifest array to ship a recipe from your own plugin — the admin page, the
		 * REST route and the CLI command all read this registry, so nothing else needs touching.
		 * A recipe whose vocabulary lives outside this plugin also needs
		 * `storeseeder_sample_data_path` to point at it.
		 *
		 * A manifest that cannot be parsed is dropped with a debug line rather than throwing:
		 * a malformed third-party recipe must not take the admin down with it.
		 *
		 * @since 1.2.0
		 * @hook  storeseeder_recipes
		 *
		 * @param mixed $manifests Decoded manifests, a list of arrays when unfiltered. Typed
		 *                         loosely because a filter may return anything; a return that is
		 *                         not an array is discarded below.
		 */
		$filtered = apply_filters( 'storeseeder_recipes', $manifests );

		$recipes = array();

		foreach ( (array) $filtered as $manifest ) {
			$recipe = Recipe::from_manifest( $manifest );

			if ( null === $recipe ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- debug only.
					error_log( 'StoreSeeder: dropped an unusable recipe manifest.' );
				}
				continue;
			}

			$recipes[ $recipe->id() ] = $recipe;
		}

		$this->recipes = $recipes;

		return $this->recipes;
	}

	/**
	 * One recipe by id, or null.
	 *
	 * @since 1.2.0
	 *
	 * @param string $id Recipe id.
	 *
	 * @return Recipe|null
	 */
	public function get( string $id ): ?Recipe {
		$all = $this->all();

		return $all[ $id ] ?? null;
	}

	/**
	 * Whether a recipe id is registered.
	 *
	 * @since 1.2.0
	 *
	 * @param string $id Recipe id.
	 *
	 * @return bool
	 */
	public function has( string $id ): bool {
		return null !== $this->get( $id );
	}

	/**
	 * Where downloaded recipes live.
	 *
	 * Not inside the plugin. Recipe content and plugin code move at different speeds — fixing a
	 * typo in the grocer's category list should not need a release — and the combination that
	 * makes this feature worth having, recipes times locales, is exactly the thing that should
	 * never be in a wp.org zip.
	 *
	 * The cost is a consent prompt and a fetch before the page can do anything, and that is the
	 * honest trade: a recipe running on default vocabulary would name grocery products after
	 * consumer electronics and price them like groceries, which is worse than an empty page that
	 * explains itself.
	 *
	 * @since 1.2.0
	 *
	 * @return string Absolute path, with a trailing slash.
	 */
	public static function directory(): string {
		return Storage::recipes();
	}

	/**
	 * Where one recipe's vocabulary lives.
	 *
	 * Filterable so a plugin shipping its own recipe can keep the words beside its own code
	 * instead of in the downloaded directory — the other half of `storeseeder_recipes`, which
	 * registers the manifest but says nothing about where the vocabulary is.
	 *
	 * @since 1.2.0
	 *
	 * @param string $id Recipe id.
	 *
	 * @return string Absolute path, no trailing slash.
	 */
	public static function vocabulary_directory( string $id ): string {
		/**
		 * Filters where each recipe's vocabulary files are read from.
		 *
		 * @since 1.2.0
		 * @hook  storeseeder_recipe_directories
		 *
		 * @param mixed $directories Absolute paths keyed by recipe id, an array<string, string>
		 *                           when unfiltered. Typed loosely because a filter may return
		 *                           anything; a non-array is discarded.
		 */
		$directories = apply_filters( 'storeseeder_recipe_directories', array() );

		if ( is_array( $directories ) && isset( $directories[ $id ] ) && is_string( $directories[ $id ] ) ) {
			return untrailingslashit( $directories[ $id ] );
		}

		return untrailingslashit( self::directory() . $id );
	}

	/**
	 * The archive's own index, or an empty list when it has not been downloaded.
	 *
	 * `recipes.json` sits at the root of the archive and names every recipe in it. Reading the
	 * index rather than globbing is what makes a *partial* download detectable: an id listed here
	 * with no directory beside it is an interrupted fetch, which is a different problem from a
	 * recipe nobody wrote, and the two deserve different messages.
	 *
	 * @since 1.2.0
	 *
	 * @return array<int, array<string, mixed>> Index entries, in the archive's order.
	 */
	public static function index(): array {
		$path = self::directory() . 'recipes.json';

		if ( ! is_readable( $path ) ) {
			return array();
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading a downloaded JSON file; WP_Filesystem needs a request context this can be called outside of.
		$decoded = json_decode( (string) file_get_contents( $path ), true );

		if ( ! is_array( $decoded ) || ! isset( $decoded['recipes'] ) || ! is_array( $decoded['recipes'] ) ) {
			return array();
		}

		return array_values( array_filter( $decoded['recipes'], 'is_array' ) );
	}

	/**
	 * Whether the recipe archive has been downloaded.
	 *
	 * @since 1.2.0
	 *
	 * @return bool
	 */
	public static function downloaded(): bool {
		return array() !== self::index();
	}

	/**
	 * Recipes the index promises that are not actually on disk.
	 *
	 * Named rather than swallowed. A fetch that dropped half the archive would otherwise present
	 * as a shorter list of recipes, and a shorter list looks like a decision somebody made.
	 *
	 * @since 1.2.0
	 *
	 * @return string[] Recipe ids.
	 */
	public static function missing(): array {
		$missing = array();

		foreach ( self::index() as $entry ) {
			$id = isset( $entry['id'] ) ? (string) $entry['id'] : '';

			if ( '' !== $id && ! is_readable( self::directory() . $id . '/recipe.json' ) ) {
				$missing[] = $id;
			}
		}

		return $missing;
	}

	/**
	 * What is wrong with a downloaded recipe, in the user's words.
	 *
	 * A manifest is a set of claims — these locales, this plan — and nothing has checked them
	 * against the files beside it. The failures are all quiet ones, which is why they are worth
	 * finding: every single one produces data rather than an error.
	 *
	 * - **No vocabulary at all.** The recipe runs, the generators fall back to their own constants,
	 *   and a "grocery" store comes out full of consumer electronics priced like groceries. That is
	 *   the half-recipe the completeness bar exists to refuse, and it is indistinguishable from a
	 *   working run unless someone reads the product names.
	 * - **A locale claimed but not shipped.** The card would say the recipe covers `de_DE`, the run
	 *   would serve English titles, and nobody would be told. The same silence that let the locale
	 *   picker offer seventy-three languages and deliver one.
	 * - **A resource in the plan with no words behind it.** Narrower than the first: the shop is
	 *   right but its categories are generic.
	 *
	 * Reported, not enforced. A recipe with generic categories is still more useful than no recipe,
	 * and refusing to run it would be deciding for the user something they can see for themselves.
	 * `blocking` marks the one case where that is not true.
	 *
	 * @since 1.2.0
	 *
	 * @param Recipe $recipe The recipe.
	 * @param string $locale Locale the run would use.
	 *
	 * @return array<int, array{code: string, message: string, blocking: bool}>
	 */
	public static function audit( Recipe $recipe, string $locale ): array {
		$dir      = self::vocabulary_directory( $recipe->id() );
		$issues   = array();
		$manifest = self::resource_manifests( $dir );

		if ( array() === $manifest ) {
			$issues[] = array(
				'code'     => 'storeseeder_recipe_no_vocabulary',
				/* translators: %s: recipe name. */
				'message'  => sprintf( __( '%s has no vocabulary files. It would build a store with generic product names — sync the recipes again.', 'storeseeder' ), $recipe->name() ),
				'blocking' => true,
			);

			return $issues;
		}

		$without_locale = array();

		$owned = array_flip( self::VOCABULARY_RESOURCES );

		foreach ( $manifest as $resource => $locales ) {
			// Only the directories this recipe is answerable for. A shared one it happens to carry
			// is a bonus, not a promise.
			if ( isset( $owned[ $resource ] ) && ! in_array( $locale, $locales, true ) ) {
				$without_locale[] = $resource;
			}
		}

		// Only worth saying when the recipe *claimed* the locale. Falling back for a locale it
		// never advertised is the documented behaviour, and the card already says which it ships.
		if ( $recipe->has_locale( $locale ) && array() !== $without_locale ) {
			$issues[] = array(
				'code'     => 'storeseeder_recipe_locale_incomplete',
				'message'  => sprintf(
					/* translators: 1: locale name and code, e.g. German (de_DE). 2: comma-separated resource names. */
					__( 'This recipe lists %1$s but ships none for %2$s, so those fall back to English.', 'storeseeder' ),
					self::locale_name( $locale ),
					implode( ', ', $without_locale )
				),
				'blocking' => false,
			);
		}

		$unbacked = array();

		foreach ( $recipe->plan() as $entry ) {
			// Only the resources a vocabulary can speak for. An order has no words of its own —
			// it is built from the products and customers that already exist.
			if ( ! isset( self::VOCABULARY_RESOURCES[ $entry['resource'] ] ) ) {
				continue;
			}

			if ( ! isset( $manifest[ self::VOCABULARY_RESOURCES[ $entry['resource'] ] ] ) ) {
				$unbacked[] = $entry['resource'];
			}
		}

		if ( array() !== $unbacked ) {
			$issues[] = array(
				'code'     => 'storeseeder_recipe_partial',
				'message'  => sprintf(
					/* translators: %s: comma-separated resource names. */
					__( 'No words shipped for %s, so those keep the default vocabulary.', 'storeseeder' ),
					implode( ', ', $unbacked )
				),
				'blocking' => false,
			);
		}

		return $issues;
	}

	/**
	 * A locale named the way a person reads it — "German (de_DE)", not "de_DE".
	 *
	 * `Locale::label()` returns "German (Germany)", which repeats the country and drops the code
	 * the manifest is actually keyed by. Both halves matter here: the name answers "will this be in
	 * my language" and the code is what someone adding a translation has to create a directory for.
	 *
	 * @since 1.2.0
	 *
	 * @param string $locale Locale code.
	 *
	 * @return string
	 */
	private static function locale_name( string $locale ): string {
		$label = Locale::label( $locale );

		if ( $label === $locale ) {
			return $locale;
		}

		$language = explode( ' (', $label )[0];

		return $language . ' (' . $locale . ')';
	}

	/**
	 * The resources whose words decide what kind of shop this is.
	 *
	 * Deliberately not every resource with a vocabulary directory. `customers` holds country
	 * lists, phone patterns and postcode formats — locale reference data that a grocer and a
	 * boutique share, and that the sample-data archive already supplies. Demanding it of every
	 * recipe put "No words shipped for customer" on all three cards, which is a false alarm about
	 * correct behaviour, and a false alarm is worse than the lost nuance.
	 *
	 * These five are the completeness bar restated: product names, a category tree, tag labels and
	 * brand names are what make a grocer look like a grocer.
	 *
	 * Named rather than derived. `product_category` does not pluralise to `product_categories` by
	 * any rule that also handles `shipping_classes`, which is the same reason generators carry an
	 * explicit resource alongside their REST route.
	 *
	 * @since 1.2.0
	 * @var array<string, string>
	 */
	const VOCABULARY_RESOURCES = array(
		'product'          => 'products',
		'brand'            => 'brands',
		'product_category' => 'product_categories',
		'product_tag'      => 'product_tags',
	);

	/**
	 * Locales each of a recipe's resource directories actually carries.
	 *
	 * Read from the per-resource `manifest.json` the archive ships, falling back to looking at the
	 * directories — a recipe written by hand, or by a third party who never ran the generator,
	 * should still audit correctly.
	 *
	 * @since 1.2.0
	 *
	 * @param string $dir Recipe's vocabulary directory.
	 *
	 * @return array<string, string[]> Locale codes keyed by resource directory name.
	 */
	private static function resource_manifests( string $dir ): array {
		$found = array();

		$directories = glob( $dir . '/*', GLOB_ONLYDIR );

		if ( false === $directories ) {
			return array();
		}

		foreach ( $directories as $resource_dir ) {
			$resource = basename( $resource_dir );
			$manifest = $resource_dir . '/manifest.json';

			if ( is_readable( $manifest ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading a downloaded JSON file; WP_Filesystem needs a request context this can be called outside of.
				$decoded = json_decode( (string) file_get_contents( $manifest ), true );

				if ( is_array( $decoded ) && isset( $decoded['locales'] ) && is_array( $decoded['locales'] ) ) {
					$found[ $resource ] = array_values( array_filter( $decoded['locales'], 'is_string' ) );
					continue;
				}
			}

			$locales = glob( $resource_dir . '/*', GLOB_ONLYDIR );

			if ( false !== $locales && array() !== $locales ) {
				$found[ $resource ] = array_map( 'basename', $locales );
			}
		}

		return $found;
	}

	/**
	 * Largest recipe mark the admin will render, in bytes.
	 *
	 * A logo is a few hundred bytes. Anything approaching this is not a logo, and inlining it
	 * would put it in every admin payload.
	 *
	 * @since 1.2.0
	 * @var int
	 */
	const MAX_ICON_BYTES = 16384;

	/**
	 * A recipe's own mark as a data URI, or '' when it has none the admin will show.
	 *
	 * The artwork comes from a downloaded archive, which a site owner may have repointed at a fork
	 * through `storeseeder_recipes_source` — so it is third-party markup rendering inside wp-admin,
	 * and it is treated as untrusted no matter how it arrived.
	 *
	 * Two independent defences, because either alone has a bad failure mode:
	 *
	 * - The markup is filtered here against a shape-only allowlist, so a `<script>`, an `onload=`,
	 *   a `<foreignObject>` or an external reference never reaches the browser.
	 * - It is returned as a data URI for an `<img>`, which is a passive context: even markup that
	 *   somehow got past the filter cannot run script or fetch anything from there.
	 *
	 * The cost of the `<img>` is that `currentColor` does not apply, so a mark carries its own
	 * colours. That suits what this is — a recipe's logo, not a UI glyph — and the tile tint still
	 * comes from the manifest's accent token.
	 *
	 * @since 1.2.0
	 *
	 * @param string $id Recipe id.
	 *
	 * @return string A `data:image/svg+xml;base64,…` URI, or ''.
	 */
	public static function icon_uri( string $id ): string {
		$id = sanitize_key( $id );

		if ( '' === $id ) {
			return '';
		}

		$path = self::vocabulary_directory( $id ) . '/icon.svg';

		if ( ! is_readable( $path ) || filesize( $path ) > self::MAX_ICON_BYTES ) {
			return '';
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading a downloaded file; WP_Filesystem needs a request context this can be called outside of.
		$markup = (string) file_get_contents( $path );

		$clean = self::clean_svg( $markup );

		if ( '' === $clean ) {
			return '';
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- building a data URI for an <img>, not obfuscating code. The markup was filtered above.
		return 'data:image/svg+xml;base64,' . base64_encode( $clean );
	}

	/**
	 * Strip everything from an SVG that is not shape.
	 *
	 * An allowlist rather than a blocklist. The set of dangerous SVG constructs is not fixed —
	 * `<script>`, event handlers, `<use href>`, `<foreignObject>`, `<image>`, embedded CSS, and
	 * whatever the next specification adds — so enumerating them is a list that is wrong the day
	 * after it is written. Enumerating the harmless ones is a list that stays right.
	 *
	 * Anything with no shapes left after filtering returns '' rather than an empty `<svg>`, so the
	 * caller falls back to the icon registry instead of rendering a blank tile.
	 *
	 * @since 1.2.0
	 *
	 * @param string $markup Raw SVG.
	 *
	 * @return string Filtered SVG, or '' when nothing usable survived.
	 */
	private static function clean_svg( string $markup ): string {
		$allowed = array(
			'svg'      => array(
				'xmlns'      => true,
				'viewbox'    => true,
				'width'      => true,
				'height'     => true,
				'fill'       => true,
				'role'       => true,
				'aria-label' => true,
			),
			'g'        => array(
				'fill'      => true,
				'stroke'    => true,
				'opacity'   => true,
				'transform' => true,
			),
			'path'     => array(),
			'circle'   => array(
				'cx' => true,
				'cy' => true,
				'r'  => true,
			),
			'ellipse'  => array(
				'cx' => true,
				'cy' => true,
				'rx' => true,
				'ry' => true,
			),
			'rect'     => array(
				'x'      => true,
				'y'      => true,
				'width'  => true,
				'height' => true,
				'rx'     => true,
				'ry'     => true,
			),
			'line'     => array(
				'x1' => true,
				'y1' => true,
				'x2' => true,
				'y2' => true,
			),
			'polyline' => array( 'points' => true ),
			'polygon'  => array( 'points' => true ),
			'title'    => array(),
		);

		// Presentation attributes every shape may carry. `style` is deliberately absent: it accepts
		// a url() and so is a fetch this cannot audit.
		$paint = array(
			'd'                => true,
			'fill'             => true,
			'fill-rule'        => true,
			'fill-opacity'     => true,
			'stroke'           => true,
			'stroke-width'     => true,
			'stroke-linecap'   => true,
			'stroke-linejoin'  => true,
			'stroke-opacity'   => true,
			'stroke-dasharray' => true,
			'opacity'          => true,
			'transform'        => true,
		);

		foreach ( $allowed as $tag => $attributes ) {
			if ( 'svg' !== $tag ) {
				$allowed[ $tag ] = array_merge( $attributes, $paint );
			}
		}

		// `wp_kses` drops a disallowed tag but keeps the text between its ends, so `<style>@import
		// url(…)</style>` survives as a bare `@import url(…)` in the output. Inert where it lands —
		// there is no element left to interpret it as CSS — but it is somebody's URL sitting in an
		// admin payload, and the tidiest answer is that container elements lose their contents too.
		$markup = (string) preg_replace(
			'#<\s*(script|style|foreignObject)\b[^>]*>.*?<\s*/\s*\1\s*>#is',
			'',
			$markup
		);

		$clean = trim( wp_kses( $markup, $allowed ) );

		// `wp_kses` drops disallowed tags but keeps their text, so a file that was mostly script
		// can survive as a bare `<svg>` wrapper around nothing. Require an actual shape.
		if ( 1 !== preg_match( '/<(path|circle|ellipse|rect|line|polyline|polygon)\b/i', $clean ) ) {
			return '';
		}

		return $clean;
	}

	/**
	 * Read the downloaded manifests, in the order the index lists them.
	 *
	 * The index carries a summary; each recipe's own `recipe.json` is still the authority on its
	 * plan and parameters, so a third party can copy one directory and have a whole recipe. The
	 * index says what exists, the manifest says what it does.
	 *
	 * @since 1.2.0
	 *
	 * @return array<int, array<string, mixed>> Decoded manifests.
	 */
	private function bundled_manifests(): array {
		$manifests = array();

		foreach ( self::index() as $entry ) {
			$id = isset( $entry['id'] ) ? sanitize_key( (string) $entry['id'] ) : '';

			if ( '' === $id ) {
				continue;
			}

			$path = self::directory() . $id . '/recipe.json';

			if ( ! is_readable( $path ) ) {
				continue;
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading a downloaded JSON file; WP_Filesystem needs a request context this can be called outside of.
			$manifest = json_decode( (string) file_get_contents( $path ), true );

			if ( is_array( $manifest ) ) {
				$manifests[] = $manifest;
			}
		}

		return $manifests;
	}
}
