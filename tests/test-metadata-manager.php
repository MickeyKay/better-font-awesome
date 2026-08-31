<?php
/**
 * Metadata scheduling and worker tests.
 *
 * @package Better_Font_Awesome
 */

require_once __DIR__ . '/MetadataTestCase.php';

/**
 * Worker that invokes a separate scheduler immediately after lock acquisition.
 */
class Better_Font_Awesome_Interleaving_Metadata_Manager extends Better_Font_Awesome_Metadata_Manager {

	/** @var Better_Font_Awesome_Metadata_Manager|null */
	public $concurrent_manager;

	/** @var bool|null */
	public $concurrent_schedule_result;

	/**
	 * Acquire ownership and run the deterministic scheduler interleaving.
	 *
	 * @param string $schedule_token Expected schedule token.
	 * @return string|false Ownership token, or false.
	 */
	protected function acquire_lock( $schedule_token = '' ) {
		$owner = parent::acquire_lock( $schedule_token );
		if ( false !== $owner && $this->concurrent_manager ) {
			$this->concurrent_schedule_result = $this->concurrent_manager->schedule_refresh();
		}
		return $owner;
	}
}

/**
 * Validate request behavior, scheduling, locks, and retry state.
 */
class Better_Font_Awesome_Metadata_Manager_Test extends Better_Font_Awesome_Metadata_Test_Case {

	/**
	 * A cold request returns fallback immediately and schedules exactly once.
	 */
	public function test_cold_request_returns_fallback_and_schedules_once_without_http() {
		$plugin  = $this->initialize_plugin();
		$library = $plugin->get_bfa_lib_instance();
		$manager = $this->metadata_manager( $plugin );
		$marker  = get_option( Better_Font_Awesome_Metadata_Manager::SCHEDULE_OPTION );

		$this->assertSame( '5.14.0', $library->get_version() );
		$this->assertSame( 0, $this->font_awesome_http_calls );
		$this->assertIsArray( $marker );
		$this->assertFalse( $manager->schedule_refresh() );
		$this->assertSame( $marker, get_option( Better_Font_Awesome_Metadata_Manager::SCHEDULE_OPTION ) );
	}

	/**
	 * Fresh durable data returns immediately without scheduling or transport.
	 */
	public function test_fresh_durable_data_returns_without_scheduling_or_http() {
		$this->persist_release( '5.15.4', time() + DAY_IN_SECONDS );
		$plugin = $this->initialize_plugin();

		$this->assertSame( '5.15.4', $plugin->get_bfa_lib_instance()->get_version() );
		$this->assertFalse( get_option( Better_Font_Awesome_Metadata_Manager::SCHEDULE_OPTION ) );
		$this->assertSame( 0, $this->font_awesome_http_calls );
	}

	/**
	 * Near-expiry and stale records keep serving while one refresh is scheduled.
	 *
	 * @dataProvider stale_deadline_provider
	 * @param int $fresh_until Freshness deadline.
	 */
	public function test_near_expiry_and_stale_data_are_served_while_scheduling_once( $fresh_until ) {
		$this->persist_release( '5.15.4', $fresh_until );
		$plugin  = $this->initialize_plugin();
		$manager = $this->metadata_manager( $plugin );
		$marker  = get_option( Better_Font_Awesome_Metadata_Manager::SCHEDULE_OPTION );

		$this->assertSame( '5.15.4', $plugin->get_bfa_lib_instance()->get_version() );
		$this->assertIsArray( $marker );
		$this->assertFalse( $manager->schedule_refresh() );
		$this->assertSame( 0, $this->font_awesome_http_calls );
	}

	/**
	 * Return near and past deadlines.
	 *
	 * @return array Deadlines.
	 */
	public function stale_deadline_provider() {
		return array(
			'near expiry' => array( time() + 30 * MINUTE_IN_SECONDS ),
			'stale'       => array( time() - HOUR_IN_SECONDS ),
		);
	}

