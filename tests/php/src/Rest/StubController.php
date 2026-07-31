<?php
/**
 * A REST controller that exists only for tests.
 *
 * Lets the registry be exercised with a controller the plugin does not ship, which is
 * the case that matters: a platform driver from another plugin exposing a resource of
 * its own.
 *
 * @package StoreSeeder\Tests
 */

namespace StoreSeeder\Tests\Rest;

use StoreSeeder\Generation\Generator;
use StoreSeeder\Generation\Generators\Product;
use StoreSeeder\Rest\Controller;

/**
 * Minimal fake controller.
 */
class StubController extends Controller {

	protected function get_rest_base(): string {
		return 'stub-things';
	}

	protected function get_resource_type(): string {
		return 'stub_thing';
	}

	protected function get_resource_type_label(): string {
		return 'Stub Thing';
	}

	protected function get_generator_instance(): Generator {
		// Borrowed: the registry does not care which generator a controller returns,
		// only that it returns one.
		return new Product();
	}
}
