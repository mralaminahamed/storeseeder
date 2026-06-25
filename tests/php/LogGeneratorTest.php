<?php
use FluentCartFakerPress\Generators\Log;
use PHPUnit\Framework\TestCase;

class LogGeneratorTest extends TestCase {
	public function test_generate_log_or_skips_without_fluent_cart(): void {
		if ( ! defined( 'FLUENT_CART_VERSION' ) ) {
			$this->markTestSkipped( 'Fluent Cart not loaded in test env.' );
		}
		$gen = new Log();
		$gen->set_locale( 'en_US' );
		$gen->set_faker();
		$gen->set_generation_params( array() );
		$result = $gen->generate( 1 );
		$this->assertIsArray( $result );
		if ( ! empty( $result ) ) {
			$this->assertArrayHasKey( 'module_type', $result[0] );
			$this->assertArrayHasKey( 'title', $result[0] );
		}
	}
}
