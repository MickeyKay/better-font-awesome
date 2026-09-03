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
	 * A deliberate earlier BFAL owner keeps its configuration without blocking requests.
	 */
	public function test_deliberate_earlier_bfal_owner_remains_authoritative_and_nonblocking() {
		$release = $this->valid_release( '5.15.3' );
		set_transient( 'bfa-release-data', $release, DAY_IN_SECONDS );
		$earlier = Better_Font_Awesome_Library::get_instance(
			array(
				'release_channel'      => '5.x',
				'include_v4_shim'     => false,
				'remove_existing_fa'  => false,
				'load_styles'         => false,
				'load_admin_styles'   => false,
				'load_shortcode'      => false,
				'load_tinymce_plugin' => false,
			)
		);
		$plugin  = Better_Font_Awesome_Plugin::get_instance();
		$library = $plugin->get_bfa_lib_instance();
		$args    = new ReflectionProperty( Better_Font_Awesome_Library::class, 'args' );
		$args->setAccessible( true );
		$first_configuration = $args->getValue( $library );

		$this->assertSame( $earlier, $library );
		$this->assertSame( '5.x', $library->get_release_channel() );
		$this->assertFalse( $first_configuration['load_styles'] );
		$this->assertFalse( $first_configuration['load_admin_styles'] );
		$this->assertFalse( $first_configuration['load_shortcode'] );
		$this->assertFalse( $first_configuration['load_tinymce_plugin'] );
		$this->assertNull( $first_configuration['release_data_provider'] );
		$this->assertNull( $first_configuration['release_data_refresh_callback'] );

		do_action( 'wp_enqueue_scripts' );
		$plugin->add_settings();
		do_action( 'rest_api_init', rest_get_server() );
		do_action( 'enqueue_block_editor_assets' );
		do_shortcode( '[icon name="flag"]' );
		$library->get_version();
		$library->get_icons();
		$library->get_stylesheet_url();
		$this->metadata_manager( $plugin )->schedule_refresh();

		$this->assertSame( '5.15.3', $library->get_version() );
		$this->assertSame( 0, $this->font_awesome_http_calls );
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
	 * The native block layout survives the existing Font Awesome cleanup setting.
	 */
	public function test_block_layout_style_survives_existing_font_awesome_cleanup() {
		$this->use_default_release_channel();
		$plugin = $this->initialize_plugin(
			array(
				'include_v4_shim'    => 1,
				'remove_existing_fa' => 1,
				'hide_admin_notices' => 0,
			)
		);
		$block_type       = $plugin->get( 'icon_block' )->register();
		$layout_handle    = reset( $block_type->style_handles );
		$competing_handle = 'theme-font-awesome';

		wp_register_style( $competing_handle, 'https://example.org/font-awesome.css', array(), '1.0.0' );
		wp_enqueue_style( $layout_handle );
		wp_enqueue_style( $competing_handle );

		try {
			do_action( 'wp_enqueue_scripts' );

			$this->assertTrue( wp_style_is( $layout_handle, 'enqueued' ) );
			$this->assertFalse( wp_style_is( $competing_handle, 'enqueued' ) );
			$this->assertTrue( wp_style_is( 'bfa-font-awesome', 'enqueued' ) );
			$this->assertSame( 0, $this->font_awesome_http_calls );
		} finally {
			wp_dequeue_style( $layout_handle );
			wp_deregister_style( $layout_handle );
			wp_dequeue_style( $competing_handle );
			wp_deregister_style( $competing_handle );
			unregister_block_type( Better_Font_Awesome_Icon_Block::NAME );
		}
	}

	/**
	 * The settings page keeps existing controls without exposing metadata state or controls.
	 */
	public function test_settings_page_is_background_only_and_performs_zero_metadata_http() {
		$this->persist_release( '5.15.4', time() - HOUR_IN_SECONDS );
		$plugin = $this->initialize_plugin();
		$store  = new Better_Font_Awesome_Metadata_Store();
		$state  = $store->get_state();
		$state['status']          = 'failed';
		$state['fetched_at']      = 1234567890;
		$state['last_error_code'] = 'bfa_ui_regression_sentinel';
		$state['last_error']      = 'Metadata UI regression sentinel.';
		$store->save_state( $state );

		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		$plugin->add_settings();
		ob_start();
		$plugin->create_admin_page();
		$output = ob_get_clean();

		wp_dequeue_script( Better_Font_Awesome_Plugin::SLUG . '-admin' );
		wp_deregister_script( Better_Font_Awesome_Plugin::SLUG . '-admin' );
		$plugin->admin_enqueue_scripts( 'settings_page_better-font-awesome' );
		$script         = wp_scripts()->registered[ Better_Font_Awesome_Plugin::SLUG . '-admin' ];
		$localized_data = isset( $script->extra['data'] ) ? $script->extra['data'] : '';

		$this->assertStringContainsString( 'id="bfa-settings-form"', $output );
		$this->assertStringContainsString( 'id="include_v4_shim"', $output );
		$this->assertStringContainsString( 'id="remove_existing_fa"', $output );
		$this->assertStringContainsString( 'id="hide_admin_notices"', $output );
		$this->assertStringContainsString( 'bfa-save-settings-button', $output );
		$this->assertStringContainsString( 'ajax_url', $localized_data );
		$this->assertStringNotContainsString( 'Font Awesome metadata', $output );
		$this->assertStringNotContainsString( 'Refresh metadata', $output );
		$this->assertStringNotContainsString( 'Fetched:', $output );
		$this->assertStringNotContainsString( 'Metadata UI regression sentinel.', $output );
		$this->assertStringNotContainsString( 'bfa-metadata-status', $output );
		$this->assertStringNotContainsString( 'bfa-refresh-metadata', $output );
		$this->assertStringNotContainsString( 'refresh_nonce', $localized_data );
		$this->assertSame( 0, $this->font_awesome_http_calls );
	}

	/**
	 * Only the existing settings-save AJAX action remains registered.
	 */
	public function test_manual_metadata_ajax_action_is_not_registered() {
		$plugin = $this->initialize_plugin();

		$this->assertFalse( has_action( 'wp_ajax_bfa_refresh_release_data' ) );
		$this->assertFalse( method_exists( $plugin, 'manual_refresh' ) );
		$this->assertFalse( method_exists( $plugin, 'metadata_status_callback' ) );
		$this->assertNotFalse( has_action( 'wp_ajax_bfa_save_options', array( $plugin, 'save_options' ) ) );
	}
}
