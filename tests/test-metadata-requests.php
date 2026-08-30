<?php
/**
 * Request-path, admin, and compatibility integration tests.
 *
 * @package Better_Font_Awesome
 */

require_once __DIR__ . '/MetadataTestCase.php';

/**
 * Validate no-request paths and administrator behavior.
 */
class Better_Font_Awesome_Metadata_Request_Test extends Better_Font_Awesome_Metadata_Test_Case {

	/**
	 * Frontend, admin, REST, editor, shortcode, and cron-triggering paths do no metadata HTTP.
	 */
	public function test_ordinary_wordpress_request_paths_perform_zero_metadata_http() {
		$this->persist_release( '5.15.4', time() - HOUR_IN_SECONDS );
		$plugin  = $this->initialize_plugin();
		$library = $plugin->get_bfa_lib_instance();

		do_action( 'wp_enqueue_scripts' );
		$plugin->add_settings();
		do_action( 'rest_api_init', rest_get_server() );
		do_action( 'enqueue_block_editor_assets' );
		do_shortcode( '[icon name="flag"]' );
		$library->get_version();
		$library->get_icons();
		$library->get_stylesheet_url();
		$library->register_font_awesome_css();
		wp_cron();

		$this->assertSame( 0, $this->font_awesome_http_calls );
		$this->assertSame( '5.15.4', $library->get_version() );
	}

	/**
	 * Only the explicit worker performs metadata HTTP.
	 */
	public function test_only_explicit_worker_performs_metadata_http() {
		$this->persist_release( '5.15.4', time() - HOUR_IN_SECONDS );
		$plugin  = $this->initialize_plugin();
		$manager = $this->metadata_manager( $plugin );
		$plugin->get_bfa_lib_instance()->get_version();
		$this->assertSame( 0, $this->font_awesome_http_calls );

		$this->font_awesome_http_response = $this->successful_response( $this->valid_release( '5.15.5' ) );
		$result = $this->run_scheduled_worker( $manager );

		$this->assertSame( '5.15.5', $result['version'] );
		$this->assertSame( 1, $this->font_awesome_http_calls );
	}

	/**
	 * A synthetic future 5.x patch updates all release-dependent paths together.
	 */
	public function test_synthetic_5_15_5_release_is_adopted_without_code_change() {
		$this->persist_release( '5.15.4' );
		$plugin  = $this->initialize_plugin(
			array(
				'include_v4_shim'    => 1,
				'remove_existing_fa' => 0,
				'hide_admin_notices' => 0,
			)
		);
		$manager = $this->metadata_manager( $plugin );
		$library = $plugin->get_bfa_lib_instance();
		$this->font_awesome_http_response = $this->successful_response( $this->valid_release( '5.15.5' ) );

		$this->assertSame( '5.15.5', $this->run_scheduled_worker( $manager )['version'] );
		$this->assertSame( '5.15.5', $library->get_version() );
		$this->assertStringContainsString( '/v5.15.5/css/all.css', $library->get_stylesheet_url() );
		$this->assertStringContainsString( '/v5.15.5/css/v4-shims.css', $library->get_stylesheet_url_v4_shim() );
		$this->assertSame( '5.15.5', ( new Better_Font_Awesome_Metadata_Store() )->get_valid_record()['release']['version'] );

		$library->register_font_awesome_css();
		$styles = wp_styles();
		$this->assertArrayHasKey( 'bfa-font-awesome', $styles->registered );
		$this->assertArrayHasKey( 'bfa-font-awesome-v4-shim', $styles->registered );
		$this->assertStringContainsString( '/v5.15.5/css/all.css', $styles->registered['bfa-font-awesome']->src );
		$this->assertStringContainsString( '/v5.15.5/css/v4-shims.css', $styles->registered['bfa-font-awesome-v4-shim']->src );
		$inline = implode( "\n", $styles->registered['bfa-font-awesome-v4-shim']->extra['after'] );
		$this->assertStringContainsString( '/v5.15.5/webfonts/fa-solid-900.woff2', $inline );
		$this->assertStringNotContainsString( '/v5.15.4/', $inline );
	}

	/**
	 * Existing settings, shortcode markup, filters, and handles remain compatible.
	 */
	public function test_existing_settings_shortcode_filters_and_handles_remain_compatible() {
		$this->persist_release();
		$options = array(
			'include_v4_shim'    => 1,
			'remove_existing_fa' => 0,
			'hide_admin_notices' => 0,
		);
		$plugin  = $this->initialize_plugin( $options );
		$library = $plugin->get_bfa_lib_instance();
		add_filter(
			'bfa_icon',
			function ( $output ) {
				return $output . '<!-- compatible -->';
			}
		);

		$output = $library->render_shortcode(
			array(
				'name'             => 'moon',
				'style'            => 'solid',
				'class'            => '2x spin',
				'unprefixed_class' => 'my-custom-class',
			)
		);
		$library->register_font_awesome_css();

		$this->assertSame( $options, $plugin->get( 'options' ) );
		$this->assertSame( '<i class="fas fa-moon fa-2x fa-spin my-custom-class " ></i><!-- compatible -->', $output );
		$this->assertTrue( wp_style_is( 'bfa-font-awesome', 'registered' ) );
		$this->assertTrue( wp_style_is( 'bfa-font-awesome-v4-shim', 'registered' ) );
	}

