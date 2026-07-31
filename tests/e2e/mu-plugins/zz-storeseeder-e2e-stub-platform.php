<?php
/**
 * A second platform, for the e2e suite.
 *
 * StoreSeeder's interesting paths only exist when more than one platform is active: the
 * topbar picker appears, the generator page refuses to run until a target is chosen, and
 * `Auto` has something to resolve between. A dev install has one platform, so the suite
 * supplies the second itself.
 *
 * It registers through `storeseeder_platforms` — the same public filter a third-party driver
 * would use — which means these tests also prove the extension point works from outside the
 * plugin. That is worth more than a mock would be.
 *
 * The driver writes nothing: `writer_classes()` is empty, so choosing this platform and
 * pressing Generate produces the missing-writer error, which is itself a path worth covering.
 *
 * Symlinked into wp-content/mu-plugins by the spec that needs it, and unlinked afterwards.
 * Loaded named `zz-` so it runs after everything else, StoreSeeder included.
 *
 * @package StoreSeeder\Tests\E2E
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'storeseeder_platforms',
	static function ( array $platforms ): array {
		// The plugin may be deactivated while this file is still linked; a filter that
		// assumed otherwise would fatal the whole admin rather than simply not registering.
		if ( ! class_exists( '\StoreSeeder\Platforms\Platform_Driver' ) ) {
			return $platforms;
		}

		$platforms[] = new class() extends \StoreSeeder\Platforms\Platform_Driver {
			public function id(): string {
				return 'stub-cart';
			}

			public function label(): string {
				return 'Stub Cart';
			}

			public function is_active(): bool {
				return true;
			}

			public function version(): ?string {
				return '2.0.0';
			}

			/**
			 * Everything supported except subscriptions, which reports a missing extension.
			 *
			 * One conditional capability is deliberate: it is what the specs assert against
			 * when checking that an unsupported resource names the plugin that would enable
			 * it rather than dimming a tile in silence.
			 *
			 * @return array<string, mixed>
			 */
			protected function capabilities(): array {
				$matrix = array();

				foreach ( \StoreSeeder\Platforms\Resource::all() as $resource_type ) {
					$matrix[ $resource_type ] = true;
				}

				$matrix[ \StoreSeeder\Platforms\Resource::SUBSCRIPTION ] =
					\StoreSeeder\Platforms\Capability::missing_extension(
						'stub-subs',
						'Stub Subscriptions'
					);

				return $matrix;
			}

			/**
			 * No writers: this platform exists to be *chosen*, not to store anything.
			 *
			 * @return array<string, string>
			 */
			protected function writer_classes(): array {
				return array();
			}
		};

		return $platforms;
	}
);
