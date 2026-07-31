<?php
/**
 * Tests for the shared CLI command behaviour.
 *
 * The command classes are thin: they resolve a resource, build a payload, and hand it to
 * the REST server. Those first two steps are what can be wrong in a way that generates a
 * hundred rows of the wrong thing, so they are static, WP_CLI-free, and tested here.
 *
 * @package StoreSeeder\Tests
 */

namespace StoreSeeder\Tests\CLI;

use StoreSeeder\CLI\Command;
use StoreSeeder\CLI\Commands\Cleanup;
use StoreSeeder\CLI\Commands\Generate;
use StoreSeeder\CLI\Commands\Locales;
use StoreSeeder\CLI\Commands\Platforms;
use StoreSeeder\CLI\Commands\Preview;
use StoreSeeder\CLI\Commands\Sample_Data;
use StoreSeeder\Platforms\Locale;
use StoreSeeder\Tests\StoreSeederUnitTestCase;

/**
 * @covers \StoreSeeder\CLI\Command
 */
class CommandTest extends StoreSeederUnitTestCase {

	/**
	 * Both spellings resolve, because both are real: `cart-sessions` is the REST base and
	 * `cart_session` the canonical resource, and no rule relates them.
	 */
	public function test_resolve_accepts_the_rest_base_and_the_resource_name(): void {
		$this->assertSame( 'cart-sessions', Command::resolve_resource( 'cart-sessions' ) );
		$this->assertSame( 'cart-sessions', Command::resolve_resource( 'cart_session' ) );
		$this->assertSame( 'cart-sessions', Command::resolve_resource( 'cart_sessions' ) );
	}

	public function test_resolve_ignores_case_and_surrounding_space(): void {
		$this->assertSame( 'products', Command::resolve_resource( '  PRODUCTS ' ) );
	}

	public function test_resolve_covers_every_registered_resource(): void {
		foreach ( \StoreSeeder\Rest\Registry::instance()->all() as $base => $controller ) {
			$this->assertSame( $base, Command::resolve_resource( $base ), $base );
			$this->assertSame(
				$base,
				Command::resolve_resource( $controller->resource_type() ),
				$controller->resource_type()
			);
		}
	}

	/**
	 * A typo has to name what was valid. "Unknown resource" on its own leaves the user
	 * guessing between two spellings of seventeen things.
	 */
	public function test_resolve_rejects_the_unknown_and_lists_what_is_valid(): void {
		$error = Command::resolve_resource( 'widgets' );

		$this->assertInstanceOf( 'WP_Error', $error );
		$this->assertSame( 'storeseeder_unknown_resource', $error->get_error_code() );
		$this->assertStringContainsString( 'widgets', $error->get_error_message() );
		$this->assertStringContainsString( 'products', $error->get_error_message() );
	}

	public function test_endpoint_params_come_from_the_controller(): void {
		$params = Command::endpoint_params( 'products' );

		foreach ( array( 'count', 'locale', 'seed', 'platform', 'product_type' ) as $param ) {
			$this->assertArrayHasKey( $param, $params );
		}
	}

	public function test_endpoint_params_are_empty_for_an_unknown_base(): void {
		$this->assertSame( array(), Command::endpoint_params( 'widgets' ) );
	}

	public function test_build_payload_passes_known_parameters_through(): void {
		$payload = Command::build_payload(
			'products',
			array(
				'count'  => '20',
				'locale' => 'de_DE',
			)
		);

		$this->assertSame( 20, $payload['count'] );
		$this->assertSame( 'de_DE', $payload['locale'] );
	}

	/**
	 * WP-CLI's own flags are not endpoint parameters, and passing them on would fail
	 * validation for a request the user made correctly.
	 */
	public function test_build_payload_drops_wp_cli_flags(): void {
		$payload = Command::build_payload(
			'products',
			array(
				'count'     => '5',
				'user'      => 'admin',
				'porcelain' => true,
				'format'    => 'json',
				'quiet'     => true,
				'debug'     => true,
			)
		);

		$this->assertSame( array( 'count' => 5 ), $payload );
	}

	/**
	 * The reason this validation exists: `--lokale=de_DE` would otherwise be dropped in
	 * silence, generate a hundred rows in English, and report success.
	 */
	public function test_build_payload_rejects_a_parameter_the_endpoint_does_not_have(): void {
		$error = Command::build_payload( 'products', array( 'lokale' => 'de_DE' ) );

		$this->assertInstanceOf( 'WP_Error', $error );
		$this->assertSame( 'storeseeder_unknown_parameter', $error->get_error_code() );
		$this->assertStringContainsString( 'lokale', $error->get_error_message() );
		// Names the real one, so the fix is obvious.
		$this->assertStringContainsString( 'locale', $error->get_error_message() );
	}

