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
		$this->assertStringContainsString( 'Local files use Font Awesome 7.3.1; your previously downloaded version is 7.99.0. Newer icons may be unavailable.', ob_get_clean() );

		$automatic = $this->initialize_plugin( array( 'asset_delivery' => 'automatic' ) );
		$this->assertSame( '7.99.0', $automatic->get_bfa_lib_instance()->get_version() );
		$this->assertSame( 1, $this->count_scheduled_refresh_events() );
		$this->assertSame( $remote, get_option( Better_Font_Awesome_Metadata_Manager::RECORD_OPTION ) );
		$this->assertSame( $content, get_post( $post )->post_content );
		$this->assertSame( 0, $this->font_awesome_http_calls );
	}

	/** @dataProvider catalog_messages */
	public function test_catalog_message_requires_valid_newer_preserved_data( $mode, $version, $invalid, $expected ) {
		if ( null !== $version ) {
			$record = $this->persist_schema_2_record( $version, time() - HOUR_IN_SECONDS );
			if ( 'checksum' === $invalid ) {
				$record['checksum'] = str_repeat( '0', 64 );
			} elseif ( 'release' === $invalid ) {
				$record['release']['version'] = '<script>7.99.0</script>';
				$record['checksum'] = hash( 'sha256', maybe_serialize( $record['release'] ) );
			}
			update_option( Better_Font_Awesome_Metadata_Store::RECORD_OPTION, $record );
		}
		$plugin = $this->initialize_plugin( array( 'asset_delivery' => $mode ) );
		$before = array_map( 'get_option', array( Better_Font_Awesome_Metadata_Store::RECORD_OPTION, Better_Font_Awesome_Metadata_Store::STATE_OPTION, Better_Font_Awesome_Metadata_Store::SCHEMA_OPTION ) );
		ob_start();
		$plugin->asset_delivery_callback();
		$html = ob_get_clean();
		$this->assertSame( $expected, false !== strpos( $html, 'your previously downloaded version' ) );
		if ( ! $expected ) {
			$this->assertStringNotContainsString( 'id="bfa-delivery-status"', $html );
		}
		$this->assertStringContainsString( 'type="checkbox" value="bundled-local"', $html );
		$this->assertSame( 'bundled-local' === $mode, false !== strpos( $html, ' checked=' ) );
		$this->assertStringContainsString( 'Load icons from your site instead of a third-party CDN. New icons arrive through plugin updates.', $html );
		$this->assertStringNotContainsString( 'Effective delivery:', $html );
		$this->assertStringNotContainsString( 'Bundled catalog:', $html );
		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertSame( $before, array_map( 'get_option', array( Better_Font_Awesome_Metadata_Store::RECORD_OPTION, Better_Font_Awesome_Metadata_Store::STATE_OPTION, Better_Font_Awesome_Metadata_Store::SCHEMA_OPTION ) ) );
		$this->assertSame( 0, $this->font_awesome_http_calls );
	}

	public static function catalog_messages() {
		return array(
			'no record local' => array( 'bundled-local', null, '', false ),
			'no record automatic' => array( 'automatic', null, '', false ),
			'equal' => array( 'bundled-local', '7.3.1', '', false ),
			'older' => array( 'bundled-local', '7.0.0', '', false ),
			'newer stale' => array( 'bundled-local', '7.99.0', '', true ),
			'bad checksum' => array( 'bundled-local', '7.99.0', 'checksum', false ),
			'invalid release' => array( 'bundled-local', '7.99.0', 'release', false ),
			'automatic with record' => array( 'automatic', '7.99.0', '', false ),
		);
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
		$this->assertStringContainsString( 'Local icon files are unavailable. Reinstall Better Font Awesome.', $html );
		$this->assertStringNotContainsString( 'previously downloaded', $html );
		$this->assertSame( 0, $this->font_awesome_http_calls );
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
		$this->assertSame( $effective, $owner->get_asset_delivery() );
		$this->assertSame( $effective, Better_Font_Awesome_Metadata_Manager::effective_asset_delivery( $owner ) );
		ob_start();
		$plugin->asset_delivery_callback();
		$html = ob_get_clean();
		if ( '' === $effective ) {
			$this->assertStringContainsString( 'configuration is unsupported', $html );
			$this->assertStringContainsString( 'This Font Awesome configuration is unsupported. Local files require Font Awesome 7 Free.', $html );
			$this->assertSame( 'bfa_asset_delivery_channel_unsupported', $owner->refresh_release_data()->get_error_code() );
		} else {
			$this->assertStringContainsString( 'Local delivery is ' . ( 'automatic' === $effective ? 'not active' : 'active' ) . ' because another plugin, theme, or filter controls Font Awesome.', $html );
		}
		$this->assertSame( 'bundled-local' === $requested, false !== strpos( $html, ' checked=' ) );
		$this->assertStringNotContainsString( 'Effective delivery:', $html );
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

	/** @dataProvider invalid_configurations */
	public function test_invalid_configuration_reports_no_effective_mode_and_does_no_work( $mode, $channel, $code ) {
		$owner = Better_Font_Awesome_Library::get_instance( array( 'asset_delivery' => $mode, 'release_channel' => $channel ) );
		$plugin = Better_Font_Awesome_Plugin::get_instance();
		$manager = $this->metadata_manager( $plugin );
		$this->assertSame( $owner, $plugin->get_bfa_lib_instance() );
		$this->assertSame( '', $owner->get_asset_delivery() );
		$this->assertSame( '', Better_Font_Awesome_Metadata_Manager::effective_asset_delivery( $owner ) );
		$this->assertSame( $code, $owner->refresh_release_data()->get_error_code() );
		$this->assertFalse( $manager->schedule_refresh( true ) );
		$this->assertSame( 0, $this->count_scheduled_refresh_events() );
		$this->assertSame( 0, $this->font_awesome_http_calls );
		ob_start();
		$plugin->asset_delivery_callback();
		$this->assertStringContainsString( 'configuration is unsupported', ob_get_clean() );
	}

	public static function invalid_configurations() {
		return array(
			array( 'invalid', '7.x', 'bfa_asset_delivery_unsupported' ),
			array( 'automatic', 'invalid', 'bfa_channel_unsupported' ),
			array( 'bundled-local', 'invalid', 'bfa_channel_unsupported' ),
			array( 'bundled-local', '5.x', 'bfa_asset_delivery_channel_unsupported' ),
		);
	}

	public function test_dependency_without_delivery_accessor_retains_automatic_compatibility() {
		$legacy_library = new stdClass();
		$this->assertSame( 'automatic', Better_Font_Awesome_Metadata_Manager::effective_asset_delivery( $legacy_library ) );
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
