<?php
/**
 * Tests for the MCP toggles and the gate they drive.
 *
 * The gate is registration: a tool that was never registered cannot be called. That makes
 * these tests the whole security story for the AI surface — if `tools()` returns a generate
 * definition while generation is off, an agent can write rows the site said it could not.
 *
 * @package StoreSeeder\Tests
 */

namespace StoreSeeder\Tests\MCP;

use StoreSeeder\MCP\MCP_Server;
use StoreSeeder\MCP\Registry;
use StoreSeeder\MCP\Settings;
use StoreSeeder\Tests\StoreSeederUnitTestCase;

/**
 * @covers \StoreSeeder\MCP\Settings
 * @covers \StoreSeeder\MCP\Registry
 */
class SettingsTest extends StoreSeederUnitTestCase {

	public function setUp(): void {
		parent::setUp();
		Registry::reset();
	}

	public function tearDown(): void {
		remove_all_filters( 'storeseeder_mcp_settings' );

		foreach ( array( Settings::ENABLED_OPTION, Settings::PREVIEW_OPTION, Settings::GENERATE_OPTION ) as $option ) {
			delete_option( $option );
		}

		Registry::reset();
		parent::tearDown();
	}

	/**
	 * All three default to on, which is what the plugin did before the switches existed.
	 * An upgrade that silently withdrew tools an agent was already calling would look like
	 * a broken integration, not like a policy.
	 */
	public function test_every_toggle_defaults_to_on(): void {
		$this->assertSame(
			array(
				'enabled'  => true,
				'preview'  => true,
				'generate' => true,
			),
			Settings::all()
		);
	}

	public function test_update_stores_only_the_keys_it_was_given(): void {
		Settings::update( array( 'generate' => false ) );

		$this->assertTrue( Settings::enabled() );
		$this->assertTrue( Settings::preview_enabled() );
		$this->assertFalse( Settings::generate_enabled() );
	}

	/**
	 * The master switch has to mean what it says everywhere, not only in the one place
	 * that happens to check both it and the sub-toggle.
	 */
	public function test_the_master_switch_withdraws_both_kinds(): void {
		Settings::update( array( 'enabled' => false ) );

		$this->assertFalse( Settings::preview_enabled() );
		$this->assertFalse( Settings::generate_enabled() );
		// Stored rather than cleared: turning the surface back on restores what was allowed.
		$this->assertTrue( Settings::all()['preview'] );
	}

	public function test_filter_can_decide_the_toggles_in_code(): void {
		add_filter(
			'storeseeder_mcp_settings',
			static function ( array $settings ): array {
				$settings['generate'] = false;
				return $settings;
			}
		);

		$this->assertTrue( Settings::preview_enabled() );
		$this->assertFalse( Settings::generate_enabled() );
	}

	/**
	 * A filter is allowed to be careless. A dropped key must not turn a documented
	 * boolean into null and make every caller's `if` read the wrong way.
	 */
	public function test_a_filter_that_drops_a_key_reads_as_off(): void {
		add_filter(
			'storeseeder_mcp_settings',
			static function (): array {
				return array( 'enabled' => true );
			}
		);

		$settings = Settings::all();

		$this->assertTrue( $settings['enabled'] );
		$this->assertFalse( $settings['preview'] );
		$this->assertFalse( $settings['generate'] );
	}

	// -----------------------------------------------------------------------------------
	// What the toggles actually withdraw.
	// -----------------------------------------------------------------------------------

	public function test_both_kinds_are_exposed_by_default(): void {
		$tools = Registry::instance()->tools();

		// Two per generator.
		$this->assertCount( 2 * count( Registry::instance()->ids() ), $tools );
		$this->assertArrayHasKey( 'storeseeder/generate-products', $tools );
		$this->assertArrayHasKey( 'storeseeder/preview-products', $tools );
	}

	public function test_generation_off_leaves_only_the_read_only_tools(): void {
		Settings::update( array( 'generate' => false ) );

		$ids = Registry::instance()->tool_ids();

		$this->assertContains( 'storeseeder/preview-products', $ids );
		$this->assertNotContains( 'storeseeder/generate-products', $ids );

		foreach ( $ids as $id ) {
			$this->assertStringStartsWith( 'storeseeder/preview-', $id );
		}
	}

