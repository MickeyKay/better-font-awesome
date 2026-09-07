<?php
/**
 * Optional local delivery integration coverage.
 *
 * @package Better_Font_Awesome
 */

require_once __DIR__ . '/MetadataTestCase.php';

/** Verify delivery, persistence, and first-caller ownership together. */
class Better_Font_Awesome_Asset_Delivery_Test extends Better_Font_Awesome_Metadata_Test_Case {

	public function setUp(): void {
		parent::setUp();
		$this->use_default_release_channel();
	}

	public function test_default_automatic_schedules_without_http() {
		$plugin = $this->initialize_plugin( array() );
		$this->assertSame( 'automatic', $plugin->get_bfa_lib_instance()->get_asset_delivery() );
		$this->assertSame( 1, $this->count_scheduled_refresh_events() );
		$this->assertSame( 0, $this->font_awesome_http_calls );
	}

	public function test_local_before_cron_ignores_newer_remote_data_and_preserves_content() {
		$remote = $this->persist_schema_2_record( '7.99.0', time() - HOUR_IN_SECONDS );
		$legacy = $this->valid_release();
		set_transient( 'bfa-release-data', $legacy, DAY_IN_SECONDS );
		$content = '[icon name="flag"] <!-- wp:better-font-awesome/icon {"iconName":"heart","iconStyle":"regular"} /-->';
		$post = self::factory()->post->create( array( 'post_content' => $content ) );
		$automatic = $this->initialize_plugin( array( 'asset_delivery' => 'automatic' ) );
		$this->assertSame( '7.99.0', $automatic->get_bfa_lib_instance()->get_version() );
		$marker = get_option( Better_Font_Awesome_Metadata_Manager::SCHEDULE_OPTION );
		$state = get_option( Better_Font_Awesome_Metadata_Manager::STATE_OPTION );
		$schema = get_option( Better_Font_Awesome_Metadata_Manager::SCHEMA_OPTION );

		$plugin = $this->initialize_plugin( array( 'asset_delivery' => 'bundled-local', 'include_v4_shim' => 1 ) );
		$library = $plugin->get_bfa_lib_instance();
		$manager = $this->metadata_manager( $plugin );
		$this->assertSame( 'bundled-local', $library->get_asset_delivery() );
		$this->assertSame( '7.3.1', $library->get_version() );
		$this->assertSame( $this->valid_schema_2_record()['release'], $library->get_release_record()['release'] );
		$this->assertStringContainsString( '/inc/font-awesome-7-fallback/', $library->get_stylesheet_url() );
		$library->register_font_awesome_css();
		$library->request_release_data_refresh();
		$this->assertFalse( $manager->schedule_refresh( true ) );
		$this->assertSame( 'bfa_refresh_disabled', $manager->run_scheduled_refresh( $marker['token'], true )->get_error_code() );
		$this->assertSame( 'bfa_refresh_disabled', $manager->run_refresh( true )->get_error_code() );
		$this->assertSame( 'bfa_refresh_disabled', $library->refresh_release_data()->get_error_code() );
		$this->assertSame( 0, $this->count_scheduled_refresh_events() );
		$this->assertSame( $state, get_option( Better_Font_Awesome_Metadata_Manager::STATE_OPTION ) );
		$this->assertSame( $schema, get_option( Better_Font_Awesome_Metadata_Manager::SCHEMA_OPTION ) );
		$this->assertSame( $remote, get_option( Better_Font_Awesome_Metadata_Manager::RECORD_OPTION ) );
		$this->assertSame( $legacy, get_transient( 'bfa-release-data' ) );
		$this->assertSame( $content, get_post( $post )->post_content );
		ob_start();
		$plugin->asset_delivery_callback();
		$this->assertStringContainsString( 'Icons introduced after this version will not render', ob_get_clean() );

		$automatic = $this->initialize_plugin( array( 'asset_delivery' => 'automatic' ) );
		$this->assertSame( '7.99.0', $automatic->get_bfa_lib_instance()->get_version() );
		$this->assertSame( 1, $this->count_scheduled_refresh_events() );
		$this->assertSame( $remote, get_option( Better_Font_Awesome_Metadata_Manager::RECORD_OPTION ) );
		$this->assertSame( $content, get_post( $post )->post_content );
		$this->assertSame( 0, $this->font_awesome_http_calls );
	}