	/**
	 * Atomic scheduling suppresses simultaneous calls.
	 */
	public function test_simultaneous_schedule_requests_create_one_event() {
		$manager = new Better_Font_Awesome_Metadata_Manager();

		$this->assertTrue( $manager->schedule_refresh() );
		$first = get_option( Better_Font_Awesome_Metadata_Manager::SCHEDULE_OPTION );
		$this->assertFalse( $manager->schedule_refresh() );
		$this->assertSame( $first, get_option( Better_Font_Awesome_Metadata_Manager::SCHEDULE_OPTION ) );
		$this->assertSame(
			$first['run_at'],
			wp_next_scheduled(
				Better_Font_Awesome_Metadata_Manager::CRON_HOOK,
				array( $first['token'], false )
			)
		);
	}

	/**
	 * A failed worker owns state after consuming a newly persisted event.
	 */
	public function test_scheduler_does_not_overwrite_interleaved_worker_failure() {
		$this->persist_release( '5.15.4', time() - HOUR_IN_SECONDS );
		$plugin    = $this->initialize_plugin();
		$scheduler = new Better_Font_Awesome_Metadata_Manager();
		$worker    = new Better_Font_Awesome_Metadata_Manager();
		$worker->set_library( $plugin->get_bfa_lib_instance() );
		wp_clear_scheduled_hook( Better_Font_Awesome_Metadata_Manager::CRON_HOOK );
		delete_option( Better_Font_Awesome_Metadata_Manager::SCHEDULE_OPTION );

		$store                    = new Better_Font_Awesome_Metadata_Store();
		$before                   = $store->get_state();
		$before['attempt_count']  = 4;
		$before['fetched_at']     = time() - DAY_IN_SECONDS;
		$before['failure_count']  = 2;
		$before['next_retry_at']  = 0;
		$before['status']         = 'stale';
		$before['scheduled_for']  = 0;
		$before['last_error_code'] = 'earlier_failure';
		$before['last_error']      = 'Earlier sanitized failure.';
		$this->assertTrue( $store->save_state( $before ) );
		$this->font_awesome_http_response = new WP_Error( 'bfa_transport_error', 'Deterministic transport failure.' );

		$interleaving = $this->schedule_with_post_persistence_worker( $scheduler, $worker );
		$state        = $store->get_state();
		$retry        = get_option( Better_Font_Awesome_Metadata_Manager::SCHEDULE_OPTION );

		$this->assertTrue( $interleaving['schedule_result'] );
		$this->assertWPError( $interleaving['worker_result'] );
		$this->assertSame( 'bfa_transport_error', $interleaving['worker_result']->get_error_code() );
		$this->assertSame( 'failed', $state['status'] );
		$this->assertSame( 3, $state['failure_count'] );
		$this->assertSame( 5, $state['attempt_count'] );
		$this->assertSame( $before['fetched_at'], $state['fetched_at'] );
		$this->assertGreaterThan( time(), $state['next_retry_at'] );
		$this->assertSame( 'bfa_transport_error', $state['last_error_code'] );
		$this->assertSame( 'The Font Awesome metadata service could not be reached.', $state['last_error'] );
		$this->assertIsArray( $retry );
		$this->assertNotSame( $interleaving['published_marker']['token'], $retry['token'] );
		$this->assertSame( $retry['run_at'], $state['scheduled_for'] );
		$this->assertSame(
			$retry['run_at'],
			wp_next_scheduled(
				Better_Font_Awesome_Metadata_Manager::CRON_HOOK,
				array( $retry['token'], false )
			)
		);
		$this->assertFalse(
			wp_next_scheduled(
				Better_Font_Awesome_Metadata_Manager::CRON_HOOK,
				array( $interleaving['published_marker']['token'], false )
			)
		);
		$this->assertSame( 1, $this->font_awesome_http_calls );
	}

