<?php
/**
 * Tests for target platform resolution.
 *
 * The three resolution paths matter more than most: picking the wrong one writes rows
 * into the wrong store, and nothing about that failure is visible afterwards.
 *
 * @package StoreSeeder\Tests
 */

namespace StoreSeeder\Tests\Platform;

use StoreSeeder\Platforms\Registry;
use StoreSeeder\Platforms\Resolver;
use StoreSeeder\Tests\StoreSeederUnitTestCase;

/**
 * @covers \StoreSeeder\Platforms\Resolver
 */
class ResolverTest extends StoreSeederUnitTestCase {

	public function setUp(): void {
		parent::setUp();

		// The base case pins a target so the rest of the suite is not testing resolution by
		// accident. This class *is* testing resolution, so it starts from Auto.
		remove_all_filters( 'storeseeder_target_platform' );

		Registry::reset();
		delete_option( Resolver::OPTION );
	}

	public function tearDown(): void {
		remove_all_filters( 'storeseeder_platforms' );
		remove_all_filters( 'storeseeder_target_platform' );
		delete_option( Resolver::OPTION );
		Registry::reset();
		parent::tearDown();
	}

	/**
	 * Register additional stub drivers.
	 *
	 * @param array<int, array{0: string, 1: bool}> $specs id/active pairs.
	 *
	 * @return void
	 */
	private function register( array $specs ): void {
		add_filter(
			'storeseeder_platforms',
			static function ( array $platforms ) use ( $specs ): array {
				foreach ( $specs as $spec ) {
					$platforms[] = new StubPlatform( $spec[0], $spec[1] );
				}
				return $platforms;
			}
		);

		Registry::reset();
	}

	/**
	 * Replace the driver list outright, rather than appending to it.
	 *
	 * "The only active platform" has to be something a test establishes, not something it
	 * inherits from whichever plugins happen to be on the machine — these two cases started
	 * failing the moment a second shipped driver existed, which was a fact about the
	 * environment rather than about the resolver.
	 *
	 * @param array<int, array{0: string, 1: bool}> $specs id/active pairs.
	 *
	 * @return void
	 */
	private function only( array $specs ): void {
		add_filter(
			'storeseeder_platforms',
			static function () use ( $specs ): array {
				$platforms = array();

				foreach ( $specs as $spec ) {
					$platforms[] = new StubPlatform( $spec[0], $spec[1] );
				}

				return $platforms;
			}
		);

		Registry::reset();
	}

	public function test_auto_resolves_the_only_active_platform(): void {
		$this->only( array( array( 'solo-cart', true ) ) );

		$platform = ( new Resolver() )->resolve();

		$this->assertNotWPError( $platform );
		$this->assertSame( 'solo-cart', $platform->id() );
	}

	public function test_auto_is_ambiguous_with_two_active_platforms(): void {
		$this->register( array( array( 'stub-cart', true ) ) );

		$resolver = new Resolver();
		$result   = $resolver->resolve();

		$this->assertWPError( $result );
		$this->assertSame( 'storeseeder_platform_required', $result->get_error_code() );
		$this->assertSame( 409, $result->get_error_data()['status'] );
		$this->assertTrue( $resolver->is_ambiguous() );

		// The error has to say what the choices are, or the caller cannot recover.
		$ids = wp_list_pluck( $result->get_error_data()['candidates'], 'id' );
		$this->assertContains( 'fluent-cart', $ids );
		$this->assertContains( 'stub-cart', $ids );
	}

	public function test_stored_target_settles_ambiguity(): void {
		$this->register( array( array( 'stub-cart', true ) ) );

		$resolver = new Resolver();
		$this->assertTrue( $resolver->store( 'stub-cart' ) );

		$resolved = $resolver->resolve();

		$this->assertNotWPError( $resolved );
		$this->assertSame( 'stub-cart', $resolved->id() );
		$this->assertFalse( $resolver->is_ambiguous() );
	}

	/**
	 * Deactivating the stored platform must not leave the admin behind an error it
	 * cannot clear from the UI, so a stale target falls through to auto.
	 */
	public function test_stored_target_that_went_inactive_falls_through(): void {
		$this->only(
			array(
				array( 'gone-cart', false ),
				array( 'live-cart', true ),
			)
		);

		update_option( Resolver::OPTION, 'gone-cart', false );

		$resolved = ( new Resolver() )->resolve();

		$this->assertNotWPError( $resolved );
		$this->assertSame( 'live-cart', $resolved->id() );
	}

	public function test_explicit_request_wins(): void {
		$this->register( array( array( 'stub-cart', true ) ) );

		$resolved = ( new Resolver() )->resolve( 'stub-cart' );

		$this->assertNotWPError( $resolved );
		$this->assertSame( 'stub-cart', $resolved->id() );
	}

	public function test_unknown_platform_is_rejected(): void {
		$result = ( new Resolver() )->resolve( 'not-a-cart' );

		$this->assertWPError( $result );
		$this->assertSame( 'storeseeder_unknown_platform', $result->get_error_code() );
		$this->assertSame( 400, $result->get_error_data()['status'] );
	}

	public function test_inactive_platform_is_rejected(): void {
		$this->register( array( array( 'dead-cart', false ) ) );

		$result = ( new Resolver() )->resolve( 'dead-cart' );

		$this->assertWPError( $result );
		$this->assertSame( 'storeseeder_platform_inactive', $result->get_error_code() );
	}

	public function test_storing_auto_clears_the_option(): void {
		$resolver = new Resolver();
		$resolver->store( 'fluent-cart' );
		$this->assertSame( 'fluent-cart', $resolver->stored() );

		$resolver->store( Resolver::AUTO );
		$this->assertSame( '', $resolver->stored() );
	}

	public function test_storing_an_unknown_platform_is_rejected(): void {
		$result = ( new Resolver() )->store( 'not-a-cart' );

		$this->assertWPError( $result );
		$this->assertSame( '', ( new Resolver() )->stored() );
	}

	/**
	 * The filter applies to the raw request, so it overrides both the request and the
	 * stored option — and is still validated, so a typo fails loudly.
	 */
	public function test_filter_can_force_the_target(): void {
		$this->register( array( array( 'stub-cart', true ) ) );

		add_filter(
			'storeseeder_target_platform',
			static function (): string {
				return 'stub-cart';
			}
		);

		$resolved = ( new Resolver() )->resolve( 'fluent-cart' );

		$this->assertNotWPError( $resolved );
		$this->assertSame( 'stub-cart', $resolved->id() );
	}
}