	public function test_queued_worker_rechecks_local_mode_without_boot_cleanup() {
		$manager = $this->metadata_manager( $this->initialize_plugin( array( 'asset_delivery' => 'automatic' ) ) );
		$marker = get_option( Better_Font_Awesome_Metadata_Manager::SCHEDULE_OPTION );
		$state = get_option( Better_Font_Awesome_Metadata_Manager::STATE_OPTION );
		$instance = new ReflectionProperty( Better_Font_Awesome_Library::class, 'instance' );
		$instance->setAccessible( true );
		$instance->setValue( null, null );
		$manager->set_library( Better_Font_Awesome_Library::get_instance( array( 'asset_delivery' => 'bundled-local' ) ) );
		$this->assertSame( 1, $this->count_scheduled_refresh_events() );
		$this->assertSame( 'bfa_refresh_disabled', $manager->run_scheduled_refresh( $marker['token'], true )->get_error_code() );
		$this->assertSame( 0, $this->count_scheduled_refresh_events() );
		$this->assertSame( $state, get_option( Better_Font_Awesome_Metadata_Manager::STATE_OPTION ) );
		$this->assertSame( 0, $this->font_awesome_http_calls );
	}

	public function test_local_bundle_failure_is_visible_even_when_notices_are_hidden() {
		$plugin = $this->initialize_plugin( array( 'asset_delivery' => 'bundled-local', 'hide_admin_notices' => 1 ) );
		$this->invoke_method( $plugin->get_bfa_lib_instance(), 'set_error', array( 'fallback', 'bfa_bundled_asset_unavailable', 'Missing bundled file' ) );
		ob_start();
		$plugin->asset_delivery_callback();
		$html = ob_get_clean();
		$this->assertStringContainsString( 'bundled files could not be loaded', $html );
		$this->assertStringContainsString( 'No third-party fallback', $html );
		ob_start();
		$plugin->version_check_frequency_callback();
		$this->assertStringContainsString( 'Background updates are disabled', ob_get_clean() );
		$this->assertSame( 0, $this->count_scheduled_refresh_events() );
	}

	public function test_fresh_remote_data_is_reused_when_returning_to_automatic() {
		$remote = $this->persist_schema_2_record( '7.99.0' );
		$this->initialize_plugin( array( 'asset_delivery' => 'bundled-local' ) );
		$library = $this->initialize_plugin( array( 'asset_delivery' => 'automatic' ) )->get_bfa_lib_instance();
		$this->assertSame( '7.99.0', $library->get_version() );
		$this->assertSame( 0, $this->count_scheduled_refresh_events() );
		$this->assertSame( $remote, get_option( Better_Font_Awesome_Metadata_Manager::RECORD_OPTION ) );
		$this->assertSame( 0, $this->font_awesome_http_calls );
	}

	public function test_local_does_not_migrate_legacy_storage_and_survives_reactivation() {
		$legacy = $this->valid_release();
		set_transient( 'bfa-release-data', $legacy, DAY_IN_SECONDS );
		$options = array( 'asset_delivery' => 'bundled-local', 'include_v4_shim' => 1 );
		$plugin = $this->initialize_plugin( $options );
		Better_Font_Awesome_Plugin::deactivate_metadata();
		Better_Font_Awesome_Plugin::activate();
		$this->assertSame( 0, $this->count_scheduled_refresh_events() );
		$this->assertFalse( get_option( Better_Font_Awesome_Metadata_Manager::SCHEMA_OPTION ) );
		$this->assertFalse( get_option( Better_Font_Awesome_Metadata_Manager::RECORD_OPTION ) );
		$this->assertSame( $legacy, get_transient( 'bfa-release-data' ) );
		$this->assertSame( $options, get_option( $plugin->get( 'option_name' ) ) );
		$this->assertSame( 0, $this->font_awesome_http_calls );
	}

