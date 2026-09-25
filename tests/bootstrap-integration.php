<?php

if ( 'cli' !== PHP_SAPI && 'phpdbg' !== PHP_SAPI ) {
	exit;
}

$composer_autoload = dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! file_exists( $composer_autoload ) ) {
	echo 'Error: run `composer install` before running the tests.' . PHP_EOL;
	exit( 1 );
}

require_once $composer_autoload;

define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__ ) . '/vendor-bin/phpunit/vendor/yoast/phpunit-polyfills' );

putenv( 'WP_PHPUNIT__TESTS_CONFIG=' . __DIR__ . '/wp-tests-config.php' );

$wp_phpunit_dir = getenv( 'WP_PHPUNIT__DIR' );

if ( false === $wp_phpunit_dir || '' === $wp_phpunit_dir ) {
	$wp_phpunit_dir = dirname( __DIR__ ) . '/vendor/wp-phpunit/wp-phpunit';
}

$test_plugins_dir = dirname( __DIR__ ) . '/vendor/test-plugins';

require_once $wp_phpunit_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () use ( $test_plugins_dir ) {
		require $test_plugins_dir . '/woocommerce/woocommerce.php';
		require $test_plugins_dir . '/woocommerce-subscriptions/woocommerce-subscriptions.php';
		require dirname( __DIR__ ) . '/woocommerce-restrict-switch.php';
	}
);

tests_add_filter(
	'pre_option_active_plugins',
	static function () {
		return array(
			'woocommerce/woocommerce.php',
			'woocommerce-subscriptions/woocommerce-subscriptions.php',
		);
	}
);

tests_add_filter(
	'setup_theme',
	static function () {
		WC_Install::install();
	}
);

tests_add_filter(
	'action_scheduler_init',
	static function () {
		WC_Subscriptions_Plugin::instance()->activate_plugin();
	}
);

require $wp_phpunit_dir . '/includes/bootstrap.php';
