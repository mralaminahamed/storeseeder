<?php
/**
 * Tests for the upload-directory paths.
 *
 * @package StoreSeeder\Tests
 */

namespace StoreSeeder\Tests;

use StoreSeeder;
use StoreSeeder\Recipes\Registry as Recipe_Registry;
use StoreSeeder\Storage;

/**
 * Where downloaded files live.
 *
 * These assert *agreement* rather than particular strings wherever they can, because the failure this
 * class exists to prevent is not a wrong path — it is two right-looking paths that stopped matching.
 * That has already happened once: the recipe directory was built independently by the registry and by
 * the generator, and when recipes moved out of the plugin only one of them followed. Nothing crashed.
 * Every recipe ran to completion on default vocabulary, and the audit reported no issues because it was
 * reading the correct path while the generator read the stale one.
 *
 * @covers \StoreSeeder\Storage
 */
class StorageTest extends StoreSeederUnitTestCase {

	/**
	 * Everything sits under one directory.
	 */
	public function test_everything_is_under_one_base_directory(): void {
		$base = Storage::base();

		$this->assertStringEndsWith( '/storeseeder/', $base );
		$this->assertStringStartsWith( $base, Storage::recipes() );
		$this->assertStringStartsWith( $base, Storage::sample_data() );
	}

	/**
	 * The structure is the one the layout promises.
	 */
	public function test_paths_have_the_documented_shape(): void {
		$this->assertStringEndsWith( '/storeseeder/recipes/', Storage::recipes() );
		$this->assertStringEndsWith( '/storeseeder/sample-data/fluent-cart', Storage::sample_data() );
	}

	/**
	 * Recipes resolve through Storage, not through a path of the registry's own.
	 *
	 * The agreement that broke before. `Registry::directory()` is what everything else asks, so it has
	 * to be the same directory Storage names.
	 */
	public function test_recipe_registry_uses_storage(): void {
		$this->assertSame( Storage::recipes(), Recipe_Registry::directory() );
	}

	/**
	 * A generator looks for vocabulary where the download puts it.
	 *
	 * The other half of the same pair: `class-storeseeder.php` downloads into
	 * `get_sample_data_directory()` and the generator reads from its own candidate list. If those two
	 * disagree, every locale silently falls back to each generator's inline defaults — which is exactly
	 * the bug that had 72 of 73 locales producing "Premium Widget".
	 */
	public function test_sample_data_download_and_read_paths_agree(): void {
		$plugin = StoreSeeder::get_instance();

		$this->assertSame( Storage::sample_data(), $plugin->get_sample_data_directory() );
	}

	/**
	 * A separate archive gets a separate directory.
	 *
	 * `storeseeder_sample_data_source` exists so a platform can ship reference data of its own. With one
	 * shared directory two archives could not coexist — whichever synced last would overwrite the other.
	 */
	public function test_each_archive_gets_its_own_directory(): void {
		$this->assertNotSame( Storage::sample_data( 'fluent-cart' ), Storage::sample_data( 'my-store' ) );
		$this->assertStringEndsWith( '/sample-data/my-store', Storage::sample_data( 'my-store' ) );
	}

	/**
	 * The archive name reaches a filesystem path, so it is constrained to one segment.
	 *
	 * @dataProvider hostile_archive_names
	 *
	 * @param string $archive Untrusted archive name.
	 */
	public function test_archive_name_cannot_escape_the_sample_data_directory( string $archive ): void {
		$path = Storage::sample_data( $archive );

		$this->assertStringNotContainsString( '..', $path );
		$this->assertStringStartsWith( Storage::base() . 'sample-data/', $path );
	}

	/**
	 * Names a filter could plausibly return, deliberately or by accident.
	 *
	 * @return array<string, array{string}>
	 */
	public function hostile_archive_names(): array {
		return array(
			'traversal'          => array( '../../../etc' ),
			'traversal segments' => array( 'a/../../b' ),
			'absolute'           => array( '/etc/passwd' ),
			'empty'              => array( '   ' ),
			'dots only'          => array( '..' ),
			'uppercase'          => array( 'Fluent-Cart' ),
		);
	}

	/**
	 * The filter is honoured for a name that is legitimate.
	 */
	public function test_archive_filter_is_honoured(): void {
		add_filter( 'storeseeder_sample_data_archive', static fn (): string => 'easycommerce' );

		$this->assertStringEndsWith( '/sample-data/easycommerce', Storage::sample_data() );
	}

	/**
	 * Every legacy path maps onto a path Storage names today.
	 *
	 * Otherwise the migration would move a directory somewhere nothing reads from, which is worse than
	 * not migrating: the files exist, the plugin cannot see them, and the only symptom is placeholder
	 * product names.
	 */
	public function test_legacy_paths_map_onto_current_ones(): void {
		$current = array( untrailingslashit( Storage::recipes() ), Storage::sample_data() );

		foreach ( array_keys( Storage::legacy_paths() ) as $destination ) {
			$this->assertContains( $destination, $current );
		}
	}

	/**
	 * The legacy paths are the ones that actually shipped.
	 */
	public function test_legacy_paths_are_the_pre_1_2_locations(): void {
		$legacy = array_values( Storage::legacy_paths() );

		$this->assertStringEndsWith( '/storeseeder-recipes', $legacy[0] );
		$this->assertStringEndsWith( '/storeseeder-sample-data-fluent-cart', $legacy[1] );

		// At the uploads root, which is the thing being moved away from.
		foreach ( $legacy as $path ) {
			$this->assertStringNotContainsString( '/storeseeder/', $path );
		}
	}
}
