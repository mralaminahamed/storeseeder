<?php
/**
 * `wp storeseeder recipe`
 *
 * @since   1.2.0
 * @package StoreSeeder\CLI
 */

namespace StoreSeeder\CLI\Commands;

use StoreSeeder\CLI\Command;
use StoreSeeder\Generation\Ledger;
use StoreSeeder\Platforms\Locale;
use StoreSeeder\Recipes\Registry as Recipe_Registry;

defined( 'ABSPATH' ) || exit;

/**
 * List the store recipes, or build one.
 *
 * The admin page runs a recipe as a sequence of ordinary generate requests, and so does this — one
 * dispatch per chunk, in the recipe's own order. There is no separate server-side runner to keep
 * in step, which is the point: a recipe is a *plan* over the existing endpoints, not a second way
 * to write rows.
 *
 * @since 1.2.0
 */
final class Recipe extends Command {
	const NAME = 'recipe';

	/**
	 * How many rows one request may create. Mirrors the endpoint's own cap.
	 *
	 * @since 1.2.0
	 * @var int
	 */
	const CHUNK = 100;

	/**
	 * How each size scales a recipe's declared counts.
	 *
	 * @since 1.2.0
	 * @var array<string, float>
	 */
	const SIZES = array(
		'small'  => 0.25,
		'medium' => 1.0,
		'large'  => 4.0,
	);

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.2.0
	 *
	 * @return string
	 */
	public static function shortdesc(): string {
		return __( 'List the store recipes, or build one.', 'storeseeder' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * WP-CLI rejects any flag the synopsis does not declare, so every one a caller might reach
	 * for has to be here — a missing entry does not warn, it simply never arrives.
	 *
	 * @since 1.2.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function synopsis(): array {
		return array(
			array(
				'type'        => 'positional',
				'name'        => 'action',
				'description' => __( 'list or run.', 'storeseeder' ),
				'optional'    => true,
				'default'     => 'list',
				'options'     => array( 'list', 'run' ),
			),
			array(
				'type'        => 'positional',
				'name'        => 'recipe',
				'description' => __( 'Recipe id, when running one.', 'storeseeder' ),
				'optional'    => true,
			),
			array(
				'type'        => 'assoc',
				'name'        => 'size',
				'description' => __( 'small, medium or large. Scales the recipe\'s declared counts.', 'storeseeder' ),
				'optional'    => true,
				'default'     => 'medium',
				'options'     => array( 'small', 'medium', 'large' ),
			),
			array(
				'type'        => 'assoc',
				'name'        => 'platform',
				'description' => __( 'Platform to write to. Defaults to the resolved target.', 'storeseeder' ),
				'optional'    => true,
			),
			array(
				'type'        => 'assoc',
				'name'        => 'locale',
				'description' => __( 'Locale for generated data.', 'storeseeder' ),
				'optional'    => true,
			),
		);
	}

	/**
	 * Run it.
	 *
	 * ## EXAMPLES
	 *
	 *     wp storeseeder recipe --user=1
	 *     wp storeseeder recipe run grocery --user=1
	 *     wp storeseeder recipe run fashion --size=small --platform=woocommerce --user=1
	 *
	 * @since 1.2.0
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Flags.
	 *
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ) {
		$this->require_access();

		$action = isset( $args[0] ) ? $args[0] : 'list';

		if ( 'run' !== $action ) {
			$this->show_list();

			return;
		}

		$id     = isset( $args[1] ) ? sanitize_key( $args[1] ) : '';
		$recipe = Recipe_Registry::instance()->get( $id );

		if ( null === $recipe ) {
			\WP_CLI::error(
				sprintf(
					/* translators: 1: requested recipe id, 2: comma-separated list of ids. */
					__( 'No recipe called "%1$s". Available: %2$s', 'storeseeder' ),
					$id,
					implode( ', ', array_keys( Recipe_Registry::instance()->all() ) )
				)
			);