	/**
	 * A successful worker owns state after consuming a newly persisted event.
	 */
	public function test_scheduler_does_not_overwrite_interleaved_worker_success() {
		$this->persist_release( '5.15.4', time() - HOUR_IN_SECONDS );
		$plugin    = $this->initialize_plugin();
		$scheduler = new Better_Font_Awesome_Metadata_Manager();
		$worker    = new Better_Font_Awesome_Metadata_Manager();
		$worker->set_library( $plugin->get_bfa_lib_instance() );
		wp_clear_scheduled_hook( Better_Font_Awesome_Metadata_Manager::CRON_HOOK );
		delete_option( Better_Font_Awesome_Metadata_Manager::SCHEDULE_OPTION );

		$store                     = new Better_Font_Awesome_Metadata_Store();
		$before                    = $store->get_state();
		$before['attempt_count']   = 7;
		$before['failure_count']   = 2;
		$before['next_retry_at']   = 0;
		$before['status']          = 'failed';
		$before['scheduled_for']   = 0;
		$before['last_error_code'] = 'earlier_failure';
		$before['last_error']      = 'Earlier sanitized failure.';
		$this->assertTrue( $store->save_state( $before ) );
		$this->font_awesome_http_response = $this->successful_response( $this->valid_release( '5.15.5' ) );
		$started_at = time();

		$interleaving = $this->schedule_with_post_persistence_worker( $scheduler, $worker );
		$state        = $store->get_state();

		$this->assertTrue( $interleaving['schedule_result'] );
		$this->assertSame( '5.15.5', $interleaving['worker_result']['version'] );
		$this->assertSame( 'fresh', $state['status'] );
		$this->assertSame( 8, $state['attempt_count'] );
		$this->assertGreaterThanOrEqual( $started_at, $state['fetched_at'] );
		$this->assertSame( 0, $state['failure_count'] );
		$this->assertSame( 0, $state['next_retry_at'] );
		$this->assertSame( '', $state['last_error_code'] );
		$this->assertSame( '', $state['last_error'] );
		$this->assertSame( 0, $state['scheduled_for'] );
		$this->assertSame( '5.15.5', $store->get_valid_record()['release']['version'] );
		$this->assertFalse( get_option( Better_Font_Awesome_Metadata_Manager::SCHEDULE_OPTION ) );
		$this->assertFalse(
			wp_next_scheduled(
				Better_Font_Awesome_Metadata_Manager::CRON_HOOK,
				array( $interleaving['published_marker']['token'], false )
			)
		);
		$this->assertSame( 1, $this->font_awesome_http_calls );
	}

