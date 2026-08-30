<?php
/**
 * Deterministic metadata failure matrix.
 *
 * @package Better_Font_Awesome
 */

require_once __DIR__ . '/MetadataTestCase.php';

/**
 * Verify every failure retains last-known-good data.
 */
class Better_Font_Awesome_Metadata_Failure_Test extends Better_Font_Awesome_Metadata_Test_Case {

	/**
	 * Every transport and validation failure retains stale data.
	 *
	 * @dataProvider failure_response_provider
	 * @param string $scenario      Scenario name.
	 * @param mixed  $response      HTTP response or response factory name.
	 * @param string $expected_code Expected error code.
	 */
	public function test_failure_matrix_retains_stale_data( $scenario, $response, $expected_code ) {
		unset( $scenario );
		$prior   = $this->persist_release( '5.15.4', time() - HOUR_IN_SECONDS );
		$plugin  = $this->initialize_plugin();
		$manager = $this->metadata_manager( $plugin );

		if ( is_string( $response ) && method_exists( $this, $response ) ) {
			$response = $this->$response();
		}
		$this->font_awesome_http_response = $response;
		$result = $manager->run_refresh( true );
		$store  = new Better_Font_Awesome_Metadata_Store();
		$state  = $store->get_state();

		$this->assertWPError( $result );
		$this->assertSame( $expected_code, $result->get_error_code() );
		$this->assertSame( $prior, $store->get_valid_record() );
		$this->assertSame( '5.15.4', $plugin->get_bfa_lib_instance()->get_version() );
		$this->assertSame( 'failed', $state['status'] );
		$this->assertSame( 1, $state['failure_count'] );
		$this->assertGreaterThan( time(), $state['next_retry_at'] );
		$this->assertStringNotContainsString( 'secret', $state['last_error'] );
		$this->assertSame( 1, $this->font_awesome_http_calls );
	}

	/**
	 * Return deterministic failures.
	 *
	 * @return array Failure cases.
	 */
	public function failure_response_provider() {
		return array(
			'timeout'             => array( 'timeout', new WP_Error( 'http_request_failed_timeout', 'secret timeout trace' ), 'bfa_transport_error' ),
			'DNS'                 => array( 'DNS', new WP_Error( 'http_request_failed_dns', 'secret DNS trace' ), 'bfa_transport_error' ),
			'TLS'                 => array( 'TLS', new WP_Error( 'http_request_failed_ssl', 'secret TLS trace' ), 'bfa_transport_error' ),
			'403'                 => array( '403', 'response_403', 'bfa_http_error' ),
			'429'                 => array( '429', 'response_429', 'bfa_http_error' ),
			'500'                 => array( '500', 'response_500', 'bfa_http_error' ),
			'GraphQL errors'      => array( 'GraphQL', 'response_graphql_error', 'bfa_graphql_error' ),
			'invalid JSON'        => array( 'invalid JSON', 'response_invalid_json', 'bfa_invalid_json' ),
			'oversized response'  => array( 'oversized', 'response_oversized', 'bfa_response_too_large' ),
			'invalid schema'      => array( 'invalid schema', 'response_invalid_schema', 'bfa_schema_missing_release' ),
			'unsupported version' => array( 'unsupported version', 'response_unsupported_version', 'bfa_version_unsupported' ),
			'invalid SRI'         => array( 'invalid SRI', 'response_invalid_sri', 'bfa_asset_integrity_invalid' ),
			'invalid asset'       => array( 'invalid asset', 'response_invalid_asset', 'bfa_asset_path_invalid' ),
		);
	}

	/** @return array 403 response. */
	protected function response_403() {
		return $this->http_response( 403, 'secret raw body' );
	}

	/** @return array 429 response. */
	protected function response_429() {
		return $this->http_response( 429, 'secret raw body' );
	}

	/** @return array 500 response. */
	protected function response_500() {
		return $this->http_response( 500, 'secret raw body' );
	}

	/** @return array GraphQL error response. */
	protected function response_graphql_error() {
		return $this->http_response( 200, '{"errors":[{"message":"secret raw error"}]}' );
	}

	/** @return array Invalid JSON response. */
	protected function response_invalid_json() {
		return $this->http_response( 200, '{invalid secret' );
	}

	/** @return array Oversized response. */
	protected function response_oversized() {
		return $this->http_response( 200, str_repeat( 'x', Better_Font_Awesome_Release_Data_Validator::MAX_RESPONSE_BYTES + 1 ) );
	}

	/** @return array Invalid schema response. */
	protected function response_invalid_schema() {
		return $this->http_response( 200, '{"data":{}}' );
	}

	/** @return array Unsupported release response. */
	protected function response_unsupported_version() {
		$release = $this->valid_release( '6.0.0' );
		return $this->successful_response( $release );
	}

	/** @return array Invalid integrity response. */
	protected function response_invalid_sri() {
		$release = $this->valid_release();
		$release['srisByLicense']['free'][0]['value'] = 'sha384-secret';
		return $this->successful_response( $release );
	}

	/** @return array Invalid asset path response. */
	protected function response_invalid_asset() {
		$release = $this->valid_release();
		$release['srisByLicense']['free'][0]['path'] = '../secret.css';
		return $this->successful_response( $release );
	}
}
