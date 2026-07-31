<?php
/**
 * Tests for the AJAX endpoint that records the MCP hint's dismissal.
 *
 * Extends WP_Ajax_UnitTestCase rather than the plugin's own base case: the
 * handler ends in wp_send_json_*(), which calls wp_die(). Only the AJAX test
 * case swaps that for an exception — under a normal test case it exits and takes
 * the whole PHPUnit process with it.
 *
 * @package StoreSeeder\Tests
 */

namespace StoreSeeder\Tests;

use StoreSeeder;
use WP_Ajax_UnitTestCase;
use WPAjaxDieContinueException;
use WPAjaxDieStopException;

/**
 * @covers \StoreSeeder
 */
class McpNoticeDismissAjaxTest extends WP_Ajax_UnitTestCase {

	/**
	 * Administrator issuing the request.
	 *
	 * @var int
	 */
	private int $admin_id = 0;

	public function set_up() {
		parent::set_up();

		// A JSON response is only JSON if nothing else printed. wpdb echoes its errors as HTML
		// by default in tests, and another plugin loaded in the suite querying something the
		// test database cannot serve is enough to put that HTML inside this endpoint's body —
		// which reads here as "the handler returned nothing" and is a lie. Errors are still
		// recorded in $wpdb->last_error; they just stop being printed into the payload.
		$GLOBALS['wpdb']->hide_errors();

		$this->admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->admin_id );
	}

	public function tear_down() {
		delete_user_meta( $this->admin_id, StoreSeeder::MCP_NOTICE_DISMISSED_META );
		unset( $_POST['nonce'] );
		parent::tear_down();
	}

	/**
	 * Read the dismissal flag for a user.
	 *
	 * @param int $user_id User to read.
	 *
	 * @return string
	 */
	private function dismissal_for( int $user_id ): string {
		return (string) get_user_meta( $user_id, StoreSeeder::MCP_NOTICE_DISMISSED_META, true );
	}

	public function test_handler_is_registered(): void {
		$this->assertTrue(
			has_action( 'wp_ajax_' . StoreSeeder::MCP_NOTICE_DISMISS_ACTION ) !== false,
			'Without the hook the browser request would 400 and the dismissal would never stick.'
		);
	}

	public function test_valid_request_records_the_dismissal(): void {
		$_POST['nonce'] = wp_create_nonce( StoreSeeder::MCP_NOTICE_DISMISS_ACTION );

		try {
			$this->_handleAjax( StoreSeeder::MCP_NOTICE_DISMISS_ACTION );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertIsArray( $response );
		$this->assertTrue( $response['success'] );
		$this->assertSame( '1', $this->dismissal_for( $this->admin_id ) );
	}

	public function test_request_without_the_capability_is_refused(): void {
		$subscriber = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber );

		$_POST['nonce'] = wp_create_nonce( StoreSeeder::MCP_NOTICE_DISMISS_ACTION );

		try {
			$this->_handleAjax( StoreSeeder::MCP_NOTICE_DISMISS_ACTION );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertIsArray( $response );
		$this->assertFalse( $response['success'] );
		$this->assertSame( '', $this->dismissal_for( $subscriber ), 'Only users who can manage options may write this.' );
	}

	public function test_request_with_a_bad_nonce_is_refused(): void {
		$_POST['nonce'] = 'not-a-valid-nonce';

		try {
			$this->_handleAjax( StoreSeeder::MCP_NOTICE_DISMISS_ACTION );
		} catch ( WPAjaxDieStopException $e ) {
			unset( $e );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		$this->assertSame(
			'',
			$this->dismissal_for( $this->admin_id ),
			'A forged request must not be able to silence the hint.'
		);
	}
}
