<?php
/**
 * MCP Ability: Generate Labels
 *
 * @package StoreSeeder\MCP\Abilities\Resources
 * @since   2.4.0
 */

namespace StoreSeeder\MCP\Abilities\Resources;

use StoreSeeder\MCP\Abilities\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Generate_Labels
 *
 * Maps to REST endpoint: POST /storeseeder/v1/labels/generate
 *
 * @since 1.0.0
 */
class Generate_Labels extends Ability {

	const REST_BASE = 'labels';
}
