<?php
/**
 * Tests for the recipe registry, its audit, and the SVG filter.
 *
 * The registry reads a *downloaded* archive, so every input here is content the plugin did not
 * write. The audit exists because those inputs fail quietly — a recipe with no vocabulary produces
 * a store, not an error — and the icon filter exists because one of them is markup that renders in
 * wp-admin.
 *
 * @package StoreSeeder\Tests
 */

namespace StoreSeeder\Tests\Recipes;

use StoreSeeder\Platforms\Resource;
use StoreSeeder\Recipes\Recipe;
use StoreSeeder\Recipes\Registry;
use StoreSeeder\Tests\StoreSeederUnitTestCase;

/**
 * @covers \StoreSeeder\Recipes\Registry
 */
class RegistryTest extends StoreSeederUnitTestCase {

	/**
	 * Filters and temporary files only — no REST server, no platform plugins, no database.
	 *
	 * @var bool
	 */
	protected bool $is_unit_test = true;

	/**
	 * A throwaway directory standing in for a downloaded archive.
	 *
	 * @var string
	 */
	private string $root = '';

	public function setUp(): void {
		parent::setUp();

		$this->root = trailingslashit( sys_get_temp_dir() ) . 'storeseeder-recipes-test-' . wp_generate_password( 8, false );

		wp_mkdir_p( $this->root );

		add_filter( 'storeseeder_recipe_directories', array( $this, 'point_at_fixture' ) );

		Registry::instance()->reset();
	}

	/**
	 * `tear_down`, not `tearDown`: the WordPress base marks the camelCase pair final, and
	 * overriding one is a compile-time fatal that PHPUnit reports as exit 255 with no message at
	 * all.
	 *
	 * @return void
	 */
	public function tear_down() {
		remove_filter( 'storeseeder_recipe_directories', array( $this, 'point_at_fixture' ) );
		remove_all_filters( 'storeseeder_recipes' );

		$this->discard( $this->root );

		Registry::instance()->reset();

		parent::tear_down();
	}

	/**
	 * Send every lookup to the fixture instead of the uploads directory.
	 *
	 * @return array<string, string>
	 */
	public function point_at_fixture(): array {
		return array( 'grocery' => $this->root . '/grocery' );
	}

	/**
	 * Delete the fixture tree.
	 *
	 * Not called `rmdir`: `WP_UnitTestCase_Base` already declares a public `rmdir()`, and
	 * redeclaring it private narrows the visibility — a compile-time fatal that PHPUnit surfaces
	 * as exit 255 with no message and no entry in the error log.
	 *
	 * @param string $dir Directory to remove.
	 *
	 * @return void
	 */
	private function discard( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		foreach ( array_diff( (array) scandir( $dir ), array( '.', '..' ) ) as $entry ) {
			$path = $dir . '/' . $entry;

			is_dir( $path ) ? $this->discard( $path ) : unlink( $path );
		}

		rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- test fixture.
	}

