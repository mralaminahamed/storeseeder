<?php
/**
 * MCP Ability: Generate Subscriptions
 *
 * @package StoreSeeder\MCP\Abilities
 * @since   2.4.0
 */

namespace StoreSeeder\MCP\Abilities;

use StoreSeeder\Abstracts\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Generate_Subscriptions
 *
 * Maps to REST endpoint: POST /storeseeder/v1/subscriptions/generate
 *
 * @since 1.0.0
 */
class Generate_Subscriptions extends Ability {

	const REST_BASE = 'subscriptions';
}