			// Unreachable: WP_CLI::error() exits. Said explicitly so static analysis does not
			// read every line below as operating on a null recipe.
			return;
		}

		$this->build( $recipe, $assoc_args );
	}

	/**
	 * Print the registered recipes.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	private function show_list(): void {
		$rows = array();

		foreach ( Recipe_Registry::instance()->all() as $recipe ) {
			$total = 0;

			foreach ( $recipe->plan() as $entry ) {
				$total += $entry['count'];
			}

			$rows[] = array(
				'id'      => $recipe->id(),
				'name'    => $recipe->name(),
				'steps'   => count( $recipe->plan() ),
				'rows'    => $total,
				'locales' => implode( ',', $recipe->locales() ),
			);
		}

		if ( array() === $rows ) {
			\WP_CLI::line( __( 'No recipes are registered.', 'storeseeder' ) );

			return;
		}

		\WP_CLI\Utils\format_items( 'table', $rows, array( 'id', 'name', 'steps', 'rows', 'locales' ) );
	}

	/**
	 * Build one recipe.
	 *
	 * @since 1.2.0
	 *
	 * @param \StoreSeeder\Recipes\Recipe $recipe     The recipe.
	 * @param array<string, string>       $assoc_args Flags.
	 *
	 * @return void
	 */
	private function build( \StoreSeeder\Recipes\Recipe $recipe, array $assoc_args ): void {
		$size       = isset( $assoc_args['size'] ) ? (string) $assoc_args['size'] : 'medium';
		$multiplier = self::SIZES[ $size ] ?? 1.0;
		$locale     = isset( $assoc_args['locale'] ) ? (string) $assoc_args['locale'] : Locale::DEFAULT_LOCALE;
		$run_id     = 'rcp_' . $recipe->id() . '_' . substr( (string) wp_generate_password( 12, false ), 0, 10 );

		// Said before the work rather than after: a recipe that ships no vocabulary for this
		// locale still runs, and its product names will be English. Silence is what let the
		// locale picker offer seventy-three and deliver one.
		if ( ! $recipe->has_locale( $locale ) ) {
			\WP_CLI::warning(
				sprintf(
					/* translators: 1: requested locale, 2: recipe name, 3: fallback locale. */
					__( '%2$s ships no vocabulary for %1$s; names and addresses will be local but product titles come from %3$s.', 'storeseeder' ),
					$locale,
					$recipe->name(),
					Locale::DEFAULT_LOCALE
				)
			);
		}

		$written = 0;
		$failed  = array();

		foreach ( $recipe->plan() as $entry ) {
			$route = $this->route_for( $entry['resource'] );

			if ( '' === $route ) {
				continue;
			}

			$wanted = max( 1, (int) round( $entry['count'] * $multiplier ) );
			$left   = $wanted;

			\WP_CLI::line(
				sprintf(
					/* translators: 1: resource name, 2: how many rows. */
					__( '%1$s — %2$d', 'storeseeder' ),
					$entry['resource'],
					$wanted
				)
			);

			while ( $left > 0 ) {
				$count = min( self::CHUNK, $left );
				$body  = array(
					'count'      => $count,
					'locale'     => $locale,
					'recipe'     => $recipe->id(),
					'recipe_run' => $run_id,
				);

				if ( isset( $assoc_args['platform'] ) ) {
					$body['platform'] = (string) $assoc_args['platform'];
				}

				$result = $this->dispatch( 'POST', "/{$route}/generate", $body );

				if ( is_wp_error( $result ) ) {
					// One message per resource, not per chunk: a driver that refuses the first
					// hundred refuses all of them, identically.
					$failed[ $entry['resource'] ] = $result->get_error_message();
					break;
				}

				$written += $count;
				$left    -= $count;
			}
		}

		foreach ( $failed as $resource_type => $message ) {
			\WP_CLI::warning( "{$resource_type}: {$message}" );
		}

		\WP_CLI::success(
			sprintf(
				/* translators: 1: recipe name, 2: rows written, 3: ledger run id. */
				__( '%1$s built — %2$d rows. Undo with: wp storeseeder cleanup --run_id=%3$s', 'storeseeder' ),
				$recipe->name(),
				$written,
				$run_id
			)
		);

		// Belt and braces. The controller clears it too, but a WP-CLI process outlives a
		// request, and a leaked run id would file later rows under a recipe someone could undo.
		Ledger::set_run( '' );
	}

	/**
	 * The REST base that generates a resource, or '' when nothing does.
	 *
	 * Looked up rather than derived: `cart_session` is served at `cart-sessions`, and no
	 * singularisation rule survives `shipping_classes`.
	 *
	 * @since 1.2.0
	 *
	 * @param string $resource_type Canonical resource name.
	 *
	 * @return string
	 */
	private function route_for( string $resource_type ): string {
		foreach ( \StoreSeeder\Rest\Registry::instance()->all() as $controller ) {
			if ( $controller->resource_type() === $resource_type ) {
				return $controller->rest_base();
			}
		}

		return '';
	}
}
