<?php
use FluentCartFakerPress\Generators\Refund;
use PHPUnit\Framework\TestCase;

class RefundGeneratorTest extends TestCase {
	public function test_generate_refund_or_reports_no_order(): void {
		if ( ! defined( 'FLUENT_CART_VERSION' ) ) {
			$this->markTestSkipped( 'Fluent Cart not loaded in test env.' );
		}
		$gen = new Refund();
		$gen->set_locale( 'en_US' );
		$gen->set_faker();
		$gen->set_generation_params( array() );
		$result = $gen->generate( 1 );
		$this->assertIsArray( $result );
		if ( ! empty( $result ) ) {
			$this->assertArrayHasKey( 'order_id', $result[0] );
			$this->assertArrayHasKey( 'amount', $result[0] );
		}
	}
}