	private function write( string $relative, string $contents ): void {
		$path = $this->root . '/' . $relative;

		wp_mkdir_p( dirname( $path ) );
		file_put_contents( $path, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- test fixture.
	}

	/**
	 * @param string[] $locales Locales the manifest claims.
	 */
	private function grocery( array $locales = array( 'en_US' ) ): Recipe {
		$recipe = Recipe::from_manifest(
			array(
				'id'      => 'grocery',
				'name'    => 'Corner grocer',
				'locales' => $locales,
				'plan'    => array(
					array(
						'resource' => Resource::PRODUCT,
						'count'    => 10,
					),
					array(
						'resource' => Resource::PRODUCT_CATEGORY,
						'count'    => 4,
					),
					// No vocabulary can speak for an order — it is built from what already exists,
					// so the audit must not ask for words that could never be shipped.
					array(
						'resource' => Resource::ORDER,
						'count'    => 40,
					),
				),
			)
		);

		$this->assertNotNull( $recipe );

		return $recipe;
	}

	// -----------------------------------------------------------------------------------
	// The filter, which is the whole extension surface.
	// -----------------------------------------------------------------------------------

	public function test_a_filter_can_register_a_recipe(): void {
		add_filter(
			'storeseeder_recipes',
			static function ( $manifests ) {
				$manifests[] = array(
					'id'   => 'bakery',
					'name' => 'Village bakery',
					'plan' => array(
						array(
							'resource' => Resource::PRODUCT,
							'count'    => 30,
						),
					),
				);

				return $manifests;
			}
		);

		Registry::instance()->reset();

		$this->assertTrue( Registry::instance()->has( 'bakery' ) );
		$this->assertSame( 'Village bakery', Registry::instance()->get( 'bakery' )->name() );
	}

	/**
	 * A third-party recipe that will not parse must not take the admin down with it — the registry
	 * is read on a page load, not in a background job.
	 */
	public function test_a_malformed_registration_is_dropped_not_fatal(): void {
		add_filter(
			'storeseeder_recipes',
			static function ( $manifests ) {
				return array( 'nonsense', 42, null, array( 'id' => 'no-name' ) );
			}
		);

		Registry::instance()->reset();

		$this->assertSame( array(), Registry::instance()->all() );
	}

	public function test_a_filter_returning_a_non_array_is_discarded(): void {
		add_filter( 'storeseeder_recipes', '__return_false' );

		Registry::instance()->reset();

		$this->assertSame( array(), Registry::instance()->all() );
	}

	// -----------------------------------------------------------------------------------
	// The audit. Every case here produces data rather than an error.
	// -----------------------------------------------------------------------------------

	/**
	 * The one blocking case: a recipe with no words builds a shop that misrepresents itself, and
	 * looks entirely successful while doing it.
	 */
	public function test_no_vocabulary_at_all_blocks(): void {
		$issues = Registry::audit( $this->grocery(), 'en_US' );

		$this->assertCount( 1, $issues );
		$this->assertSame( 'storeseeder_recipe_no_vocabulary', $issues[0]['code'] );
		$this->assertTrue( $issues[0]['blocking'] );
	}

	public function test_a_complete_recipe_reports_nothing(): void {
		$this->write( 'grocery/products/en_US/product_names.json', '{"products":["Oats"]}' );
		$this->write( 'grocery/product_categories/en_US/departments.json', '{"departments":["Bakery"]}' );

		$this->assertSame( array(), Registry::audit( $this->grocery(), 'en_US' ) );
	}

	/**
	 * A resource in the plan with no words behind it keeps the default vocabulary — the shop is
	 * right and its categories are generic, which is worth saying but not worth refusing.
	 */
	public function test_a_resource_with_no_words_is_reported_and_not_blocking(): void {
		$this->write( 'grocery/products/en_US/product_names.json', '{"products":["Oats"]}' );

		$issues = Registry::audit( $this->grocery(), 'en_US' );

		$this->assertCount( 1, $issues );
		$this->assertSame( 'storeseeder_recipe_partial', $issues[0]['code'] );
		$this->assertFalse( $issues[0]['blocking'] );
		$this->assertStringContainsString( Resource::PRODUCT_CATEGORY, $issues[0]['message'] );
		// Orders carry no vocabulary by nature, so asking for theirs would be a permanent warning.
		$this->assertStringNotContainsString( Resource::ORDER, $issues[0]['message'] );
	}

	/**
	 * The silence this exists to break: a manifest listing a locale it does not ship would present
	 * as a translated recipe and serve English.
	 */
	public function test_a_claimed_locale_that_is_not_shipped_is_reported(): void {
		$this->write( 'grocery/products/en_US/product_names.json', '{"products":["Oats"]}' );
		$this->write( 'grocery/product_categories/en_US/departments.json', '{"departments":["Bakery"]}' );

		$issues = Registry::audit( $this->grocery( array( 'en_US', 'de_DE' ) ), 'de_DE' );

		$this->assertCount( 1, $issues );
		$this->assertSame( 'storeseeder_recipe_locale_incomplete', $issues[0]['code'] );
		$this->assertFalse( $issues[0]['blocking'] );
	}

	/**
	 * Falling back for a locale the recipe never advertised is documented behaviour, and the card
	 * already prints which locales it ships. Warning about it too would be noise on every run.
	 */
	public function test_a_locale_it_never_claimed_is_not_reported(): void {
		$this->write( 'grocery/products/en_US/product_names.json', '{"products":["Oats"]}' );
		$this->write( 'grocery/product_categories/en_US/departments.json', '{"departments":["Bakery"]}' );

		$this->assertSame( array(), Registry::audit( $this->grocery(), 'ja_JP' ) );
	}

	public function test_a_resource_manifest_is_preferred_over_the_directories(): void {
		$this->write( 'grocery/products/en_US/product_names.json', '{"products":["Oats"]}' );
		$this->write( 'grocery/product_categories/en_US/departments.json', '{"departments":["Bakery"]}' );
		// Claims a locale whose directory is absent — the manifest is authoritative, so this must
		// count as shipped and produce no locale warning.
		$this->write( 'grocery/products/manifest.json', '{"resource":"products","locales":["en_US","de_DE"]}' );
		$this->write( 'grocery/product_categories/manifest.json', '{"resource":"product_categories","locales":["en_US","de_DE"]}' );

		$this->assertSame( array(), Registry::audit( $this->grocery( array( 'en_US', 'de_DE' ) ), 'de_DE' ) );
	}

	// -----------------------------------------------------------------------------------
	// The icon filter. This one renders in wp-admin.
	// -----------------------------------------------------------------------------------

	public function test_a_plain_mark_survives(): void {
		$this->write( 'grocery/icon.svg', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><circle cx="24" cy="24" r="20" fill="#16a34a"/></svg>' );

		$uri = Registry::icon_uri( 'grocery' );

		$this->assertStringStartsWith( 'data:image/svg+xml;base64,', $uri );
		$this->assertStringContainsString( '<circle', base64_decode( substr( $uri, 26 ) ) );
	}

	/**
	 * @dataProvider hostile_marks
	 *
	 * @param string $svg      The markup.
	 * @param string $forbidden What must not survive.
	 */
	public function test_hostile_markup_does_not_survive( string $svg, string $forbidden ): void {
		$this->write( 'grocery/icon.svg', $svg );

		$uri = Registry::icon_uri( 'grocery' );
		$out = '' === $uri ? '' : (string) base64_decode( substr( $uri, 26 ) );

		$this->assertStringNotContainsStringIgnoringCase( $forbidden, $out, $forbidden );
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public function hostile_marks(): array {
		$wrap = static function ( string $inner ): string {
			return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48">' . $inner . '<path d="M0 0h48v48H0z"/></svg>';
		};

		return array(
			'script tag'      => array( $wrap( '<script>alert(1)</script>' ), '<script' ),
			'event handler'   => array( $wrap( '<circle cx="1" cy="1" r="1" onload="alert(1)"/>' ), 'onload' ),
			'foreign object'  => array( $wrap( '<foreignObject><body xmlns="http://www.w3.org/1999/xhtml"><script>alert(1)</script></body></foreignObject>' ), 'foreignObject' ),
			'external image'  => array( $wrap( '<image href="https://example.test/x.png"/>' ), '<image' ),
			'use reference'   => array( $wrap( '<use href="https://example.test/x.svg#a"/>' ), '<use' ),
			'embedded style'  => array( $wrap( '<style>@import url(https://example.test/x.css);</style>' ), '@import' ),
			'style attribute' => array( $wrap( '<circle cx="1" cy="1" r="1" style="background:url(https://example.test/x)"/>' ), 'example.test' ),
			'animate handler' => array( $wrap( '<animate onbegin="alert(1)" attributeName="x"/>' ), 'onbegin' ),
		);
	}

	/**
	 * Markup that was only script leaves an empty wrapper. Rendering that would give a blank tile
	 * where the manifest's icon name would have given something.
	 */
	public function test_a_mark_with_no_shape_left_is_refused_entirely(): void {
		$this->write( 'grocery/icon.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>' );

		$this->assertSame( '', Registry::icon_uri( 'grocery' ) );
	}

	public function test_an_oversized_mark_is_refused(): void {
		$this->write(
			'grocery/icon.svg',
			'<svg xmlns="http://www.w3.org/2000/svg"><path d="' . str_repeat( 'M0 0 ', 8000 ) . '"/></svg>'
		);

		$this->assertSame( '', Registry::icon_uri( 'grocery' ) );
	}

	public function test_a_recipe_with_no_mark_returns_nothing(): void {
		$this->assertSame( '', Registry::icon_uri( 'grocery' ) );
	}

	public function test_an_id_that_could_escape_its_directory_returns_nothing(): void {
		$this->assertSame( '', Registry::icon_uri( '../../wp-config' ) );
		$this->assertSame( '', Registry::icon_uri( '' ) );
	}
}
