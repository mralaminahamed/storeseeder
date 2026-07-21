<?php
/**
 * Helper trait for WordPress REST API requests in tests.
 *
 * @package FluentCartFakerPress\Tests
 */

namespace FluentCartFakerPress\Tests\TestHelpers\Traits;

use WP_REST_Request;

/**
 * Helper trait for building and asserting REST requests in tests.
 *
 * @since 2.4.0
 */
trait Wp_Rest_Request_Trait {

	/**
	 * Create a REST request, optionally authenticated.
	 *
	 * @param string $method  HTTP method.
	 * @param string $route   Route path.
	 * @param array  $params  Request parameters.
	 * @param int    $user_id User ID to authenticate as, or 0 for none.
	 *
	 * @return WP_REST_Request
	 */
	protected function create_authenticated_request( string $method, string $route, array $params = array(), int $user_id = 0 ): WP_REST_Request {
		$request = new WP_REST_Request( strtoupper( $method ), $route );

		if ( in_array( strtoupper( $method ), array( 'POST', 'PUT', 'PATCH' ), true ) ) {
			$request->set_body_params( $params );
		} else {
			$request->set_query_params( $params );
		}

		if ( $user_id > 0 ) {
			wp_set_current_user( $user_id );
		}

		return $request;
	}

	/**
	 * Assert a REST response has the expected status code.
	 *
	 * @param int                $expected_status Expected status code.
	 * @param \WP_REST_Response  $response        REST response.
	 * @param string             $message         Optional assertion message.
	 *
	 * @return void
	 */
	protected function assertResponseStatus( int $expected_status, $response, string $message = '' ): void {
		$actual_status = $response->get_status();
		$this->assertEquals(
			$expected_status,
			$actual_status,
			$message ?: "Expected status {$expected_status}, got {$actual_status}. Response: " . wp_json_encode( $response->get_data() )
		);
	}

	/**
	 * Assert a REST response's data contains the expected keys.
	 *
	 * @param array              $expected_keys Expected keys.
	 * @param \WP_REST_Response  $response      REST response.
	 *
	 * @return void
	 */
	protected function assertResponseHasKeys( array $expected_keys, $response ): void {
		$data = $response->get_data();
		foreach ( $expected_keys as $key ) {
			$this->assertArrayHasKey( $key, $data, "Response missing expected key: {$key}" );
		}
	}

	/**
	 * Assert a REST response is an error, optionally with a given code.
	 *
	 * @param \WP_REST_Response $response   REST response.
	 * @param string            $error_code Expected error code.
	 *
	 * @return void
	 */
	protected function assertResponseError( $response, string $error_code = '' ): void {
		$this->assertTrue( $response->is_error(), 'Response should be an error.' );

		if ( $error_code ) {
			$data = $response->get_data();
			$this->assertEquals( $error_code, $data['code'] ?? '', "Expected error code {$error_code}." );
		}
	}
}
