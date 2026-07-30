<?php
/**
 * WordPress test configuration for StoreSeeder.
 *
 * Every value is driven by an environment variable declared in
 * phpunit.xml.dist, so the same file works locally and in CI — override any
 * variable in your shell or workflow to change the target. Nothing secret is
 * committed here.
 *
 * WARNING: the WordPress test suite DROPS AND RECREATES every table sharing
 * WP_TABLE_PREFIX. Never point this at a production database.
 *
 * @package StoreSeeder\Tests
 */

/*
 * Path to the WordPress installation used for testing. Defaults to the WP root
 * five levels up (wp-content/plugins/storeseeder/tests/php), which is correct
 * when the plugin sits inside a normal WordPress checkout.
 */
define( 'ABSPATH', rtrim( getenv( 'WP_PATH' ) ?: dirname( __DIR__, 5 ), '/\\' ) . DIRECTORY_SEPARATOR );

/* Active theme — kept as 'default' for headless CLI runs. */
define( 'WP_DEFAULT_THEME', 'default' );

/* Debug settings — surface everything, but never onto stdout. */
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );

/* Database credentials. */
define( 'DB_NAME', getenv( 'WP_DB_NAME' ) ?: 'wordpress_test' );
define( 'DB_USER', getenv( 'WP_DB_USER' ) ?: 'root' );
define( 'DB_PASSWORD', getenv( 'WP_DB_PASS' ) ?: '' );
define( 'DB_HOST', getenv( 'WP_DB_HOST' ) ?: 'localhost' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

/* Authentication keys and salts — fixed values, valid for testing only. */
define( 'AUTH_KEY', 'storeseeder-test-auth-key-not-for-production-use' );
define( 'SECURE_AUTH_KEY', 'storeseeder-test-secure-auth-key-not-for-production-use' );
define( 'LOGGED_IN_KEY', 'storeseeder-test-logged-in-key-not-for-production-use' );
define( 'NONCE_KEY', 'storeseeder-test-nonce-key-not-for-production-use' );
define( 'AUTH_SALT', 'storeseeder-test-auth-salt-not-for-production-use' );
define( 'SECURE_AUTH_SALT', 'storeseeder-test-secure-auth-salt-not-for-production-use' );
define( 'LOGGED_IN_SALT', 'storeseeder-test-logged-in-salt-not-for-production-use' );
define( 'NONCE_SALT', 'storeseeder-test-nonce-salt-not-for-production-use' );

/*
 * Table prefix for the test installation. Must differ from the production
 * prefix so a misconfigured run cannot drop real tables.
 */
$table_prefix = getenv( 'WP_TABLE_PREFIX' ) ?: 'storeseeder_test_'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

/* Test site identity. */
define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'StoreSeeder Test Blog' );

define( 'WP_PHP_BINARY', 'php' );
define( 'WPLANG', '' );
