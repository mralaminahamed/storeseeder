<?php
/**
 * WP-CLI command registry
 *
 * Holds the commands StoreSeeder registers under `wp storeseeder`. Shaped like
 * StoreSeeder\Rest\Registry, StoreSeeder\MCP\Registry and StoreSeeder\Platforms\Registry —
 * all four answer "what is available?", and there was no reason for the fourth to be
 * discovered a fourth way.
 *
 * @since   1.1.0
 * @package StoreSeeder\CLI
 */

namespace StoreSeeder\CLI;

defined( 'ABSPATH' ) || exit;

/**
 * Registry of WP-CLI commands.
 *
 * @since 1.1.0
 */
final class Registry {
	/**
	 * Shared instance.
	 *
	 * @since 1.1.0
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Command class names keyed by command name, or null before the filter has run.
	 *
	 * @since 1.1.0
	 * @var array<string, class-string<Command>>|null
	 */
	private $commands = null;

	/**
	 * Shared instance.
	 *
	 * @since 1.1.0
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Discard the shared instance and its memoised commands.
	 *
	 * For tests, which add commands through the filter between cases.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$instance = null;
	}

	/**
	 * Command classes shipped with the plugin.
	 *
	 * @since 1.1.0
	 *
	 * @return array<int, class-string<Command>>
	 */
	private function default_classes(): array {
		return array(
			Commands\Generate::class,
			Commands\Preview::class,
			Commands\Platforms::class,
			Commands\Locales::class,
			Commands\Recipe::class,
			Commands\Sample_Data::class,
			Commands\Cleanup::class,
		);
	}

	/**
	 * Every registered command class, keyed by command name.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, class-string<Command>>
	 */
	public function all(): array {
		if ( null !== $this->commands ) {
			return $this->commands;
		}

		/**
		 * Filters the WP-CLI commands StoreSeeder registers.
		 *
		 * Append a class name extending StoreSeeder\CLI\Command to add a subcommand under
		 * `wp storeseeder`, or remove one to withdraw it.
		 *
		 * @since 1.1.0
		 *
		 * @param array<int, mixed> $commands Command class names. Each entry is expected to
		 *                                    extend Command; anything else is discarded
		 *                                    rather than trusted, since a filter can return
		 *                                    whatever it likes.
		 */
		$commands = apply_filters( 'storeseeder_cli_commands', $this->default_classes() );

		$this->commands = array();

		foreach ( $commands as $command ) {
			// A malformed entry must not take down registration, which the other commands
			// share.
			if ( ! is_string( $command ) || ! class_exists( $command ) ) {
				continue;
			}

			if ( ! is_subclass_of( $command, Command::class ) ) {
				continue;
			}

			$name = $command::NAME;

			// A command with no name would register as bare `wp storeseeder`, shadowing the
			// namespace itself.
			if ( '' === $name ) {
				continue;
			}

			$this->commands[ $name ] = $command;
		}

		return $this->commands;
	}

	/**
	 * Command names, as typed after `wp storeseeder`.
	 *
	 * @since 1.1.0
	 *
	 * @return array<int, string>
	 */
	public function names(): array {
		return array_keys( $this->all() );
	}

	/**
	 * Register every command with WP-CLI.
	 *
	 * Called only when WP_CLI is defined; the registry itself is inspectable without it,
	 * which is what lets the command logic be tested in PHPUnit.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function register_commands(): void {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}

		foreach ( $this->all() as $name => $class ) {
			\WP_CLI::add_command(
				'storeseeder ' . $name,
				new $class(),
				array(
					'shortdesc' => $class::shortdesc(),
					'synopsis'  => $class::synopsis(),
				)
			);
		}
	}
}
