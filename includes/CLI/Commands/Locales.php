<?php
/**
 * `wp storeseeder locales`
 *
 * @since   1.1.0
 * @package StoreSeeder\CLI
 */

namespace StoreSeeder\CLI\Commands;

use StoreSeeder\CLI\Command;
use StoreSeeder\Platforms\Locale;

defined( 'ABSPATH' ) || exit;

/**
 * List the locales data can be generated in.
 *
 * Reads the same list the REST enum and the admin picker read, so what this prints is
 * exactly what `--locale` will accept.
 *
 * @since 1.1.0
 */
final class Locales extends Command {
	const NAME = 'locales';

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public static function shortdesc(): string {
		return __( 'List the locales data can be generated in.', 'storeseeder' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function synopsis(): array {
		return array(
			array(
				'type'        => 'assoc',
				'name'        => 'search',
				'description' => __( 'Filter by language name or locale code.', 'storeseeder' ),
				'optional'    => true,
			),
			array(
				'type'        => 'assoc',
				'name'        => 'format',
				'description' => __( 'Output format.', 'storeseeder' ),
				'optional'    => true,
				'default'     => 'table',
				'options'     => array( 'table', 'json', 'csv', 'yaml', 'ids' ),
			),
		);
	}

	/**
	 * Run it.
	 *
	 * ## EXAMPLES
	 *
	 *     wp storeseeder locales
	 *     wp storeseeder locales --search=german
	 *     wp storeseeder locales --format=ids
	 *
	 * @since 1.1.0
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Flags.
	 *
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ) {
		// No access check: this only lists what the plugin can do, writes nothing, and is
		// the command someone runs *before* working out which user to pass.
		$rows = self::rows( isset( $assoc_args['search'] ) ? (string) $assoc_args['search'] : '' );

		if ( array() === $rows ) {
			\WP_CLI::warning(
				sprintf(
					/* translators: %s: the search term. */
					__( 'No locale matches "%s".', 'storeseeder' ),
					isset( $assoc_args['search'] ) ? $assoc_args['search'] : ''
				)
			);

			return;
		}

		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';

		if ( 'ids' === $format ) {
			\WP_CLI::line( implode( ' ', wp_list_pluck( $rows, 'code' ) ) );

			return;
		}

		\WP_CLI\Utils\format_items( $format, $rows, array( 'code', 'label', 'default' ) );
	}

	/**
	 * The locale rows, optionally filtered.
	 *
	 * Matches code as well as label, for the same reason the admin picker does: someone who
	 * knows `ja_JP` should not have to guess how the label is spelled.
	 *
	 * @since 1.1.0
	 *
	 * @param string $search Search term, or '' for everything.
	 *
	 * @return array<int, array<string, string>>
	 */
	public static function rows( string $search = '' ): array {
		$needle = strtolower( trim( $search ) );
		$rows   = array();

		foreach ( Locale::all() as $code => $label ) {
			if (
				'' !== $needle
				&& false === strpos( strtolower( $label ), $needle )
				&& false === strpos( strtolower( $code ), $needle )
			) {
				continue;
			}

			$rows[] = array(
				'code'    => $code,
				'label'   => $label,
				'default' => Locale::DEFAULT_LOCALE === $code ? '*' : '',
			);
		}

		return $rows;
	}
}
