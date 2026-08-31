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
 * Validate the exact Composer-installed BFAL dependency and requested mode.
 *
 * @return string Validation mode.
 * @throws RuntimeException When mode or reference does not match.
 */
function better_font_awesome_validate_bfal_dependency() {
	$mode               = getenv( 'BFA_BFAL_VALIDATION_MODE' );
	$expected_version   = getenv( 'BFA_EXPECTED_BFAL_VERSION' );
	$expected_reference = getenv( 'BFA_EXPECTED_BFAL_REFERENCE' );
	$package            = 'mickey-kay/better-font-awesome-library';
	$reference          = Composer\InstalledVersions::getReference( $package );
	$version            = Composer\InstalledVersions::getPrettyVersion( $package );

	if ( ! in_array( $mode, array( 'current', 'rollback' ), true ) ) {
		throw new RuntimeException( 'Set BFA_BFAL_VALIDATION_MODE to current or rollback.' );
	}
	if ( ! is_string( $expected_version ) || '' === $expected_version ) {
		throw new RuntimeException( 'BFA_EXPECTED_BFAL_VERSION must be an exact public version.' );
	}
	if ( ! is_string( $expected_reference ) || ! preg_match( '/^[a-f0-9]{40}$/', $expected_reference ) ) {
		throw new RuntimeException( 'BFA_EXPECTED_BFAL_REFERENCE must be an exact 40-character commit SHA.' );
	}
	if ( ! is_string( $version ) || ! hash_equals( $expected_version, $version ) ) {
		throw new RuntimeException(
			sprintf(
				'BFAL %1$s validation requires version %2$s, but Composer installed %3$s.',
				$mode,
				$expected_version,
				is_string( $version ) ? $version : 'none'
			)
		);
	}
	if ( ! is_string( $reference ) || ! hash_equals( $expected_reference, $reference ) ) {
		throw new RuntimeException(
			sprintf(
				'BFAL %1$s validation requires reference %2$s, but Composer installed %3$s.',
				$mode,
				$expected_reference,
				is_string( $reference ) ? $reference : 'none'
			)
		);
	}

	fwrite(
		STDERR,
		sprintf(
			"BFAL validation mode: %1\$s; version: %2\$s; reference: %3\$s\n",
			$mode,
			is_string( $version ) ? $version : 'unknown',
			$reference
		)
	);

	return $mode;
}

$better_font_awesome_bfal_validation_mode = better_font_awesome_validate_bfal_dependency();

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	global $better_font_awesome_bfal_validation_mode;

	require dirname( dirname( __FILE__ ) ) . '/better-font-awesome.php';
	require_once dirname( __DIR__ ) . '/vendor/mickey-kay/better-font-awesome-library/better-font-awesome-library.php';

	$validator_methods = array( 'validate_release', 'validate_record' );
	$library_methods   = array(
		'get_release_record',
		'get_release_channel',
		'request_release_data_refresh',
		'refresh_release_data',
	);
	$has_current_api = class_exists( 'Better_Font_Awesome_Release_Data_Validator' ) &&
		defined( 'Better_Font_Awesome_Release_Data_Validator::RELEASE_CHANNEL' );
	foreach ( $validator_methods as $method ) {
		$has_current_api = $has_current_api && is_callable( array( 'Better_Font_Awesome_Release_Data_Validator', $method ) );
	}
	foreach ( $library_methods as $method ) {
		$has_current_api = $has_current_api && method_exists( 'Better_Font_Awesome_Library', $method );
	}
	if ( 'current' === $better_font_awesome_bfal_validation_mode && ! $has_current_api ) {
		throw new RuntimeException( 'Current mode requires the stable BFAL validator, provider record, asynchronous request, and explicit worker APIs.' );
	}
	if ( 'rollback' === $better_font_awesome_bfal_validation_mode && $has_current_api ) {
		throw new RuntimeException( 'Rollback mode requires BFAL 2.0.3 without the current refresh API.' );
	}
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