	/**
	 * The first scheduling annotation is created without autoloading state.
	 */
	public function test_first_schedule_creates_non_autoloaded_state() {
		global $wpdb;

		delete_option( Better_Font_Awesome_Metadata_Manager::STATE_OPTION );
		$manager = new Better_Font_Awesome_Metadata_Manager();

		$this->assertTrue( $manager->schedule_refresh() );
		$marker = get_option( Better_Font_Awesome_Metadata_Manager::SCHEDULE_OPTION );
		$state  = ( new Better_Font_Awesome_Metadata_Store() )->get_state();
		$this->assertSame( 'scheduled', $state['status'] );
		$this->assertSame( $marker['run_at'], $state['scheduled_for'] );
		$this->assertArrayNotHasKey( Better_Font_Awesome_Metadata_Manager::STATE_OPTION, wp_load_alloptions( true ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Verify the persisted autoload mode across supported WordPress versions.
		$autoload = $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", Better_Font_Awesome_Metadata_Manager::STATE_OPTION ) );
		$this->assertContains( $autoload, array( 'no', 'off', 'auto-off' ) );
		$this->assertSame( 0, $this->font_awesome_http_calls );
	}

	/**
	 * A newly claimed marker is not stolen before its event is written.
	 */
	public function test_new_schedule_marker_without_event_is_temporarily_reserved() {
		$marker = array(
			'schema_version' => 1,
			'token'          => wp_generate_uuid4(),
			'created_at'     => time(),
			'run_at'         => time() + 1,
			'force'          => false,
		);
		add_option( Better_Font_Awesome_Metadata_Manager::SCHEDULE_OPTION, $marker, '', false );

		$manager = new Better_Font_Awesome_Metadata_Manager();
		$this->assertFalse( $manager->schedule_refresh() );
		$this->assertSame( $marker, get_option( Better_Font_Awesome_Metadata_Manager::SCHEDULE_OPTION ) );
	}

	/**
	 * An abandoned schedule marker is recovered.
	 */
	public function test_expired_schedule_marker_is_recovered() {
		$marker = array(
			'schema_version' => 1,
			'token'          => wp_generate_uuid4(),
			'created_at'     => time() - Better_Font_Awesome_Metadata_Manager::SCHEDULE_GRACE - 1,
			'run_at'         => time() - Better_Font_Awesome_Metadata_Manager::SCHEDULE_GRACE - 1,
			'force'          => false,
		);
		add_option( Better_Font_Awesome_Metadata_Manager::SCHEDULE_OPTION, $marker, '', false );

		$manager = new Better_Font_Awesome_Metadata_Manager();
		$this->assertTrue( $manager->schedule_refresh() );
		$this->assertNotSame( $marker['token'], get_option( Better_Font_Awesome_Metadata_Manager::SCHEDULE_OPTION )['token'] );
	}

	/**
	 * Only one worker can hold the fleet-safe lock.
	 */
	public function test_simultaneous_worker_lock_is_suppressed() {
		$manager = new Better_Font_Awesome_Metadata_Manager();
		$owner   = $this->invoke_method( $manager, 'acquire_lock' );

		$this->assertIsString( $owner );
		$this->assertFalse( $this->invoke_method( $manager, 'acquire_lock' ) );
		$this->assertTrue( $this->invoke_method( $manager, 'release_lock', array( $owner ) ) );
	}

	/**
	 * Worker ownership is established before its schedule marker is consumed.
	 */
	public function test_worker_claim_has_no_unowned_scheduler_window() {
		$this->persist_release( '5.15.4', time() - HOUR_IN_SECONDS );
		$plugin     = $this->initialize_plugin();
		$marker     = get_option( Better_Font_Awesome_Metadata_Manager::SCHEDULE_OPTION );
		$concurrent = new Better_Font_Awesome_Metadata_Manager();
		$worker     = new Better_Font_Awesome_Interleaving_Metadata_Manager();
		$worker->set_library( $plugin->get_bfa_lib_instance() );
		$worker->concurrent_manager = $concurrent;
		$this->font_awesome_http_response = $this->successful_response( $this->valid_release( '5.15.5' ) );

		$result = $worker->run_scheduled_refresh( $marker['token'], false );

		$this->assertFalse( $worker->concurrent_schedule_result );
		$this->assertSame( '5.15.5', $result['version'] );
		$this->assertSame( 1, $this->font_awesome_http_calls );
		$this->assertFalse( get_option( Better_Font_Awesome_Metadata_Manager::SCHEDULE_OPTION ) );
		$this->assertFalse( get_option( Better_Font_Awesome_Metadata_Manager::LOCK_OPTION ) );
		$this->assertFalse(
			wp_next_scheduled(
				Better_Font_Awesome_Metadata_Manager::CRON_HOOK,
				array( $marker['token'], false )
			)
		);
	}

	/**
	 * A separate request cannot enqueue while the worker performs HTTP.
	 */
	public function test_concurrent_scheduler_is_suppressed_during_worker_http() {
		$this->persist_release( '5.15.4', time() - HOUR_IN_SECONDS );
		$plugin     = $this->initialize_plugin();
		$worker     = $this->metadata_manager( $plugin );
		$marker     = get_option( Better_Font_Awesome_Metadata_Manager::SCHEDULE_OPTION );
		$concurrent = new Better_Font_Awesome_Metadata_Manager();
		$schedule_result = null;
		$interleave = function ( $preempt, $parsed_args, $url ) use ( $concurrent, &$schedule_result ) {
			unset( $parsed_args );
			if ( Better_Font_Awesome_Library::FONT_AWESOME_API_BASE_URL === $url && null === $schedule_result ) {
				$schedule_result = $concurrent->schedule_refresh();
			}
			return $preempt;
		};
		add_filter( 'pre_http_request', $interleave, 4, 3 );
		$this->font_awesome_http_response = $this->successful_response( $this->valid_release( '5.15.5' ) );

		try {
			$result = $worker->run_scheduled_refresh( $marker['token'], false );
		} finally {
			remove_filter( 'pre_http_request', $interleave, 4 );
		}

		$this->assertFalse( $schedule_result );
		$this->assertSame( '5.15.5', $result['version'] );
		$this->assertSame( 1, $this->font_awesome_http_calls );
		$this->assertFalse( get_option( Better_Font_Awesome_Metadata_Manager::SCHEDULE_OPTION ) );
		$this->assertFalse( get_option( Better_Font_Awesome_Metadata_Manager::LOCK_OPTION ) );
	}

	/**
	 * An expired crashed worker lease is recovered and retried by later traffic.
	 */
	public function test_expired_crashed_worker_recovers_and_eventually_retries() {
		$this->persist_release( '5.15.4', time() - HOUR_IN_SECONDS );
		$plugin  = $this->initialize_plugin();
		$marker  = get_option( Better_Font_Awesome_Metadata_Manager::SCHEDULE_OPTION );
		$crashed = new Better_Font_Awesome_Metadata_Manager();
		$owner   = $this->invoke_method( $crashed, 'acquire_lock', array( $marker['token'] ) );
		$this->assertIsString( $owner );
		$this->assertTrue( $this->invoke_method( $crashed, 'atomic_delete_option', array( Better_Font_Awesome_Metadata_Manager::SCHEDULE_OPTION, $marker ) ) );
		$this->invoke_method( $crashed, 'unschedule_marker', array( $marker ) );

		$concurrent = new Better_Font_Awesome_Metadata_Manager();
		$this->assertFalse( $concurrent->schedule_refresh() );
		$this->assertFalse( get_option( Better_Font_Awesome_Metadata_Manager::SCHEDULE_OPTION ) );

		$expired = get_option( Better_Font_Awesome_Metadata_Manager::LOCK_OPTION );
		$expired['expires_at'] = time() - 1;
		update_option( Better_Font_Awesome_Metadata_Manager::LOCK_OPTION, $expired, false );

		$recovery = new Better_Font_Awesome_Metadata_Manager();
		$recovery->set_library( $plugin->get_bfa_lib_instance() );
		$this->assertTrue( $recovery->schedule_refresh() );
		$retry = get_option( Better_Font_Awesome_Metadata_Manager::SCHEDULE_OPTION );
		$this->font_awesome_http_response = $this->successful_response( $this->valid_release( '5.15.5' ) );
		$result = $recovery->run_scheduled_refresh( $retry['token'], false );

		$this->assertSame( '5.15.5', $result['version'] );
		$this->assertSame( 1, $this->font_awesome_http_calls );
		$this->assertFalse( get_option( Better_Font_Awesome_Metadata_Manager::SCHEDULE_OPTION ) );
		$this->assertFalse( get_option( Better_Font_Awesome_Metadata_Manager::LOCK_OPTION ) );
	}

	/**
	 * An expired worker lock can be recovered.
	 */
	public function test_expired_worker_lock_is_recovered() {
		$expired = array(
			'schema_version' => 1,
			'owner'          => wp_generate_uuid4(),
			'acquired_at'    => time() - Better_Font_Awesome_Metadata_Manager::LOCK_TTL - 10,
			'expires_at'     => time() - 10,
		);
		add_option( Better_Font_Awesome_Metadata_Manager::LOCK_OPTION, $expired, '', false );

		$manager = new Better_Font_Awesome_Metadata_Manager();
		$owner   = $this->invoke_method( $manager, 'acquire_lock' );
		$this->assertIsString( $owner );
		$this->assertNotSame( $expired['owner'], $owner );
	}

	/**
	 * One worker can never release another worker's lock.
	 */
	public function test_lock_ownership_prevents_cross_worker_release() {
		$manager = new Better_Font_Awesome_Metadata_Manager();
		$owner   = $this->invoke_method( $manager, 'acquire_lock' );
		$other   = get_option( Better_Font_Awesome_Metadata_Manager::LOCK_OPTION );
		$other['owner'] = wp_generate_uuid4();
		update_option( Better_Font_Awesome_Metadata_Manager::LOCK_OPTION, $other, false );

		$this->assertFalse( $this->invoke_method( $manager, 'release_lock', array( $owner ) ) );
		$this->assertSame( $other, get_option( Better_Font_Awesome_Metadata_Manager::LOCK_OPTION ) );
	}

	/**
	 * Retry intervals progress to the cap and remain persisted.
	 */
	public function test_exponential_backoff_is_persisted_and_capped() {
		$this->persist_release();
		$plugin  = $this->initialize_plugin();
		$manager = $this->metadata_manager( $plugin );
		$this->font_awesome_http_response = $this->http_response( 500, 'raw secret failure' );
		$expected = array( HOUR_IN_SECONDS, 6 * HOUR_IN_SECONDS, DAY_IN_SECONDS, DAY_IN_SECONDS );

		foreach ( $expected as $index => $delay ) {
			$before = time();
			$result = $this->run_scheduled_worker( $manager );
			$state  = ( new Better_Font_Awesome_Metadata_Store() )->get_state();
			$this->assertWPError( $result );
			$this->assertSame( $index + 1, $state['failure_count'] );
			$this->assertGreaterThanOrEqual( $before + $delay, $state['next_retry_at'] );
			$this->assertLessThanOrEqual( time() + $delay, $state['next_retry_at'] );
			wp_clear_scheduled_hook( Better_Font_Awesome_Metadata_Manager::CRON_HOOK );
			delete_option( Better_Font_Awesome_Metadata_Manager::SCHEDULE_OPTION );
		}
	}

	/**
	 * Jitter filters are clamped to their documented bounds.
	 */
	public function test_jitter_is_bounded() {
		remove_filter( 'better_font_awesome_metadata_jitter', '__return_zero', 10 );
		$maximum_filter = function ( $jitter, $context, $maximum ) {
			unset( $jitter, $context );
			return $maximum + DAY_IN_SECONDS;
		};
		add_filter(
			'better_font_awesome_metadata_jitter',
			$maximum_filter,
			10,
			3
		);

		$this->persist_release();
		$manager = $this->metadata_manager();
		$this->font_awesome_http_response = $this->http_response( 500, 'failure' );
		$before = time();
		$this->run_scheduled_worker( $manager );
		$state = ( new Better_Font_Awesome_Metadata_Store() )->get_state();

		$this->assertGreaterThanOrEqual( $before + HOUR_IN_SECONDS, $state['next_retry_at'] );
		$this->assertLessThanOrEqual( time() + HOUR_IN_SECONDS + Better_Font_Awesome_Metadata_Manager::RETRY_JITTER_MAX, $state['next_retry_at'] );
		remove_filter( 'better_font_awesome_metadata_jitter', $maximum_filter, 10 );
	}

	/**
	 * A successful retry replaces durable data and clears failure state.
	 */
	public function test_successful_retry_replaces_record_and_clears_failure_state() {
		$this->persist_release( '5.15.4' );
		$plugin  = $this->initialize_plugin();
		$manager = $this->metadata_manager( $plugin );
		$this->font_awesome_http_response = $this->http_response( 500, 'failure' );
		$this->assertWPError( $this->run_scheduled_worker( $manager ) );

		$this->font_awesome_http_response = $this->successful_response( $this->valid_release( '5.15.5' ) );
		$this->assertSame( '5.15.5', $this->run_scheduled_worker( $manager )['version'] );
		$store = new Better_Font_Awesome_Metadata_Store();
		$state = $store->get_state();

		$this->assertSame( '5.15.5', $store->get_valid_record()['release']['version'] );
		$this->assertSame( 0, $state['failure_count'] );
		$this->assertSame( 0, $state['next_retry_at'] );
		$this->assertSame( '', $state['last_error'] );
		$this->assertSame( 'fresh', $state['status'] );
	}

	/**
	 * An administrator override advances a backoff event without bypassing lock.
	 */
	public function test_administrator_override_advances_backoff_but_respects_lock() {
		$store                  = new Better_Font_Awesome_Metadata_Store();
		$state                  = $store->get_state();
		$state['next_retry_at'] = time() + DAY_IN_SECONDS;
		$state['status']        = 'failed';
		$store->save_state( $state );
		$manager = $this->metadata_manager();

		$regular = get_option( Better_Font_Awesome_Metadata_Manager::SCHEDULE_OPTION );
		$this->assertIsArray( $regular );
		$this->assertTrue( $manager->schedule_refresh( true ) );
		$manual = get_option( Better_Font_Awesome_Metadata_Manager::SCHEDULE_OPTION );
		$this->assertLessThan( $regular['run_at'], $manual['run_at'] );
		$this->assertTrue( $manual['force'] );

		$active_lock = array(
			'schema_version' => 1,
			'owner'          => wp_generate_uuid4(),
			'acquired_at'    => time(),
			'expires_at'     => time() + Better_Font_Awesome_Metadata_Manager::LOCK_TTL,
		);
		add_option( Better_Font_Awesome_Metadata_Manager::LOCK_OPTION, $active_lock, '', false );
		$result = $manager->run_scheduled_refresh( $manual['token'], true );
		$this->assertWPError( $result );
		$this->assertSame( 'bfa_refresh_locked', $result->get_error_code() );
		$this->assertSame( $active_lock, get_option( Better_Font_Awesome_Metadata_Manager::LOCK_OPTION ) );
		$this->assertSame( $manual, get_option( Better_Font_Awesome_Metadata_Manager::SCHEDULE_OPTION ) );
	}

	/**
	 * Deactivation removes work while preserving data, and activation reschedules.
	 */
	public function test_deactivation_and_reactivation_preserve_data_and_schedule_work() {
		$this->persist_release();
		Better_Font_Awesome_Metadata_Manager::activate( false );
		$this->assertIsArray( get_option( Better_Font_Awesome_Metadata_Manager::SCHEDULE_OPTION ) );

		Better_Font_Awesome_Metadata_Manager::deactivate( false );
		$this->assertFalse( get_option( Better_Font_Awesome_Metadata_Manager::SCHEDULE_OPTION ) );
		$this->assertSame( '5.15.4', ( new Better_Font_Awesome_Metadata_Store() )->get_valid_record()['release']['version'] );

		Better_Font_Awesome_Metadata_Manager::activate( false );
		$this->assertIsArray( get_option( Better_Font_Awesome_Metadata_Manager::SCHEDULE_OPTION ) );
	}

	/**
	 * An overdue event with the marker's exact arguments still owns the work.
	 */
	public function test_overdue_matching_event_is_preserved_by_schedule_refresh() {
		$run_at  = time() - Better_Font_Awesome_Metadata_Manager::SCHEDULE_GRACE - MINUTE_IN_SECONDS;
		$marker  = $this->create_schedule_marker( $run_at, $run_at - MINUTE_IN_SECONDS, false, true );
		$manager = new Better_Font_Awesome_Metadata_Manager();

		$this->assertFalse( $manager->schedule_refresh() );
		$this->assertSame( $marker, get_option( Better_Font_Awesome_Metadata_Manager::SCHEDULE_OPTION ) );
		$this->assertSame(
			$run_at,
			wp_next_scheduled(
				Better_Font_Awesome_Metadata_Manager::CRON_HOOK,
				array( $marker['token'], false )
			)
		);
	}

	/**
	 * Boot before cron dispatch never postpones an overdue owned event.
	 */
	public function test_repeated_boot_does_not_postpone_overdue_matching_event() {
		$this->persist_release( '5.15.4', time() - HOUR_IN_SECONDS );
		$run_at = time() - Better_Font_Awesome_Metadata_Manager::SCHEDULE_GRACE - MINUTE_IN_SECONDS;
		$marker = $this->create_schedule_marker( $run_at, $run_at - MINUTE_IN_SECONDS, false, true );

		$first_request = new Better_Font_Awesome_Metadata_Manager();
		$first_request->boot();
		$this->assertSame( $marker, get_option( Better_Font_Awesome_Metadata_Manager::SCHEDULE_OPTION ) );

		$second_request = new Better_Font_Awesome_Metadata_Manager();
		$second_request->boot();
		$this->assertSame( $marker, get_option( Better_Font_Awesome_Metadata_Manager::SCHEDULE_OPTION ) );
		$this->assertSame(
			$run_at,
			wp_next_scheduled(
				Better_Font_Awesome_Metadata_Manager::CRON_HOOK,
				array( $marker['token'], false )
			)
		);
	}

	/**
	 * A newly claimed marker is protected while its event is not yet visible.
	 */
	public function test_recent_marker_without_visible_event_is_protected_during_grace() {
		$marker  = $this->create_schedule_marker( time() + MINUTE_IN_SECONDS, time(), false, false );
		$manager = new Better_Font_Awesome_Metadata_Manager();

		$this->assertFalse( $manager->schedule_refresh() );
		$this->assertSame( $marker, get_option( Better_Font_Awesome_Metadata_Manager::SCHEDULE_OPTION ) );
		$this->assertFalse(
			wp_next_scheduled(
				Better_Font_Awesome_Metadata_Manager::CRON_HOOK,
				array( $marker['token'], false )
			)
		);
	}

	/**
	 * Malformed ownership never prevents replacement scheduling.
	 */
	public function test_malformed_schedule_marker_is_recovered() {
		$this->assertTrue( add_option( Better_Font_Awesome_Metadata_Manager::SCHEDULE_OPTION, 'malformed', '', false ) );
		$manager = new Better_Font_Awesome_Metadata_Manager();

		$this->assertTrue( $manager->schedule_refresh() );
		$marker = get_option( Better_Font_Awesome_Metadata_Manager::SCHEDULE_OPTION );
		$this->assertIsArray( $marker );
		$this->assertArrayHasKey( 'token', $marker );
		$this->assertSame(
			$marker['run_at'],
			wp_next_scheduled(
				Better_Font_Awesome_Metadata_Manager::CRON_HOOK,
				array( $marker['token'], false )
			)
		);
	}

	/**
	 * A stale marker with no event is recovered during ordinary plugin startup.
	 */
	public function test_missed_schedule_is_recovered_during_boot() {
		$this->persist_release( '5.15.4', time() - HOUR_IN_SECONDS );
		$old = array(
			'schema_version' => 1,
			'token'          => wp_generate_uuid4(),
			'created_at'     => time() - Better_Font_Awesome_Metadata_Manager::SCHEDULE_GRACE - 10,
			'run_at'         => time() - Better_Font_Awesome_Metadata_Manager::SCHEDULE_GRACE - 10,
			'force'          => false,
		);
		add_option( Better_Font_Awesome_Metadata_Manager::SCHEDULE_OPTION, $old, '', false );

		$this->initialize_plugin();
		$new = get_option( Better_Font_Awesome_Metadata_Manager::SCHEDULE_OPTION );
		$this->assertNotSame( $old['token'], $new['token'] );
		$this->assertSame(
			$new['run_at'],
			wp_next_scheduled(
				Better_Font_Awesome_Metadata_Manager::CRON_HOOK,
				array( $new['token'], false )
			)
		);
	}

	/**
	 * Persist a marker and optionally its exact WordPress cron event.
	 *
	 * @param int  $run_at         Event timestamp.
	 * @param int  $created_at     Marker claim timestamp.
	 * @param bool $force          Force argument.
	 * @param bool $schedule_event Whether to persist the matching event.
	 * @return array Schedule marker.
	 */
	private function create_schedule_marker( $run_at, $created_at, $force, $schedule_event ) {
		$marker = array(
			'schema_version' => Better_Font_Awesome_Metadata_Manager::SCHEMA_VERSION,
			'token'          => wp_generate_uuid4(),
			'created_at'     => (int) $created_at,
			'run_at'         => (int) $run_at,
			'force'          => (bool) $force,
		);
		$this->assertTrue( add_option( Better_Font_Awesome_Metadata_Manager::SCHEDULE_OPTION, $marker, '', false ) );

		if ( $schedule_event ) {
			$this->assertTrue(
				wp_schedule_single_event(
					(int) $run_at,
					Better_Font_Awesome_Metadata_Manager::CRON_HOOK,
					array( $marker['token'], (bool) $force ),
					true
				)
			);
		}

		return $marker;
	}

	/**
	 * Run a worker after WordPress persists the scheduler's event.
	 *
	 * @param Better_Font_Awesome_Metadata_Manager $scheduler Scheduling request manager.
	 * @param Better_Font_Awesome_Metadata_Manager $worker    Separate worker manager.
	 * @return array Scheduling result, worker result, and published marker.
	 */
	private function schedule_with_post_persistence_worker( $scheduler, $worker ) {
		$interleaved      = false;
		$worker_result    = null;
		$published_marker = null;
		$interleave = function ( $option ) use ( $worker, &$interleaved, &$worker_result, &$published_marker ) {
			if ( 'cron' !== $option || $interleaved ) {
				return;
			}

			$marker = get_option( Better_Font_Awesome_Metadata_Manager::SCHEDULE_OPTION, array() );
			if ( ! is_array( $marker ) || empty( $marker['token'] ) ) {
				return;
			}

			$interleaved      = true;
			$published_marker = $marker;
			$worker_result    = $worker->run_scheduled_refresh( (string) $marker['token'], (bool) $marker['force'] );
		};
		add_action( 'updated_option', $interleave, 10, 1 );

		try {
			$schedule_result = $scheduler->schedule_refresh();
		} finally {
			remove_action( 'updated_option', $interleave, 10 );
		}

		$this->assertTrue( $interleaved );
		$this->assertIsArray( $published_marker );

		return array(
			'schedule_result'  => $schedule_result,
			'worker_result'    => $worker_result,
			'published_marker' => $published_marker,
		);
	}
}
