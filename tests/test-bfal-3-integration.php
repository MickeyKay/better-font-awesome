<?php
/**
 * BFAL 3 default-channel integration tests.
 *
 * @package Better_Font_Awesome
 */

require_once __DIR__ . '/MetadataTestCase.php';

/**
 * Validate the immediate Font Awesome 7 upgrade and channel-aware persistence.
 */
class Better_Font_Awesome_BFAL_3_Integration_Test extends Better_Font_Awesome_Metadata_Test_Case {

	/**
	 * A clean installation uses BFAL's packaged FA7 fallback without HTTP.
	 */
	public function test_clean_install_uses_default_fa7_fallback_immediately_without_http() {
		$this->use_default_release_channel();
		$plugin  = $this->initialize_plugin();
		$library = $plugin->get_bfa_lib_instance();
		$record  = $library->get_release_record();

		$this->assertSame( '7.x', $library->get_release_channel() );
		$this->assertSame( '7.3.1', $library->get_version() );
		$this->assertSame( 2, $record['schema_version'] );
		$this->assertSame( '7.x', $record['channel'] );
		$this->assertStringEndsWith( '/inc/font-awesome-7-fallback/css/all.min.css', $library->get_stylesheet_url() );
		$this->assertSame( 0, $this->font_awesome_http_calls );
		$this->assertIsArray( get_option( Better_Font_Awesome_Metadata_Manager::SCHEDULE_OPTION ) );
	}

	/**
	 * Released BFA 2.1.0 metadata remains stored but cannot become active on FA7.
	 */
	public function test_upgrade_ignores_but_preserves_released_fa5_data() {
		$legacy_release = $this->valid_release( '5.15.4' );
		$legacy_record  = $this->persist_release( '5.15.4', time() + DAY_IN_SECONDS );
		set_transient( 'bfa-release-data', $legacy_release, DAY_IN_SECONDS );
		$this->use_default_release_channel();

		$plugin  = $this->initialize_plugin();
		$library = $plugin->get_bfa_lib_instance();
		$store   = new Better_Font_Awesome_Metadata_Store();

		$this->assertSame( '7.x', $library->get_release_channel() );
		$this->assertSame( '7.3.1', $library->get_version() );
		$this->assertSame( array(), $store->get_valid_record( '7.x' ) );
		$this->assertSame( $legacy_record, $store->get_valid_record( '5.x' ) );
		$this->assertSame( $legacy_release, get_transient( 'bfa-release-data' ) );
		$this->assertSame( 0, $this->font_awesome_http_calls );
	}

	/**
	 * Durable records are returned only to their matching selected channel.
	 */
	public function test_schema_1_and_schema_2_records_are_channel_matched() {
		$store    = new Better_Font_Awesome_Metadata_Store();
		$schema_1 = $this->persist_release();

		$this->assertSame( $schema_1, $store->get_valid_record( '5.x' ) );
		$this->assertSame( array(), $store->get_valid_record( '7.x' ) );

		$schema_2 = $this->persist_schema_2_record();
		$this->assertSame( $schema_2, $store->get_valid_record( '7.x' ) );
		$this->assertSame( array(), $store->get_valid_record( '5.x' ) );

		$this->use_default_release_channel();
		$manager = new Better_Font_Awesome_Metadata_Manager();
		$this->assertSame( $schema_2, $manager->provide_release_data() );

		update_option( Better_Font_Awesome_Metadata_Store::RECORD_OPTION, $schema_1, false );
		$this->assertSame( array(), $manager->provide_release_data() );
	}

	/**
	 * An old FA5 transient never migrates into the active FA7 record.
	 */
	public function test_fa5_transient_migration_is_inert_on_default_fa7_channel() {
		$legacy = $this->valid_release( '5.15.4' );
		set_transient( 'bfa-release-data', $legacy, DAY_IN_SECONDS );
		$this->use_default_release_channel();

		$plugin = $this->initialize_plugin();
		$store  = new Better_Font_Awesome_Metadata_Store();

		$this->assertSame( '7.3.1', $plugin->get_bfa_lib_instance()->get_version() );
		$this->assertSame( array(), $store->get_valid_record( '7.x' ) );
		$this->assertSame( $legacy, get_transient( 'bfa-release-data' ) );
		$this->assertSame( 1, get_option( Better_Font_Awesome_Metadata_Store::SCHEMA_OPTION ) );
		$this->assertSame( 0, $this->font_awesome_http_calls );
	}

