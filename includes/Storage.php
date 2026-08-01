<?php
/**
 * Where StoreSeeder keeps downloaded files.
 *
 * @since      1.2.0
 * @package    StoreSeeder
 */

namespace StoreSeeder;

defined( 'ABSPATH' ) || exit;

/**
 * The one place any path under `wp-content/uploads` is decided.
 *
 * Two things made this worth extracting rather than leaving the paths where they were used.
 *
 * **They were computed in five places, and two of them were the same value.** The sample-data
 * directory was built by `class-storeseeder.php` for downloading and syncing, and independently by
 * `Generation\Generator` for reading — so moving it in one would have left the other reading an empty
 * directory and falling silently back to each generator's inline defaults. That is not hypothetical:
 * it is exactly what happened to the recipe path when recipes moved out of the plugin, and the audit
 * that should have caught it was reading the correct path while the generator read the stale one.
 *
 * **The layout said the wrong thing.** Both directories sat at the uploads root, and the sample-data
 * one was named after its *repository* — `storeseeder-sample-data-fluent-cart` — so a site had two
 * unrelated-looking StoreSeeder directories beside everybody else's media, one of them advertising a
 * platform. Now:
 *
 *     uploads/storeseeder/
 *       recipes/                     recipes.json + one directory per recipe
 *       sample-data/<archive>/       one directory per reference-data archive
 *
 * The `<archive>` level is not decoration. `storeseeder_sample_data_source` exists so a platform can
 * ship reference data of its own — the shipped archive holds names and addresses that suit a different
 * store only by accident — and with one shared directory two archives could not coexist: whichever
 * synced last would overwrite the other, and nothing would say so.
 *
 * Being under `uploads/` these files are web-reachable, as they were before. They are vocabulary —
 * word lists and numeric bands — so there is nothing here that was not already public in the archive
 * it came from.
 */
final class Storage {

	/** The single directory everything lives under, inside `uploads`. */
	const DIRNAME = 'storeseeder';

	/**
	 * The shipped reference-data archive's directory name.
	 *
	 * Named for the archive rather than for the resolved platform, which matters: a WooCommerce site
	 * downloads this same archive, because the vocabulary in it is platform-neutral even though the
	 * repository's name is not. Keying the directory on the resolved platform would have sent that
	 * site looking in an empty `woo-commerce/` and silently produced "Premium Widget" again.
	 */
	const DEFAULT_ARCHIVE = 'fluent-cart';

	/** Set once `migrate()` has run, so it does not walk the filesystem on every request. */
	const MIGRATED_OPTION = 'storeseeder_uploads_migrated';

	/**
	 * The base directory, with a trailing slash.
	 *
	 * @since 1.2.0
	 *
	 * @return string
	 */
	public static function base(): string {
		$uploads = wp_upload_dir();

		return trailingslashit( $uploads['basedir'] ) . self::DIRNAME . '/';
	}

	/**
	 * Where downloaded recipes live, with a trailing slash.
	 *
	 * `Recipes\Registry` is the only caller that should reach past this — ask it for a recipe's
	 * vocabulary directory rather than deriving one, for the reason in this class's own docblock.
	 *
	 * @since 1.2.0
	 *
	 * @return string
	 */
	public static function recipes(): string {
		return self::base() . 'recipes/';
	}

	/**
	 * Where a reference-data archive lives, without a trailing slash.
	 *
	 * No trailing slash because every caller joins a resource and a locale onto it and the existing
	 * ones already write the separator themselves.
	 *
	 * @since 1.2.0
	 *
	 * @param string $archive Archive directory name. Defaults to the shipped one.
	 *
	 * @return string
	 */
	public static function sample_data( string $archive = '' ): string {
		if ( '' === $archive ) {
			/**
			 * Filters the directory name the reference-data archive is stored under.
			 *
			 * Change this **whenever you change `storeseeder_sample_data_source`**. The two describe
			 * one thing from either end — where the archive comes from, and where it lands — and a
			 * fork pointed at a different repository while still writing into the shipped archive's
			 * directory would overwrite it, with the only symptom being product names from the wrong
			 * shop.
			 *
			 * @since 1.2.0
			 *
			 * @param string $archive Directory name, `[a-z0-9_-]` only.
			 */
			$archive = (string) apply_filters( 'storeseeder_sample_data_archive', self::DEFAULT_ARCHIVE );
		}

		// A filtered value reaches a path, so it is constrained to a single segment: `sanitize_key()`
		// alone still permits `..` through underscores in some inputs, and an empty result would
		// collapse the archive level entirely.
		$archive = preg_replace( '/[^a-z0-9_-]/', '', strtolower( $archive ) );

		if ( ! is_string( $archive ) || '' === $archive ) {
			$archive = self::DEFAULT_ARCHIVE;
		}

		return untrailingslashit( self::base() . 'sample-data/' . $archive );
	}