	/**
	 * Users without manage_options cannot schedule a refresh.
	 */
	public function test_manual_refresh_requires_manage_options() {
		$this->persist_release();
		$plugin = $this->initialize_plugin();
		$response = $this->call_manual_refresh( $plugin, 0, wp_create_nonce( Better_Font_Awesome_Plugin::SLUG . '-refresh-release-data' ) );

		$this->assertFalse( $response['success'] );
		$this->assertFalse( get_option( Better_Font_Awesome_Metadata_Manager::SCHEDULE_OPTION ) );
		$this->assertSame( 0, $this->font_awesome_http_calls );
	}

	/**
	 * Administrators must provide the dedicated refresh nonce.
	 */
	public function test_manual_refresh_requires_valid_nonce() {
		$this->persist_release();
		$plugin  = $this->initialize_plugin();
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$response = $this->call_manual_refresh( $plugin, $user_id, 'invalid' );

		$this->assertFalse( $response['success'] );
		$this->assertSame( 0, $this->font_awesome_http_calls );
	}

	/**
	 * A valid manual request only schedules work and returns immediately.
	 */
	public function test_manual_refresh_is_authenticated_and_asynchronous() {
		$this->persist_release();
		$plugin  = $this->initialize_plugin();
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		$nonce    = wp_create_nonce( Better_Font_Awesome_Plugin::SLUG . '-refresh-release-data' );
		$response = $this->call_manual_refresh( $plugin, $user_id, $nonce );

		$this->assertTrue( $response['success'] );
		$this->assertIsArray( get_option( Better_Font_Awesome_Metadata_Manager::SCHEDULE_OPTION ) );
		$this->assertSame( 0, $this->font_awesome_http_calls );
	}

	/**
	 * Status output is administrator-only and never exposes arbitrary upstream data.
	 */
	public function test_status_is_administrator_only_and_sanitized() {
		$plugin = $this->initialize_plugin();
		$store  = new Better_Font_Awesome_Metadata_Store();
		$state  = $store->get_state();
		$state['status']          = 'failed';
		$state['last_error_code'] = 'bfa_http_error';
		$state['last_error']      = 'Sanitized failure.';
		$store->save_state( $state );

		ob_start();
		$plugin->metadata_status_callback();
		$this->assertSame( '', ob_get_clean() );

		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		ob_start();
		$plugin->metadata_status_callback();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Sanitized failure.', $output );
		$this->assertStringNotContainsString( 'headers', $output );
		$this->assertStringNotContainsString( 'token=', $output );
	}

	/**
	 * Invoke manual refresh and decode its JSON response.
	 *
	 * @param Better_Font_Awesome_Plugin $plugin  Plugin instance.
	 * @param int                        $user_id User ID.
	 * @param string                     $nonce   Request nonce.
	 * @return array Decoded response and exception.
	 */
	private function call_manual_refresh( $plugin, $user_id, $nonce ) {
		if ( ! defined( 'DOING_AJAX' ) ) {
			define( 'DOING_AJAX', true );
		}
		wp_set_current_user( $user_id );
		$_POST['bfa_nonce']    = $nonce;
		$_REQUEST['bfa_nonce'] = $nonce;
		add_filter( 'wp_die_handler', array( $this, 'filter_wp_die_handler' ) );
		add_filter( 'wp_die_ajax_handler', array( $this, 'filter_wp_die_handler' ) );
		add_filter( 'wp_die_json_handler', array( $this, 'filter_wp_die_handler' ) );
		ob_start();

		$exception = null;
		try {
			$plugin->manual_refresh();
		} catch ( Better_Font_Awesome_Metadata_WP_Die_Exception $caught ) {
			$exception = $caught;
		}
		$json = ob_get_clean();
		remove_filter( 'wp_die_handler', array( $this, 'filter_wp_die_handler' ) );
		remove_filter( 'wp_die_ajax_handler', array( $this, 'filter_wp_die_handler' ) );
		remove_filter( 'wp_die_json_handler', array( $this, 'filter_wp_die_handler' ) );

		$response = json_decode( $json, true );
		$response['exception'] = $exception;
		return $response;
	}
}