	/**
	 * The explicit worker calls BFAL and persists its complete schema-2 result.
	 */
	public function test_successful_fa7_refresh_persists_complete_schema_2_record() {
		$this->use_default_release_channel();
		$plugin  = $this->initialize_plugin();
		$manager = $this->metadata_manager( $plugin );
		$current = $this->valid_schema_2_record();
		$this->font_awesome_http_response = $this->successful_response( $current['release'] );

		$result = $this->run_scheduled_worker( $manager );
		$stored = ( new Better_Font_Awesome_Metadata_Store() )->get_valid_record( '7.x' );

		$this->assertSame( 2, $result['schema_version'] );
		$this->assertSame( '7.x', $result['channel'] );
		$this->assertSame( $result['release'], $stored['release'] );
		$this->assertSame( 2, $stored['schema_version'] );
		$this->assertSame( 'fresh', $manager->get_status()['status'] );
		$this->assertSame( 1, $this->font_awesome_http_calls );
	}

	/**
	 * A malformed FA7 refresh leaves the durable last-known-good record usable.
	 */
	public function test_malformed_fa7_refresh_retains_last_known_good_and_retries() {
		$prior = $this->persist_schema_2_record( '7.3.1', time() - HOUR_IN_SECONDS );
		$this->use_default_release_channel();
		$plugin  = $this->initialize_plugin();
		$manager = $this->metadata_manager( $plugin );
		$this->font_awesome_http_response = $this->http_response( 200, '{invalid' );

		$result = $this->run_scheduled_worker( $manager );
		$store  = new Better_Font_Awesome_Metadata_Store();
		$state  = $store->get_state();

		$this->assertWPError( $result );
		$this->assertSame( 'bfa_v2_invalid_json', $result->get_error_code() );
		$this->assertSame( $prior, $store->get_valid_record( '7.x' ) );
		$this->assertSame( '7.3.1', $plugin->get_bfa_lib_instance()->get_version() );
		$this->assertSame( 'failed', $state['status'] );
		$this->assertGreaterThan( time(), $state['next_retry_at'] );
		$this->assertIsArray( get_option( Better_Font_Awesome_Metadata_Manager::SCHEDULE_OPTION ) );
		$this->assertSame( 1, $this->font_awesome_http_calls );
	}

	/**
	 * A schema-2 persistence failure retains the prior record and retry state.
	 */
	public function test_fa7_persistence_failure_retains_last_known_good_and_retries() {
		global $wpdb;

		$prior = $this->persist_schema_2_record( '7.3.1', time() - HOUR_IN_SECONDS );
		$this->use_default_release_channel();
		$plugin  = $this->initialize_plugin();
		$manager = $this->metadata_manager( $plugin );
		$current = $this->valid_schema_2_record();
		$this->font_awesome_http_response = $this->successful_response( $current['release'] );
		$intercept_write = function ( $query ) use ( $wpdb ) {
			if ( preg_match( '/^UPDATE/', $query ) && false !== strpos( $query, Better_Font_Awesome_Metadata_Store::RECORD_OPTION ) ) {
				return "UPDATE {$wpdb->options} SET invalid_bfa_test_column = 1";
			}

			return $query;
		};
		add_filter( 'query', $intercept_write );
		$previous_suppress_errors = $wpdb->suppress_errors( true );

		try {
			$result = $this->run_scheduled_worker( $manager );
			$store  = new Better_Font_Awesome_Metadata_Store();
			$state  = $store->get_state();

			$this->assertWPError( $result );
			$this->assertSame( 'bfa_durable_write_failed', $result->get_error_code() );
			$this->assertSame( $prior, $store->get_valid_record( '7.x' ) );
			$this->assertSame( 'failed', $state['status'] );
			$this->assertGreaterThan( time(), $state['next_retry_at'] );
			$this->assertIsArray( get_option( Better_Font_Awesome_Metadata_Manager::SCHEDULE_OPTION ) );
			$this->assertSame( 1, $this->font_awesome_http_calls );
		} finally {
			$wpdb->suppress_errors( $previous_suppress_errors );
			remove_filter( 'query', $intercept_write );
		}
	}
}
