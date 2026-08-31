<?php
/**
 * Shared helpers for metadata integration tests.
 *
 * @package Better_Font_Awesome
 */

/**
 * WordPress-backed metadata integration test base.
 */
abstract class Better_Font_Awesome_Metadata_Test_Case extends WP_UnitTestCase {

	/**
	 * Number of intercepted Font Awesome HTTP calls.
	 *
	 * @var int
	 */
	protected $font_awesome_http_calls = 0;

	/**
	 * Response returned for intercepted Font Awesome HTTP.
	 *
	 * @var array|WP_Error|null
	 */
	protected $font_awesome_http_response;

	/**
	 * Reset integration state.
	 */
	public function setUp(): void {
		parent::setUp();
		if ( 'rollback' === getenv( 'BFA_BFAL_VALIDATION_MODE' ) ) {
			$this->markTestSkipped( 'Expected current-only skip in the BFAL 2.0.3 rollback suite.' );
		}
		$this->reset_singletons();
		$this->clear_metadata_state();
		$this->font_awesome_http_calls    = 0;
		$this->font_awesome_http_response = null;
		add_filter( 'pre_http_request', array( $this, 'intercept_font_awesome_http' ), 5, 3 );
		add_filter( 'better_font_awesome_metadata_jitter', '__return_zero', 10, 3 );
	}

	/**
	 * Remove test state.
	 */
	public function tearDown(): void {
		remove_filter( 'pre_http_request', array( $this, 'intercept_font_awesome_http' ), 5 );
		remove_filter( 'better_font_awesome_metadata_jitter', '__return_zero', 10 );
		$_POST    = array();
		$_REQUEST = array();
		wp_set_current_user( 0 );
		$this->clear_metadata_state();
		$this->reset_singletons();
		parent::tearDown();
	}

	/**
	 * Intercept only the Font Awesome metadata endpoint.
	 *
	 * @param false|array|WP_Error $preempt     Existing preempted response.
	 * @param array                $parsed_args HTTP request arguments.
	 * @param string               $url         Request URL.
	 * @return false|array|WP_Error Preempted response.
	 */
	public function intercept_font_awesome_http( $preempt, $parsed_args, $url ) {
		unset( $parsed_args );
		if ( Better_Font_Awesome_Library::FONT_AWESOME_API_BASE_URL !== $url ) {
			return $preempt;
		}

		++$this->font_awesome_http_calls;
		if ( null !== $this->font_awesome_http_response ) {
			return $this->font_awesome_http_response;
		}

		return new WP_Error( 'unexpected_font_awesome_http', 'Unexpected Font Awesome HTTP request.' );
	}

	/**
	 * Return a valid release based on BFAL's bundled fixture.
	 *
	 * @param string $version Release version.
	 * @return array Valid release.
	 */
	protected function valid_release( $version = '5.15.4' ) {
		$contents = file_get_contents( dirname( __DIR__ ) . '/vendor/mickey-kay/better-font-awesome-library/inc/fallback-release-data.json' );
		$payload  = json_decode( $contents, true );
		$release  = $payload['data']['release'];
		$release['version'] = $version;
		return $release;
	}

	/**
	 * Persist one durable record.
	 *
	 * @param string   $version     Release version.
	 * @param int|null $fresh_until Freshness deadline.
	 * @return array Durable record.
	 */
	protected function persist_release( $version = '5.15.4', $fresh_until = null ) {
		$release    = $this->valid_release( $version );
		$validation = Better_Font_Awesome_Release_Data_Validator::validate_release( $release, 'api' );
		$fetched_at = time() - HOUR_IN_SECONDS;
		if ( null === $fresh_until ) {
			$fresh_until = time() + DAY_IN_SECONDS;
		}

		$store  = new Better_Font_Awesome_Metadata_Store();
		$record = $store->build_record( $validation['record'], $fetched_at, $fresh_until );
		$this->assertTrue( $store->persist_record( $record ) );
		return $record;
	}

