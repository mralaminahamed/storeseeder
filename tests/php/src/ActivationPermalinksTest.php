<?php
/**
 * Tests for the permalink handling in the activation hook.
 *
 * @package StoreSeeder\Tests
 */

namespace StoreSeeder\Tests;

use StoreSeeder;

/**
 * @covers \StoreSeeder
 */
class ActivationPermalinksTest extends StoreSeederUnitTestCase {

	/**
	 * @var StoreSeeder
	 */
	private StoreSeeder $plugin;

	/**
	 * Structure the site had before the test touched it.
	 *
	 * @var string
	 */
	private string $original_structure = '';

	public function setUp(): void {
		parent::setUp();
		$this->plugin             = storeseeder();
		$this->original_structure = (string) get_option( 'permalink_structure', '' );
	}

	public function tearDown(): void {
		$this->set_structure( $this->original_structure );
		remove_all_filters( 'storeseeder_set_postname_permalinks' );
		unset( $this->plugin );
		parent::tearDown();
	}

	/**
	 * Write the structure straight to the option, bypassing WP_Rewrite so the
	 * fixture cannot be confused with what activation itself does.
	 *
	 * @param string $structure Permalink structure to store.
	 *
	 * @return void
	 */
	private function set_structure( string $structure ): void {
		update_option( 'permalink_structure', $structure );

		global $wp_rewrite;
		if ( $wp_rewrite instanceof \WP_Rewrite ) {
			$wp_rewrite->init();
		}
	}

	public function test_activation_sets_postname_permalinks_when_none_set(): void {
		$this->set_structure( '' );

		$this->plugin->activate_plugin();

		$this->assertSame( '/%postname%/', get_option( 'permalink_structure' ) );
	}

	public function test_activation_keeps_an_existing_structure(): void {
		$this->set_structure( '/%year%/%monthnum%/%postname%/' );

		$this->plugin->activate_plugin();

		$this->assertSame(
			'/%year%/%monthnum%/%postname%/',
			get_option( 'permalink_structure' ),
			'A structure the site owner already picked must not be overwritten.'
		);
	}

	public function test_activation_keeps_a_custom_structure_that_omits_postname(): void {
		$this->set_structure( '/archives/%post_id%' );

		$this->plugin->activate_plugin();

		$this->assertSame( '/archives/%post_id%', get_option( 'permalink_structure' ) );
	}

	public function test_filter_can_opt_out_of_setting_permalinks(): void {
		$this->set_structure( '' );
		add_filter( 'storeseeder_set_postname_permalinks', '__return_false' );

		$this->plugin->activate_plugin();

		$this->assertSame(
			'',
			get_option( 'permalink_structure' ),
			'Sites that deliberately run plain permalinks must be able to opt out.'
		);
	}

	public function test_activation_is_idempotent(): void {
		$this->set_structure( '' );

		$this->plugin->activate_plugin();
		$this->plugin->activate_plugin();

		$this->assertSame( '/%postname%/', get_option( 'permalink_structure' ) );
	}
}