	public function test_reactivation_uses_known_effective_local_mode_when_filter_overrides_setting() {
		$select_local = static function ( $args ) {
			$args['asset_delivery'] = 'bundled-local';
			return $args;
		};
		add_filter( 'bfa_init_args', $select_local );
		try {
			$this->initialize_plugin( array( 'asset_delivery' => 'automatic' ) );
			Better_Font_Awesome_Plugin::deactivate_metadata();
			Better_Font_Awesome_Plugin::activate();
			$this->assertSame( 0, $this->count_scheduled_refresh_events() );
			$this->assertSame( 0, $this->font_awesome_http_calls );
		} finally {
			remove_filter( 'bfa_init_args', $select_local );
		}
	}

	/** @dataProvider owner_modes */
	public function test_earlier_owner_remains_authoritative( $owner_mode, $requested, $channel, $effective ) {
		update_option( 'better-font-awesome_options', array( 'asset_delivery' => $requested ) );
		$owner = Better_Font_Awesome_Library::get_instance( array( 'asset_delivery' => $owner_mode, 'release_channel' => $channel ) );
		$plugin = Better_Font_Awesome_Plugin::get_instance();
		$this->assertSame( $owner, $plugin->get_bfa_lib_instance() );
		$this->assertSame( '' === $effective ? 'bundled-local' : $effective, $owner->get_asset_delivery() );
		ob_start();
		$plugin->asset_delivery_callback();
		$html = ob_get_clean();
		if ( '' === $effective ) {
			$this->assertStringContainsString( 'configuration is unsupported', $html );
			$this->assertStringNotContainsString( 'Effective delivery: Local files', $html );
			$this->assertSame( 'bfa_asset_delivery_channel_unsupported', $owner->refresh_release_data()->get_error_code() );
		} else {
			$this->assertStringContainsString( 'saved choice is not active', $html );
			$this->assertStringContainsString( 'Effective delivery: ' . ( 'automatic' === $effective ? 'Automatic updates (CDN)' : 'Local files' ), $html );
		}
		$this->assertSame( 'automatic' === $effective ? 1 : 0, $this->count_scheduled_refresh_events() );
		$this->assertSame( 0, $this->font_awesome_http_calls );
	}

	public static function owner_modes() {
		return array(
			array( 'automatic', 'bundled-local', '7.x', 'automatic' ),
			array( 'bundled-local', 'automatic', '7.x', 'bundled-local' ),
			array( 'bundled-local', 'bundled-local', '5.x', '' ),
		);
	}

	/** @dataProvider invalid_settings */
	public function test_invalid_settings_default_to_automatic( $value ) {
		$plugin = $this->initialize_plugin( array( 'asset_delivery' => $value ) );
		$this->assertSame( 'automatic', $plugin->get_bfa_lib_instance()->get_asset_delivery() );
		$this->assertSame( 'automatic', $plugin->sanitize( array( 'asset_delivery' => $value ) )['asset_delivery'] );
		$this->assertSame( 0, $this->font_awesome_http_calls );
	}

	public static function invalid_settings() {
		return array( array( null ), array( false ), array( array( 'bundled-local' ) ), array( '<script>local</script>' ), array( 'local' ) );
	}

	public function test_refresh_disabled_from_worker_does_not_record_failure_or_retry() {
		$plugin = $this->initialize_plugin( array( 'asset_delivery' => 'automatic' ) );
		$manager = $this->metadata_manager( $plugin );
		$library = new class() {
			public function get_release_channel() { return '7.x'; }
			public function get_asset_delivery() { return 'automatic'; }
			public function refresh_release_data() { return new WP_Error( 'bfa_refresh_disabled', 'Disabled' ); }
		};
		$manager->set_library( $library );
		$state = get_option( Better_Font_Awesome_Metadata_Manager::STATE_OPTION );
		$result = $this->run_scheduled_worker( $manager, false );
		$this->assertSame( 'bfa_refresh_disabled', $result->get_error_code() );
		$this->assertSame( $state, get_option( Better_Font_Awesome_Metadata_Manager::STATE_OPTION ) );
		$this->assertSame( 0, $this->count_scheduled_refresh_events() );
		$this->assertSame( 0, $this->font_awesome_http_calls );
	}
}