	public function test_preview_off_leaves_only_the_generate_tools(): void {
		Settings::update( array( 'preview' => false ) );

		$ids = Registry::instance()->tool_ids();

		foreach ( $ids as $id ) {
			$this->assertStringStartsWith( 'storeseeder/generate-', $id );
		}
	}

	public function test_the_master_switch_off_exposes_nothing(): void {
		Settings::update( array( 'enabled' => false ) );

		$this->assertSame( array(), Registry::instance()->tools() );
	}

	/**
	 * Preview is listed first per resource, so a client enumerating tools meets the
	 * harmless one before the one that writes.
	 */
	public function test_preview_precedes_generate_for_each_resource(): void {
		$ids = Registry::instance()->tool_ids();

		$this->assertSame( 'storeseeder/preview-products', $ids[0] );
		$this->assertSame( 'storeseeder/generate-products', $ids[1] );
	}

	// -----------------------------------------------------------------------------------
	// The read-only definitions.
	// -----------------------------------------------------------------------------------

	/**
	 * A preview tool answers with columns and rows, not with a generation envelope — and
	 * it must not describe itself as creating anything, since an AI client chooses between
	 * the two on the strength of these strings.
	 */
	public function test_a_preview_definition_declares_the_preview_envelope(): void {
		$def = Registry::instance()->preview_definitions()['storeseeder/preview-products'];

		$this->assertSame( array( 'columns', 'rows' ), array_keys( $def['output_schema']['properties'] ) );
		$this->assertStringContainsString( 'Read-only', $def['description'] );
		$this->assertTrue( $def['meta']['annotations']['readonly'] );
		$this->assertTrue( $def['meta']['annotations']['idempotent'] );
	}

	public function test_a_generate_definition_is_not_marked_read_only(): void {
		$def = Registry::instance()->definitions()['storeseeder/generate-products'];

		$this->assertFalse( $def['meta']['annotations']['readonly'] );
		// StoreSeeder only ever creates, so nothing it exposes is destructive.
		$this->assertFalse( $def['meta']['annotations']['destructive'] );
	}

	/**
	 * mcp-adapter's own default server refuses to run an ability without this flag, so
	 * dropping it would quietly break every client pointed at
	 * /wp-json/mcp/mcp-adapter-default-server rather than at StoreSeeder's endpoint.
	 */
	public function test_every_tool_is_public_to_the_default_mcp_server(): void {
		foreach ( Registry::instance()->tools() as $id => $def ) {
			$this->assertTrue( $def['meta']['mcp']['public'], $id );
			$this->assertSame( 'tool', $def['meta']['mcp']['type'], $id );
		}
	}

	/**
	 * Both tools take the same parameters, because they answer the same question — one by
	 * describing the rows and one by inserting them.
	 */
	public function test_a_preview_tool_takes_the_same_input_as_its_generate_tool(): void {
		$registry = Registry::instance();

		$this->assertSame(
			$registry->definitions()['storeseeder/generate-orders']['input_schema'],
			$registry->preview_definitions()['storeseeder/preview-orders']['input_schema']
		);
	}

	// -----------------------------------------------------------------------------------
	// What the admin is told.
	// -----------------------------------------------------------------------------------

	public function test_status_reports_the_toggles_and_the_tool_count(): void {
		Settings::update( array( 'generate' => false ) );

		$status = MCP_Server::status();

		$this->assertTrue( $status['enabled'] );
		$this->assertTrue( $status['preview'] );
		$this->assertFalse( $status['generate'] );
		// Generators are unchanged; only the tools built from them are fewer.
		$this->assertSame( count( Registry::instance()->ids() ), $status['abilities'] );
		$this->assertSame( $status['abilities'], $status['tools'] );
	}

	public function test_status_reports_no_tools_when_the_surface_is_off(): void {
		Settings::update( array( 'enabled' => false ) );

		$this->assertSame( 0, MCP_Server::status()['tools'] );
	}
}
