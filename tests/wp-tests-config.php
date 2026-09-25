<?php

if ( 'cli' !== PHP_SAPI && 'phpdbg' !== PHP_SAPI ) {
	exit;
}

function wrd_tests_env( $name, $default ) {
	$value = getenv( $name );

	return false === $value || '' === $value ? $default : $value;
}

define( 'ABSPATH', rtrim( wrd_tests_env( 'WP_CORE_DIR', dirname( __DIR__ ) . '/vendor/wordpress' ), '/' ) . '/' );

define( 'DB_NAME', wrd_tests_env( 'WP_TESTS_DB_NAME', 'wordpress_test' ) );
define( 'DB_USER', wrd_tests_env( 'WP_TESTS_DB_USER', 'root' ) );
define( 'DB_PASSWORD', wrd_tests_env( 'WP_TESTS_DB_PASSWORD', 'root' ) );
define( 'DB_HOST', wrd_tests_env( 'WP_TESTS_DB_HOST', 'mariadb' ) );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- the WordPress test suite reads this global.
$table_prefix = wrd_tests_env( 'WP_TESTS_TABLE_PREFIX', 'wptests_' );

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'LibreSign Test Site' );
define( 'WP_PHP_BINARY', 'php' );
define( 'WPLANG', '' );

define( 'WP_DEBUG', true );

define( 'AUTH_KEY', 'wrd-tests-auth-key' );
define( 'SECURE_AUTH_KEY', 'wrd-tests-secure-auth-key' );
define( 'LOGGED_IN_KEY', 'wrd-tests-logged-in-key' );
define( 'NONCE_KEY', 'wrd-tests-nonce-key' );
define( 'AUTH_SALT', 'wrd-tests-auth-salt' );
define( 'SECURE_AUTH_SALT', 'wrd-tests-secure-auth-salt' );
define( 'LOGGED_IN_SALT', 'wrd-tests-logged-in-salt' );
define( 'NONCE_SALT', 'wrd-tests-nonce-salt' );