	/**
	 * Everything arrives from a shell as a string, and the REST validator rejects "20"
	 * where an integer is declared — so the coercion is not a convenience.
	 */
	public function test_build_payload_coerces_to_the_declared_type(): void {
		$payload = Command::build_payload(
			'products',
			array(
				'count'       => '20',
				'price_range' => '{"min":5,"max":500}',
			)
		);

		$this->assertIsInt( $payload['count'] );
		$this->assertSame( array( 'min' => 5, 'max' => 500 ), $payload['price_range'] );
	}

	public function test_build_payload_splits_a_comma_separated_list(): void {
		$payload = Command::build_payload(
			'orders',
			array( 'payment_methods' => 'stripe, paypal' )
		);

		$this->assertSame( array( 'stripe', 'paypal' ), $payload['payment_methods'] );
	}

	public function test_build_payload_leaves_unparseable_json_for_the_validator_to_reject(): void {
		$payload = Command::build_payload( 'products', array( 'price_range' => 'not json' ) );

		// Passed through rather than silently dropped: the REST layer's error message about
		// the parameter is better than this layer inventing one.
		$this->assertSame( 'not json', $payload['price_range'] );
	}

	/**
	 * Every command declares the metadata WP-CLI needs to document itself, since
	 * `wp help storeseeder <cmd>` is where people look first.
	 */
	public function test_every_command_declares_a_name_and_a_description(): void {
		$classes = array( Generate::class, Preview::class, Platforms::class, Locales::class, Sample_Data::class, Cleanup::class );

		foreach ( $classes as $class ) {
			$this->assertNotSame( '', $class::NAME, $class );
			$this->assertNotSame( '', $class::shortdesc(), $class );

			foreach ( $class::synopsis() as $parameter ) {
				$this->assertArrayHasKey( 'type', $parameter, $class );
				$this->assertArrayHasKey( 'description', $parameter, $class );
			}
		}
	}
}

/**
 * @covers \StoreSeeder\CLI\Commands\Generate
 * @covers \StoreSeeder\CLI\Commands\Preview
 * @covers \StoreSeeder\CLI\Commands\Platforms
 * @covers \StoreSeeder\CLI\Commands\Locales
 * @covers \StoreSeeder\CLI\Commands\Sample_Data
 */
class CommandOutputTest extends StoreSeederUnitTestCase {

	/**
	 * The generate envelope is `{ message, <resource>: [ … ] }` and the resource key differs
	 * per endpoint, so the count cannot come from a fixed key.
	 */
	public function test_generate_counts_items_from_whichever_key_holds_them(): void {
		$this->assertSame( 3, Generate::count_created( array( 'message' => 'ok', 'product' => array( 1, 2, 3 ) ) ) );
		$this->assertSame( 2, Generate::count_created( array( 'message' => 'ok', 'cart_session' => array( 1, 2 ) ) ) );
		$this->assertSame( 7, Generate::count_created( array( 'generated' => 7 ) ) );
		$this->assertSame( 0, Generate::count_created( array( 'message' => 'nothing' ) ) );
	}

	/**
	 * Preview rows are built for a React table — every cell is an object carrying a display
	 * hint. A terminal wants the value, under the column's own label.
	 */
	public function test_preview_flattens_cells_and_uses_column_labels(): void {
		$rows = Preview::flatten_rows(
			array(
				'columns' => array(
					array( 'key' => 'name', 'label' => 'Name' ),
					array( 'key' => 'price', 'label' => 'Price' ),
				),
				'rows'    => array(
					array(
						'name'  => array( 'v' => 'Widget', 'kind' => 'text' ),
						'price' => array( 'v' => '$10.00', 'kind' => 'money' ),
					),
				),
			)
		);

		$this->assertSame( array( array( 'Name' => 'Widget', 'Price' => '$10.00' ) ), $rows );
	}

	public function test_preview_falls_back_to_the_key_when_a_column_has_no_label(): void {
		$rows = Preview::flatten_rows(
			array( 'rows' => array( array( 'sku' => array( 'v' => 'SKU-1' ) ) ) )
		);

		$this->assertSame( array( array( 'sku' => 'SKU-1' ) ), $rows );
	}

