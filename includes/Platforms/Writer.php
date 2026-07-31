<?php
/**
 * Abstract Writer Class for StoreSeeder
 *
 * A Writer persists one canonical resource into one platform. It is the only place in
 * the plugin that is allowed to name a platform's models, tables or status strings —
 * everything above it works in the neutral vocabulary of Platform\Resource.
 *
 * @since   1.1.0
 * @package StoreSeeder\Platforms
 */

namespace StoreSeeder\Platforms;

use Faker\Generator as Faker_Generator;

/**
 * Abstract Writer Class
 *
 * ## Where the boundary sits
 *
 * The split between Generator::build_entity() and Writer::write() is *not* "invented
 * data vs. saved data". It is "data that has no platform in it" vs. "everything that
 * touches the platform, in either direction".
 *
 * That matters because several resources cannot be shaped without reading real rows.
 * An order needs real product variations to have line items, and their real prices to
 * have a total; a refund needs an existing charge transaction to refund against; a
 * label needs orders to attach to. Resolving those foreign keys is the writer's job,
 * not the generator's, because each platform stores and randomises them differently.
 *
 * So a writer legitimately reads before it writes, and computes totals from what it
 * read. What it must never do is invent a name, an address, a date or a quantity —
 * those come in on the entity, already localised by FakerPHP, so that the same seed
 * produces the same data on every platform.
 *
 * ## The entity contract
 *
 * Two conventions hold across every entity, and every writer converts on the way out:
 *
 * - Money is an integer in the currency's minor unit, alongside an explicit
 *   `currency`. Fluent Cart stores cents already; WooCommerce, StoreEngine and
 *   EasyCommerce want decimals, so those writers divide. A float must never reach a
 *   writer, because binary rounding on a price is a real bug and an invisible one.
 * - Statuses use the canonical vocabulary — pending, processing, on_hold, completed,
 *   cancelled, failed, refunded — which each writer maps to its own spelling.
 *   WooCommerce needs a `wc-` prefix; Fluent Cart does not.
 *
 * @since 1.1.0
 */
abstract class Writer {
	/**
	 * FakerPHP generator, shared with the generator that owns this writer.
	 *
	 * Present so a writer can randomise a choice among rows it read — picking one of
	 * the real orders to attach to, say. Not for inventing field values.
	 *
	 * @since 1.1.0
	 * @var Faker_Generator|null
	 */
	protected $faker = null;

	/**
	 * Generation parameters from the REST request.
	 *
	 * @since 1.1.0
	 * @var array<string, mixed>
	 */
	protected array $params = array();

	/**
	 * The canonical resource this writer persists.
	 *
	 * @since 1.1.0
	 *
	 * @return string One of the Platform\Resource constants.
	 */
	abstract public function resource(): string;

	/**
	 * Persist one canonical entity.
	 *
	 * Returning array|WP_Error rather than an id is deliberate: it is the contract
	 * Generator::generate() already loops over, so per-item failures keep landing in
	 * the batch's error list instead of aborting the run.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $entity Canonical, platform-neutral entity.
	 *
	 * @return array<string, mixed>|\WP_Error The persisted item, or the reason it failed.
	 */
	abstract public function write( array $entity );

	/**
	 * Share the generator's FakerPHP instance.
	 *
	 * @since 1.1.0
	 *
	 * @param Faker_Generator $faker Configured, already seeded generator.
	 *
	 * @return void
	 */
	public function set_faker( Faker_Generator $faker ): void {
		$this->faker = $faker;
	}

	/**
	 * Share the generation parameters.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $params Parameters from the REST request.
	 *
	 * @return void
	 */
	public function set_params( array $params ): void {
		$this->params = $params;
	}

	/**
	 * The shared FakerPHP instance.
	 *
	 * @since 1.1.0
	 *
	 * @return Faker_Generator
	 */
	protected function faker(): Faker_Generator {
		return $this->faker;
	}
}
