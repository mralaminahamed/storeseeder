<?php
/**
 * A platform driver that exists only for tests.
 *
 * Lets the registry and resolver be exercised with more than one platform without
 * needing a second e-commerce plugin on disk — which is the whole point of the
 * abstraction being filter-driven.
 *
 * @package StoreSeeder\Tests
 */

namespace StoreSeeder\Tests\Platform;

use StoreSeeder\Platforms\Platform_Driver;
use StoreSeeder\Platforms\Writer;
use StoreSeeder\Platforms\Capability;
use StoreSeeder\Platforms\Resource;

/**
 * Configurable stub driver.
 */
class StubPlatform extends Platform_Driver {

	/**
	 * @var string
	 */
	private string $id;

	/**
	 * @var bool
	 */
	private bool $active;

	/**
	 * @var array<string, Capability|bool>
	 */
	private array $matrix;

	/**
	 * @var array<string, string|Writer>
	 */
	private array $writers;

	/**
	 * @param string                        $id      Platform id.
	 * @param bool                          $active  Whether it reports as installed.
	 * @param array<string, Capability|bool> $matrix  Capability matrix.
	 * @param array<string, string|Writer>  $writers Writer map.
	 */
	public function __construct( string $id, bool $active = true, array $matrix = array(), array $writers = array() ) {
		$this->id      = $id;
		$this->active  = $active;
		$this->matrix  = $matrix ?: array( Resource::PRODUCT => true );
		$this->writers = $writers;
	}

	public function id(): string {
		return $this->id;
	}

	public function label(): string {
		return 'Stub ' . $this->id;
	}

	public function is_active(): bool {
		return $this->active;
	}

	public function version(): ?string {
		return $this->active ? '9.9.9' : null;
	}

	protected function capabilities(): array {
		return $this->matrix;
	}

	protected function writer_classes(): array {
		return $this->writers;
	}
}