	/**
	 * Every directory a reference-data archive might be readable from, most current first.
	 *
	 * For readers only — a writer must use `sample_data()`, which names exactly one place.
	 *
	 * The legacy path is here because `migrate()` can fail: a site whose filesystem needs FTP or SSH
	 * credentials that are not configured cannot move anything, and without this the reader would then
	 * look in a new empty directory and fall silently back to each generator's inline word lists. The
	 * symptom would be every product called "Premium Widget" again, with nothing logged — which is the
	 * defect this whole class exists to prevent, reintroduced by the fix for it.
	 *
	 * @since 1.2.0
	 *
	 * @param string $archive Archive directory name. Defaults to the shipped one.
	 *
	 * @return string[] Absolute paths without trailing slashes. Never empty.
	 */
	public static function sample_data_dirs( string $archive = '' ): array {
		$current = self::sample_data( $archive );
		$dirs    = array( $current );

		foreach ( self::legacy_paths() as $new => $legacy ) {
			if ( $new === $current ) {
				$dirs[] = $legacy;
			}
		}

		return $dirs;
	}

	/**
	 * Create a directory, and keep it from being listed.
	 *
	 * The blank `index.php` is what every WordPress upload directory carries: it costs nothing and it
	 * stops a server with autoindex enabled from publishing a browsable list of everything downloaded.
	 *
	 * @since 1.2.0
	 *
	 * @param string $path Absolute directory path.
	 *
	 * @return bool Whether the directory exists afterwards.
	 */
	public static function ensure( string $path ): bool {
		if ( ! wp_mkdir_p( $path ) ) {
			return false;
		}

		$guard = trailingslashit( $path ) . 'index.php';

		if ( ! file_exists( $guard ) ) {
			$filesystem = self::filesystem();

			if ( $filesystem instanceof \WP_Filesystem_Base ) {
				$filesystem->put_contents( $guard, "<?php\n// Silence is golden.\n", FS_CHMOD_FILE );
			}
		}

		return true;
	}

	/**
	 * The initialised `WP_Filesystem`, or null when it cannot be had.
	 *
	 * Through `WP_Filesystem` rather than `rename()` and `file_put_contents()` because that is how the
	 * rest of the plugin writes — it respects a site configured for FTP or SSH access, where the direct
	 * calls fail and silencing the failure is how you get a migration that reports success and moved
	 * nothing.
	 *
	 * @since 1.2.0
	 *
	 * @return \WP_Filesystem_Base|null
	 */
	private static function filesystem() {
		global $wp_filesystem;

		if ( ! $wp_filesystem instanceof \WP_Filesystem_Base ) {
			if ( ! function_exists( 'WP_Filesystem' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}

			WP_Filesystem();
		}

		return $wp_filesystem instanceof \WP_Filesystem_Base ? $wp_filesystem : null;
	}

	/**
	 * The pre-1.2.0 locations, which sat at the uploads root.
	 *
	 * @since 1.2.0
	 *
	 * @return array<string, string> New path => legacy path.
	 */
	public static function legacy_paths(): array {
		$uploads = wp_upload_dir();
		$base    = trailingslashit( $uploads['basedir'] );

		return array(
			untrailingslashit( self::recipes() )       => $base . 'storeseeder-recipes',
			self::sample_data( self::DEFAULT_ARCHIVE ) => $base . 'storeseeder-sample-data-fluent-cart',
		);
	}

	/**
	 * Move anything at a legacy path to its new home, once.
	 *
	 * Runs from the same `admin_init` hook the ledger's own upgrade uses, and records that it has run
	 * so it does not stat the filesystem on every request.
	 *
	 * A move rather than a re-download: both archives are re-downloadable, but a site that has already
	 * consented should not have to consent again or wait for a fetch because the plugin tidied its own
	 * directory. If the move fails — permissions, or a destination that somehow exists — the legacy
	 * directory is left exactly where it is and the flag is not set, so the next request tries again
	 * and the worst case is that an administrator presses **Force re-sync**.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public static function migrate(): void {
		if ( '1' === (string) get_option( self::MIGRATED_OPTION, '' ) ) {
			return;
		}

		$filesystem = self::filesystem();

		if ( ! $filesystem instanceof \WP_Filesystem_Base ) {
			return;
		}

		$moved_all = true;

		foreach ( self::legacy_paths() as $new => $legacy ) {
			if ( ! is_dir( $legacy ) ) {
				continue;
			}

			// A new path that already holds something is the ambiguous case, and guessing which copy
			// is current would be the wrong kind of clever. Leave both and let a re-sync settle it.
			if ( is_dir( $new ) ) {
				$moved_all = false;

				continue;
			}

			self::ensure( dirname( $new ) );

			// `$overwrite` stays false: the destination is known not to exist, and passing true here
			// would turn the ambiguous case above into a silent data loss if the guard ever moved.
			if ( ! $filesystem->move( $legacy, $new, false ) ) {
				$moved_all = false;
			}
		}

		if ( $moved_all ) {
			update_option( self::MIGRATED_OPTION, '1', true );
		}
	}
}
