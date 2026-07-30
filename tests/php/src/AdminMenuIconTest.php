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

	public function test_icon_is_monochrome_admin_grey(): void {
		// WordPress paints this as a background image, which cannot inherit a
		// colour — so it has to ship in the admin icon grey and carry no gradient
		// or brand fill, or it will not sit right in the menu.
		$this->assertStringContainsString( '#a7aaad', $this->svg );
		$this->assertStringNotContainsString( 'linearGradient', $this->svg );
		$this->assertStringNotContainsString( '#4f46e5', $this->svg );
		$this->assertStringNotContainsString( '#7c3aed', $this->svg );
		$this->assertStringNotContainsString( '#ffffff', $this->svg );
	}
}
