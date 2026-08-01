<?php
/**
 * An MCP ability that exists only for tests.
 *
 * @package StoreSeeder\Tests
 */

namespace StoreSeeder\Tests\MCP;

use StoreSeeder\MCP\Ability;

/**
 * Minimal stub ability.
 */
class StubAbility extends Ability {

	const REST_BASE = 'stub_things';

	public static function label(): string {
		return 'Generate Stub Things';
	}

	public static function description(): string {
		return 'Generates stub things, for tests only.';
	}

	protected static function output(): array {
		return array(
			'key'         => 'stub_things',
			'description' => 'Array of generated stub things.',
		);
	}
}
