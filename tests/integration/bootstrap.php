<?php

$tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $tests_dir ) {
	$tests_dir = sys_get_temp_dir() . '/wordpress-tests-lib';
}

if ( ! file_exists( $tests_dir . '/includes/functions.php' ) ) {
	echo "WordPress test library not found. Set WP_TESTS_DIR or run tests/bin/install-wp-tests.sh.\n";
	exit( 1 );
}

require_once $tests_dir . '/includes/functions.php';

define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__, 2 ) . '/vendor/yoast/phpunit-polyfills' );

if ( 'true' === getenv( 'WP_TESTS_MULTISITE' ) ) {
	define( 'WP_TESTS_MULTISITE', true );
}

tests_add_filter(
	'muplugins_loaded',
	static function () {
		require dirname( __DIR__, 2 ) . '/lightweight-seo.php';
	}
);

require $tests_dir . '/includes/bootstrap.php';