	public function test_preview_returns_nothing_for_a_body_without_rows(): void {
		$this->assertSame( array(), Preview::flatten_rows( array() ) );
		$this->assertSame( array(), Preview::flatten_rows( array( 'rows' => 'not an array' ) ) );
	}

	/**
	 * The table has to answer "where will my next run go?", which means distinguishing a
	 * platform chosen explicitly from one Auto happens to resolve to.
	 */
	public function test_platform_rows_distinguish_an_explicit_target_from_auto(): void {
		$state = array(
			'platforms' => array(
				array(
					'id'       => 'fluent-cart',
					'label'    => 'Fluent Cart',
					'active'   => true,
					'version'  => '1.6.0',
					'supports' => array(
						'product' => array( 'supported' => true ),
						'refund'  => array( 'supported' => false ),
					),
				),
			),
			'stored'    => '',
			'resolved'  => 'fluent-cart',
		);

		$rows = Platforms::rows( $state );

		$this->assertSame( 'auto', $rows[0]['target'] );
		$this->assertSame( '1/2', $rows[0]['resources'] );

		$state['stored'] = 'fluent-cart';
		$this->assertSame( 'yes', Platforms::rows( $state )[0]['target'] );
	}

	public function test_platform_rows_report_an_inactive_driver_without_a_version(): void {
		$rows = Platforms::rows(
			array(
				'platforms' => array(
					array( 'id' => 'woocommerce', 'label' => 'WooCommerce', 'active' => false, 'version' => null ),
				),
				'stored'    => '',
				'resolved'  => '',
			)
		);

		$this->assertSame( 'no', $rows[0]['active'] );
		$this->assertSame( '—', $rows[0]['version'] );
		$this->assertSame( '', $rows[0]['target'] );
	}

	public function test_locale_rows_list_everything_and_mark_the_default(): void {
		$rows = Locales::rows();

		$this->assertCount( count( Locale::all() ), $rows );

		$defaults = array_filter( $rows, static fn( $row ) => '*' === $row['default'] );
		$this->assertCount( 1, $defaults );
		$this->assertSame( Locale::DEFAULT_LOCALE, reset( $defaults )['code'] );
	}

	public function test_locale_rows_search_matches_label_or_code(): void {
		$this->assertNotEmpty( Locales::rows( 'german' ) );
		$this->assertNotEmpty( Locales::rows( 'ja_JP' ) );
		$this->assertSame( array(), Locales::rows( 'zzzzz' ) );
	}

	/**
	 * The ledger table is what someone reads before agreeing to delete, so a count
	 * attributed to the wrong resource is the failure that matters here.
	 */
	public function test_cleanup_rows_pair_each_resource_with_its_count(): void {
		$rows = Cleanup::rows(
			array(
				'total'     => 3,
				'resources' => array(
					array( 'resource' => 'transaction', 'count' => 1 ),
					array( 'resource' => 'order', 'count' => 2 ),
				),
			)
		);

		$this->assertSame(
			array(
				array( 'resource' => 'transaction', 'rows' => 1 ),
				array( 'resource' => 'order', 'rows' => 2 ),
			),
			$rows
		);
	}

	public function test_cleanup_rows_skip_entries_it_cannot_read(): void {
		$rows = Cleanup::rows( array( 'resources' => array( array( 'count' => 4 ), 'order' ) ) );

		$this->assertSame( array(), $rows );
	}

	public function test_cleanup_rows_are_empty_for_a_body_without_resources(): void {
		$this->assertSame( array(), Cleanup::rows( array() ) );
	}

	/**
	 * Three consent states, and "never asked" must not read as "declined" — one is a
	 * decision, the other is a prompt not yet seen.
	 */
	public function test_sample_data_status_names_the_consent_state(): void {
		$this->assertStringContainsString(
			'granted',
			implode( "\n", Sample_Data::status_lines( array( 'exists' => true, 'consent' => 'granted' ) ) )
		);
		$this->assertStringContainsString(
			'declined',
			implode( "\n", Sample_Data::status_lines( array( 'exists' => false, 'consent' => 'declined' ) ) )
		);
		$this->assertStringContainsString(
			'never asked',
			implode( "\n", Sample_Data::status_lines( array( 'exists' => false ) ) )
		);
	}

	public function test_sample_data_status_omits_lines_it_has_no_value_for(): void {
		$lines = Sample_Data::status_lines( array( 'exists' => false ) );

		$this->assertCount( 2, $lines );
		$this->assertStringNotContainsString( 'Last synced', implode( "\n", $lines ) );
	}
}
