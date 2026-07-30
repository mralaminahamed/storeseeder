<?php
/**
 * Tests for the admin menu icon.
 *
 * @package StoreSeeder\Tests
 */

namespace StoreSeeder\Tests;

use ReflectionMethod;
use StoreSeeder;

/**
 * @covers \StoreSeeder
 */
class AdminMenuIconTest extends StoreSeederUnitTestCase {

	/**
	 * Decoded SVG markup of the menu icon.
	 *
	 * @var string
	 */
	private string $svg = '';

	/**
	 * The raw data URI.
	 *
	 * @var string
	 */
	private string $uri = '';

	public function setUp(): void {
		parent::setUp();

		// get_menu_icon() is private: it is an implementation detail of
		// add_admin_menu(), but the artwork it emits is worth pinning down.
		$method = new ReflectionMethod( StoreSeeder::class, 'get_menu_icon' );

		// Required to reach a private method before PHP 8.1, and deprecated from
		// PHP 8.5 — where the suite's strict output checks turn the notice into a
		// risky test. The plugin supports 7.4, so both paths have to work.
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		$this->uri = (string) $method->invoke( storeseeder() );
		$this->svg = (string) base64_decode( substr( $this->uri, strlen( 'data:image/svg+xml;base64,' ) ), true );
	}

	public function test_uri_uses_the_prefix_wordpress_recognises(): void {
		// WordPress only treats an icon as an SVG background when it starts with
		// exactly this; anything else is rendered as an <img> instead.
		$this->assertStringStartsWith( 'data:image/svg+xml;base64,', $this->uri );
	}

	public function test_payload_decodes_to_well_formed_svg(): void {
		$this->assertNotSame( '', $this->svg, 'The payload must be valid base64.' );

		$previous = libxml_use_internal_errors( true );
		$parsed   = simplexml_load_string( $this->svg );
		libxml_use_internal_errors( $previous );

		$this->assertNotFalse( $parsed, 'The decoded payload must be well-formed XML.' );
		$this->assertSame( 'svg', $parsed->getName() );
	}

	public function test_artwork_matches_the_source_icon(): void {
		$source = file_get_contents( STORESEEDER_PLUGIN_PATH . '.wordpress-org/icon.svg' );

		if ( false === $source ) {
			$this->markTestSkipped( '.wordpress-org/icon.svg is not present in this checkout.' );
		}

		// The cart transform, the cart outline and both sprout leaves are copied
		// verbatim from the source, so drift between the two shows up here.
		$fragments = array(
			'translate(11,26) scale(3.7)',
			'M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12',
			'M92,27 C86.5,21 78.5,21.5 76,27 C81.5,32 89.5,31.5 92,27 Z',
			'M92,22 C96,14.5 104,13.5 108.5,18.5 C104.5,25 96.5,26 92,22 Z',
		);

		foreach ( $fragments as $fragment ) {
			$this->assertStringContainsString( $fragment, $source, 'Source artwork changed; update the menu icon too.' );
			$this->assertStringContainsString( $fragment, $this->svg, 'Menu icon has drifted from .wordpress-org/icon.svg.' );
		}

		$this->assertStringContainsString( 'viewBox="0 0 120 120"', $this->svg, 'The menu icon must use the source grid.' );
	}

	public function test_default_variant_is_a_dark_mark_on_a_white_tile(): void {
		$this->assertStringContainsString( 'rx="29" fill="#ffffff"', $this->svg, 'The tile keeps the source corner radius.' );
		$this->assertStringContainsString( 'stroke="#1d2327"', $this->svg );
		$this->assertStringContainsString( 'fill="#1d2327"', $this->svg, 'The sprout leaves are filled, not stroked.' );
	}

	public function test_no_variant_carries_the_gradient(): void {
		// A gradient panel muddies to a single tone at 20px, so neither variant
		// ships one.
		$this->assertStringNotContainsString( 'linearGradient', $this->svg );
		$this->assertStringNotContainsString( '#4f46e5', $this->svg );
		$this->assertStringNotContainsString( '#7c3aed', $this->svg );
	}

	public function test_monochrome_variant_drops_the_tile_for_admin_grey(): void {
		add_filter( 'storeseeder_menu_icon_variant', array( $this, 'force_monochrome_variant' ) );
		$svg = $this->decode_menu_icon();
		remove_filter( 'storeseeder_menu_icon_variant', array( $this, 'force_monochrome_variant' ) );

		$this->assertStringContainsString( '#a7aaad', $svg );
		$this->assertStringNotContainsString( '<rect', $svg, 'The monochrome variant draws no tile.' );
		$this->assertStringNotContainsString( '#ffffff', $svg );
		$this->assertStringContainsString( 'translate(11,26) scale(3.7)', $svg, 'Both variants share the artwork.' );
	}

	public function test_unknown_variant_falls_back_to_the_default(): void {
		add_filter( 'storeseeder_menu_icon_variant', array( $this, 'force_unknown_variant' ) );
		$svg = $this->decode_menu_icon();
		remove_filter( 'storeseeder_menu_icon_variant', array( $this, 'force_unknown_variant' ) );

		$this->assertStringContainsString( 'fill="#ffffff"', $svg, 'Anything unrecognised should render the default.' );
	}

	/**
	 * Filter callback: select the monochrome variant.
	 *
	 * @return string
	 */
	public function force_monochrome_variant(): string {
		return 'monochrome';
	}

	/**
	 * Filter callback: return a variant name the plugin does not know.
	 *
	 * @return string
	 */
	public function force_unknown_variant(): string {
		return 'chartreuse-hexagon';
	}

	/**
	 * Invoke the private builder and decode its payload.
	 *
	 * @return string SVG markup.
	 */
	private function decode_menu_icon(): string {
		$method = new ReflectionMethod( StoreSeeder::class, 'get_menu_icon' );

		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		$uri = (string) $method->invoke( storeseeder() );

		return (string) base64_decode( substr( $uri, strlen( 'data:image/svg+xml;base64,' ) ), true );
	}
}
