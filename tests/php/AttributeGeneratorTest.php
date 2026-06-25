<?php
use FluentCartFakerPress\Generators\Attribute;
use PHPUnit\Framework\TestCase;

class AttributeGeneratorTest extends TestCase {
	public function test_generate_single_attribute_returns_group_with_terms(): void {
		if ( ! defined( 'FLUENT_CART_VERSION' ) ) {
			$this->markTestSkipped( 'Fluent Cart not loaded in test env.' );
		}
		$gen = new Attribute();
		$gen->set_locale( 'en_US' );
		$gen->set_faker();
		$gen->set_generation_params( array() );
		$result = $gen->generate( 1 );
		$this->assertIsArray( $result );
		$this->assertCount( 1, $result );
		$this->assertArrayHasKey( 'id', $result[0] );
		$this->assertNotEmpty( $result[0]['values'] );
	}
}