	/**
	 * Initialize a fresh plugin and BFAL singleton.
	 *
	 * @param array|null $stored_options Existing BFA options.
	 * @return Better_Font_Awesome_Plugin Plugin instance.
	 */
	protected function initialize_plugin( $stored_options = null ) {
		$this->reset_singletons();
		if ( is_array( $stored_options ) ) {
			update_option( Better_Font_Awesome_Plugin::SLUG . '_options', $stored_options );
		}
		return Better_Font_Awesome_Plugin::get_instance();
	}

	/**
	 * Return the plugin metadata manager.
	 *
	 * @param Better_Font_Awesome_Plugin|null $plugin Plugin instance.
	 * @return Better_Font_Awesome_Metadata_Manager Metadata manager.
	 */
	protected function metadata_manager( $plugin = null ) {
		if ( null === $plugin ) {
			$plugin = $this->initialize_plugin();
		}
		return $plugin->get( 'metadata_manager' );
	}

	/**
	 * Schedule and consume the exact explicit worker event.
	 *
	 * @param Better_Font_Awesome_Metadata_Manager $manager Metadata manager.
	 * @param bool                                 $force   Whether retry timing is overridden.
	 * @return array|WP_Error|null Worker result.
	 */
	protected function run_scheduled_worker( $manager, $force = true ) {
		$manager->schedule_refresh( $force );
		$marker = get_option( Better_Font_Awesome_Metadata_Manager::SCHEDULE_OPTION, array() );
		$this->assertIsArray( $marker );
		$this->assertArrayHasKey( 'token', $marker );

		return $manager->run_scheduled_refresh( (string) $marker['token'], $force );
	}

	/**
	 * Build an HTTP response.
	 *
	 * @param int    $status HTTP status.
	 * @param string $body   Response body.
	 * @return array HTTP response.
	 */
	protected function http_response( $status, $body ) {
		return array(
			'headers'  => array(),
			'body'     => $body,
			'response' => array(
				'code'    => $status,
				'message' => 'Raw upstream response message',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	/**
	 * Build a successful API response.
	 *
	 * @param array $release Valid release.
	 * @return array HTTP response.
	 */
	protected function successful_response( $release ) {
		return $this->http_response(
			200,
			wp_json_encode( array( 'data' => array( 'release' => $release ) ) )
		);
	}

	/**
	 * Invoke an inaccessible method for ownership tests.
	 *
	 * @param object $object Object instance.
	 * @param string $method Method name.
	 * @param array  $args   Method arguments.
	 * @return mixed Method result.
	 */
	protected function invoke_method( $object, $method, $args = array() ) {
		$reflection = new ReflectionMethod( $object, $method );
		$reflection->setAccessible( true );
		return $reflection->invokeArgs( $object, $args );
	}

	/**
	 * Remove options, transient, events, and callbacks owned by test instances.
	 */
	private function clear_metadata_state() {
		wp_dequeue_style( 'bfa-font-awesome' );
		wp_dequeue_style( 'bfa-font-awesome-v4-shim' );
		wp_deregister_style( 'bfa-font-awesome' );
		wp_deregister_style( 'bfa-font-awesome-v4-shim' );
		wp_clear_scheduled_hook( Better_Font_Awesome_Metadata_Manager::CRON_HOOK );
		remove_all_actions( Better_Font_Awesome_Metadata_Manager::CRON_HOOK );
		delete_transient( 'bfa-release-data' );
		foreach (
			array(
				Better_Font_Awesome_Metadata_Store::RECORD_OPTION,
				Better_Font_Awesome_Metadata_Store::STATE_OPTION,
				Better_Font_Awesome_Metadata_Store::SCHEMA_OPTION,
				Better_Font_Awesome_Metadata_Manager::LOCK_OPTION,
				Better_Font_Awesome_Metadata_Manager::SCHEDULE_OPTION,
			) as $option_name
		) {
			delete_option( $option_name );
		}
	}

	/**
	 * Reset BFA and BFAL singletons.
	 */
	private function reset_singletons() {
		foreach (
			array(
				Better_Font_Awesome_Plugin::class,
				Better_Font_Awesome_Library::class,
			) as $class_name
		) {
			$property = new ReflectionProperty( $class_name, 'instance' );
			$property->setAccessible( true );
			$property->setValue( null, null );
		}
	}
}
