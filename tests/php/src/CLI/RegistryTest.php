<?php
/**
 * Tests for the WP-CLI command registry.
 *
 * The registry is deliberately usable without WP_CLI defined — that is what lets the
 * commands be tested at all, since the CLI runtime is absent under PHPUnit.
 *
 * @package StoreSeeder\Tests
 */

namespace StoreSeeder\Tests\CLI;

use StoreSeeder\CLI\Command;
use StoreSeeder\CLI\Registry;
use StoreSeeder\Tests\StoreSeederUnitTestCase;

/**
 * @covers \StoreSeeder\CLI\Registry
 */
class RegistryTest extends StoreSeederUnitTestCase {

	public function setUp(): void {
		parent::setUp();
		Registry::reset();
	}

	public function tearDown(): void {
		remove_all_filters( 'storeseeder_cli_commands' );
		Registry::reset();
		parent::tearDown();
	}

	public function test_the_shipped_commands_are_registered(): void {
		$this->assertSame(
			array( 'generate', 'preview', 'platforms', 'locales', 'sample-data' ),
			Registry::instance()->names()
		);
	}

	public function test_commands_are_keyed_by_name(): void {
		$all = Registry::instance()->all();

		$this->assertArrayHasKey( 'generate', $all );
		$this->assertTrue( is_subclass_of( $all['generate'], Command::class ) );
	}

	public function test_filter_can_add_a_command(): void {
		add_filter(
			'storeseeder_cli_commands',
			static function ( array $commands ): array {
				$commands[] = StubCommand::class;
				return $commands;
			}
		);

		$this->assertArrayHasKey( 'stub', Registry::instance()->all() );
		$this->assertCount( 6, Registry::instance()->all() );
	}

	public function test_filter_can_remove_a_command(): void {
		add_filter(
			'storeseeder_cli_commands',
			static function ( array $commands ): array {
				return array_values(
					array_filter(
						$commands,
						static function ( $command ): bool {
							return \StoreSeeder\CLI\Commands\Preview::class !== $command;
						}
					)
				);
			}
		);

		$this->assertNotContains( 'preview', Registry::instance()->names() );
	}

	public function test_malformed_entries_are_discarded(): void {
		add_filter(
			'storeseeder_cli_commands',
			static function ( array $commands ): array {
				$commands[] = 'Not\\A\\Class';
				$commands[] = new \stdClass();
				$commands[] = 42;
				// A real class, but not a Command.
				$commands[] = Registry::class;
				return $commands;
			}
		);

		$this->assertCount( 5, Registry::instance()->all() );
	}

	/**
	 * A command with no name would register as bare `wp storeseeder`, shadowing the
	 * namespace and every subcommand under it.
	 */
	public function test_a_nameless_command_is_discarded(): void {
		add_filter(
			'storeseeder_cli_commands',
			static function ( array $commands ): array {
				$commands[] = NamelessCommand::class;
				return $commands;
			}
		);

		$this->assertCount( 5, Registry::instance()->all() );
	}

	/**
	 * register_commands() is a no-op without the CLI runtime, so the plugin can call it
	 * unconditionally rather than every caller repeating the guard.
	 */
	public function test_registering_without_wp_cli_does_nothing_rather_than_fataling(): void {
		$this->assertFalse( defined( 'WP_CLI' ) && WP_CLI );

		Registry::instance()->register_commands();

		$this->assertCount( 5, Registry::instance()->all() );
	}
}

/**
 * A third-party command, as the filter would supply one.
 */
class StubCommand extends Command {
	const NAME = 'stub';

	public static function shortdesc(): string {
		return 'A stub command.';
	}

	public function __invoke( array $args, array $assoc_args ) {
		// Nothing: registration is what is under test.
	}
}

/**
 * The malformed case that would shadow the whole namespace.
 */
class NamelessCommand extends Command {
	public static function shortdesc(): string {
		return 'No name.';
	}

	public function __invoke( array $args, array $assoc_args ) {
		// Nothing.
	}
}
