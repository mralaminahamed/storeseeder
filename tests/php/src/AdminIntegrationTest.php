<?php
/**
 * Tests for the Plugins-screen links, admin body class, and MCP adapter check.
 *
 * @package StoreSeeder\Tests
 */

namespace StoreSeeder\Tests;

use StoreSeeder;

/**
 * @covers \StoreSeeder
 */
class AdminIntegrationTest extends StoreSeederUnitTestCase {

	/**
	 * The plugin instance.
	 *
	 * @var StoreSeeder
	 */
	private StoreSeeder $plugin;

	public function setUp(): void {
		parent::setUp();
		$this->plugin = storeseeder();
	}

	public function tearDown(): void {
		unset( $this->plugin );
		parent::tearDown();
	}

	public function test_action_links_are_prepended(): void {
		$links = $this->plugin->add_plugin_action_links( array( 'deactivate' => '<a href="#">Deactivate</a>' ) );

		$this->assertSame(
			array( 'generate', 'settings', 'deactivate' ),
			array_keys( $links ),
			"The plugin's own links belong before WordPress's."
		);
		$this->assertStringContainsString( 'page=storeseeder', $links['generate'] );
		$this->assertStringContainsString( 'page=storeseeder#/settings', $links['settings'] );
	}

	public function test_row_meta_added_for_this_plugin(): void {
		$file  = plugin_basename( STORESEEDER_PLUGIN_FILE );
		$links = $this->plugin->add_plugin_row_meta( array( 'version' => '1.0.0' ), $file );

		$joined = implode( ' ', $links );

		$this->assertStringContainsString( 'Documentation', $joined );
		$this->assertStringContainsString( 'Support', $joined );
		$this->assertStringContainsString( 'GitHub', $joined );
		$this->assertStringContainsString( 'rel="noopener noreferrer"', $joined );
	}

	public function test_row_meta_untouched_for_other_plugins(): void {
		$original = array( 'version' => '1.0.0' );

		$this->assertSame(
			$original,
			$this->plugin->add_plugin_row_meta( $original, 'some-other-plugin/some-other-plugin.php' ),
			'Row meta must only be extended on this plugin’s own row.'
		);
	}

	public function test_body_class_added_on_plugin_screen(): void {
		set_current_screen( 'toplevel_page_storeseeder' );

		$this->assertStringContainsString(
			'storeseeder-admin-page',
			$this->plugin->filter_admin_body_class( 'wp-admin folded' )
		);
	}

	public function test_body_class_keeps_the_folded_preference(): void {
		set_current_screen( 'toplevel_page_storeseeder' );

		$this->assertStringContainsString(
			'folded',
			$this->plugin->filter_admin_body_class( 'wp-admin folded' ),
			"The user's collapsed-menu preference must survive."
		);
	}

	public function test_body_class_untouched_on_other_screens(): void {
		set_current_screen( 'dashboard' );

		$this->assertSame(
			'wp-admin folded',
			$this->plugin->filter_admin_body_class( 'wp-admin folded' )
		);
	}

	public function test_mcp_adapter_detection_tracks_the_transport_class(): void {
		$this->assertSame(
			class_exists( '\WP\MCP\Transport\HttpTransport' ),
			$this->plugin->is_mcp_adapter_active(),
			'The check must reflect whether mcp-adapter is actually loaded.'
		);
	}

	public function test_mcp_notice_only_shows_on_the_plugin_screen(): void {
		if ( $this->plugin->is_mcp_adapter_active() ) {
			$this->markTestSkipped( 'mcp-adapter is installed, so the notice never renders.' );
		}

		set_current_screen( 'dashboard' );
		ob_start();
		$this->plugin->dependency_notice();
		$elsewhere = (string) ob_get_clean();

		set_current_screen( 'toplevel_page_storeseeder' );
		ob_start();
		$this->plugin->dependency_notice();
		$on_screen = (string) ob_get_clean();

		$this->assertStringNotContainsString( 'mcp-adapter', $elsewhere, 'The hint must not follow users around wp-admin.' );
		$this->assertStringContainsString( 'mcp-adapter', $on_screen );
		$this->assertStringContainsString( 'is-dismissible', $on_screen );
	}
}
