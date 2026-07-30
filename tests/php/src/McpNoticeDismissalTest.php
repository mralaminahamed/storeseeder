<?php
/**
 * Tests for remembering the MCP hint's dismissal.
 *
 * @package StoreSeeder\Tests
 */

namespace StoreSeeder\Tests;

use StoreSeeder;

/**
 * @covers \StoreSeeder
 */
class McpNoticeDismissalTest extends StoreSeederUnitTestCase {

	/**
	 * The plugin instance.
	 *
	 * @var StoreSeeder
	 */
	private StoreSeeder $plugin;

	/**
	 * Administrator used for the dismissal.
	 *
	 * @var int
	 */
	private int $admin_id = 0;

	public function setUp(): void {
		parent::setUp();

		if ( storeseeder()->is_mcp_adapter_active() ) {
			$this->markTestSkipped( 'mcp-adapter is installed, so the hint never renders.' );
		}

		$this->plugin   = storeseeder();
		$this->admin_id = $this->create_admin_user();

		wp_set_current_user( $this->admin_id );
		set_current_screen( 'toplevel_page_storeseeder' );
	}

	public function tearDown(): void {
		delete_user_meta( $this->admin_id, StoreSeeder::MCP_NOTICE_DISMISSED_META );
		wp_set_current_user( 0 );
		unset( $this->plugin );
		parent::tearDown();
	}

	/**
	 * Capture the notice markup.
	 *
	 * @return string
	 */
	private function render_notices(): string {
		ob_start();
		$this->plugin->dependency_notice();

		return (string) ob_get_clean();
	}

	public function test_notice_shows_before_it_is_dismissed(): void {
		$output = $this->render_notices();

		$this->assertStringContainsString( 'storeseeder-mcp-notice', $output );
		$this->assertStringContainsString( 'mcp-adapter', $output );
	}

	public function test_notice_stays_hidden_once_dismissed(): void {
		update_user_meta( $this->admin_id, StoreSeeder::MCP_NOTICE_DISMISSED_META, 1 );

		$this->assertStringNotContainsString(
			'storeseeder-mcp-notice',
			$this->render_notices(),
			'A dismissal has to survive the next page load — that is the whole point.'
		);
	}

	public function test_dismissal_is_per_user(): void {
		update_user_meta( $this->admin_id, StoreSeeder::MCP_NOTICE_DISMISSED_META, 1 );

		$colleague = $this->create_admin_user();
		wp_set_current_user( $colleague );

		$this->assertStringContainsString(
			'storeseeder-mcp-notice',
			$this->render_notices(),
			'One administrator dismissing a hint must not hide it for everyone else.'
		);

		delete_user_meta( $colleague, StoreSeeder::MCP_NOTICE_DISMISSED_META );
	}

	public function test_notice_ships_the_dismiss_script_with_a_nonce(): void {
		$output = $this->render_notices();

		$this->assertStringContainsString( StoreSeeder::MCP_NOTICE_DISMISS_ACTION, $output, 'The script needs the AJAX action.' );
		$this->assertStringContainsString( 'admin-ajax.php', $output );
		$this->assertStringContainsString( 'notice-dismiss', $output, 'The listener hangs off the core close button.' );
		$this->assertStringContainsString( 'credentials', $output, 'The request must carry the session cookie.' );
	}

	public function test_notice_is_not_shown_outside_the_plugin_screen(): void {
		set_current_screen( 'dashboard' );

		$this->assertStringNotContainsString( 'storeseeder-mcp-notice', $this->render_notices() );
	}
}
