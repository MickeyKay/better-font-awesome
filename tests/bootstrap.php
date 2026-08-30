<?php
/**
 * PHPUnit bootstrap file
 *
 * @package Better_Font_Awesome
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find $_tests_dir/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	require dirname( dirname( __FILE__ ) ) . '/better-font-awesome.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

/**
 * Return deterministic Font Awesome metadata during tests.
 *
 * @param false|array|WP_Error $preempt     Preempted HTTP response.
 * @param array                $parsed_args HTTP request arguments.
 * @param string               $url         Request URL.
 * @return false|array|WP_Error
 */
function better_font_awesome_mock_http_request( $preempt, $parsed_args, $url ) {
	if ( 'https://api.fontawesome.com' !== $url ) {
		return $preempt;
	}
	if ( false !== $preempt ) {
		return $preempt;
	}

	return array(
		'headers'  => array(),
		'body'     => file_get_contents( dirname( __DIR__ ) . '/vendor/mickey-kay/better-font-awesome-library/inc/fallback-release-data.json' ),
		'response' => array(
			'code'    => 200,
			'message' => 'OK',
		),
		'cookies'  => array(),
		'filename' => null,
	);
}
tests_add_filter( 'pre_http_request', 'better_font_awesome_mock_http_request', 10, 3 );

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';
