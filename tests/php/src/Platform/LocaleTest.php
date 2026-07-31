<?php
/**
 * Tests for the supported-locale list.
 *
 * @package StoreSeeder\Tests
 */

namespace StoreSeeder\Tests\Platform;

use StoreSeeder\Platforms\Locale;
use StoreSeeder\Tests\StoreSeederUnitTestCase;

/**
 * @covers \StoreSeeder\Platforms\Locale
 */
class LocaleTest extends StoreSeederUnitTestCase {

	public function tearDown(): void {
		remove_all_filters( 'storeseeder_locales' );
		remove_all_filters( 'storeseeder_locale' );
		parent::tearDown();
	}

	/**
	 * The guard against the bug this class was written to fix.
	 *
	 * Offering a locale FakerPHP has no provider for is not a harmless surplus:
	 * Factory::create() falls back to en_US in silence, so the user picks Japanese and
	 * is handed English with no error. Equally, a locale faker ships and we do not
	 * offer is capability thrown away for nothing. Either direction is a bug, so this
	 * asserts the two sets are identical rather than merely overlapping.
	 */
	public function test_list_matches_fakerphp_exactly(): void {
		$provider_dir = dirname( __DIR__, 4 ) . '/vendor/fakerphp/faker/src/Faker/Provider';

		$this->assertDirectoryExists( $provider_dir, 'FakerPHP is not installed' );

		$faker = array();

		foreach ( (array) scandir( $provider_dir ) as $entry ) {
			if ( is_string( $entry ) && 1 === preg_match( '/^[a-z]{2}_[A-Za-z_]+$/', $entry ) ) {
				$faker[] = $entry;
			}
		}

		sort( $faker );

		$ours = Locale::codes();
		sort( $ours );

		$this->assertSame(
			$faker,
			$ours,
			"The locale list and FakerPHP's providers have drifted. Locales we offer that "
			. 'faker lacks generate English in silence; locales faker ships that we omit are '
			. 'simply unavailable. Update Locale::all().'
		);
	}

	public function test_default_is_supported(): void {
		$this->assertTrue( Locale::is_supported( Locale::DEFAULT_LOCALE ) );
	}

	public function test_every_locale_has_a_label(): void {
		foreach ( Locale::all() as $code => $label ) {
			$this->assertNotSame( '', trim( $label ), $code );
			$this->assertNotSame( $code, $label, "{$code} has no real label" );
		}
	}

	/**
	 * The Settings page maps a chosen label back to a code, so two locales sharing a
	 * label would make one of them unselectable.
	 */
	public function test_labels_are_unique(): void {
		$labels = array_values( Locale::all() );

		$this->assertSame( count( $labels ), count( array_unique( $labels ) ) );
	}

	public function test_is_supported_rejects_the_unknown(): void {
		$this->assertFalse( Locale::is_supported( 'xx_XX' ) );
		$this->assertFalse( Locale::is_supported( '' ) );
		// Was offered before this class existed, but faker has no provider for it.
		$this->assertFalse( Locale::is_supported( 'pt_AO' ) );
	}

	/**
	 * These three ship with faker and were missing from the old list.
	 */
	public function test_previously_missing_locales_are_available(): void {
		foreach ( array( 'ar_EG', 'ar_JO', 'en_CA' ) as $code ) {
			$this->assertTrue( Locale::is_supported( $code ), $code );
		}
	}

	public function test_label_falls_back_to_the_code(): void {
		$this->assertSame( 'English (United States)', Locale::label( 'en_US' ) );
		$this->assertSame( 'xx_XX', Locale::label( 'xx_XX' ) );
	}

	public function test_resolve_passes_a_supported_locale_through(): void {
		$this->assertSame( 'ja_JP', Locale::resolve( 'ja_JP' ) );
	}

	/**
	 * A site on a locale faker does not carry should still get data in its own
	 * language where any regional variant exists — de_LU is not a faker locale, but
	 * German is.
	 */
	public function test_resolve_falls_back_to_the_same_language(): void {
		$this->assertStringStartsWith( 'de_', Locale::resolve( 'de_LU' ) );
		$this->assertStringStartsWith( 'es_', Locale::resolve( 'es_MX' ) );
	}

	public function test_resolve_falls_back_to_the_default_when_nothing_matches(): void {
		$this->assertSame( Locale::DEFAULT_LOCALE, Locale::resolve( 'xx_XX' ) );
	}

	public function test_storeseeder_locale_filter_is_honoured(): void {
		add_filter(
			'storeseeder_locale',
			static function (): string {
				return 'fr_FR';
			}
		);

		$this->assertSame( 'fr_FR', Locale::resolve( 'en_US' ) );
	}

	public function test_locales_filter_can_narrow_the_list(): void {
		add_filter(
			'storeseeder_locales',
			static function (): array {
				return array( 'en_US' => 'English (United States)' );
			}
		);

		$this->assertSame( array( 'en_US' ), Locale::codes() );
	}

	/**
	 * A filter returning nothing would leave the plugin unable to generate at all, so
	 * the shipped list survives an empty or malformed return.
	 */
	public function test_a_filter_cannot_empty_the_list(): void {
		add_filter(
			'storeseeder_locales',
			static function (): array {
				return array();
			}
		);

		$this->assertNotEmpty( Locale::codes() );

		remove_all_filters( 'storeseeder_locales' );

		add_filter(
			'storeseeder_locales',
			static function () {
				return 'not an array';
			}
		);

		$this->assertNotEmpty( Locale::codes() );
	}
}
