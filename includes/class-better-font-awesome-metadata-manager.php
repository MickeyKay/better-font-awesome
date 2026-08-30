<?php
/**
 * Durable Font Awesome metadata orchestration.
 *
 * @package Better_Font_Awesome
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-better-font-awesome-metadata-store.php';

/**
 * Own durable release data, background refreshes, and retry state for BFAL.
 */
class Better_Font_Awesome_Metadata_Manager {

	/** Durable release record option. */
	const RECORD_OPTION = Better_Font_Awesome_Metadata_Store::RECORD_OPTION;

	/** Refresh status option. */
	const STATE_OPTION = Better_Font_Awesome_Metadata_Store::STATE_OPTION;

	/** Storage migration version option. */
	const SCHEMA_OPTION = Better_Font_Awesome_Metadata_Store::SCHEMA_OPTION;

	/** Atomic worker lock option. */
	const LOCK_OPTION = 'better_font_awesome_refresh_lock';

	/** Atomic scheduling marker option. */
	const SCHEDULE_OPTION = 'better_font_awesome_refresh_schedule';

	/** WP-Cron refresh hook. */
	const CRON_HOOK = 'better_font_awesome_refresh_release_data';

	/** BFA storage schema version. */
	const SCHEMA_VERSION = Better_Font_Awesome_Metadata_Store::SCHEMA_VERSION;

	/** Target freshness interval. */
	const FRESH_INTERVAL = DAY_IN_SECONDS;

	/** Schedule before a record expires. */
	const REFRESH_LEAD_TIME = HOUR_IN_SECONDS;

	/** Maximum freshness jitter. */
	const FRESH_JITTER_MAX = HOUR_IN_SECONDS;

	/** Maximum retry jitter. */
	const RETRY_JITTER_MAX = 15 * MINUTE_IN_SECONDS;

	/** Maximum retry delay. */
	const RETRY_CAP = DAY_IN_SECONDS;

	/** Worker lock lifetime. */
	const LOCK_TTL = 10 * MINUTE_IN_SECONDS;

	/** Scheduling marker recovery window. */
	const SCHEDULE_GRACE = 10 * MINUTE_IN_SECONDS;

	/**
	 * BFAL instance. Set only after BFA makes the first singleton call.
	 *
	 * @var Better_Font_Awesome_Library|null
	 */
	private $library;

	/**
	 * Durable storage collaborator.
	 *
	 * @var Better_Font_Awesome_Metadata_Store
	 */
	private $store;

	/**
	 * Register the worker hook.
	 */
	public function __construct() {
		$this->store = new Better_Font_Awesome_Metadata_Store();
		add_action( self::CRON_HOOK, array( $this, 'handle_cron_refresh' ), 10, 2 );
	}

	/**
	 * Attach the BFAL instance after BFA initializes it.
	 *
	 * @param Better_Font_Awesome_Library $library BFAL instance.
	 */
	public function set_library( $library ) {
		$this->library = $library;
	}

	/**
	 * Run storage migration and recover missing refresh schedules.
	 */
	public function boot() {
		$this->store->maybe_migrate_transient( self::FRESH_INTERVAL );

		$record = $this->store->get_valid_record();
		if ( empty( $record ) || $this->store->record_needs_refresh( $record, self::REFRESH_LEAD_TIME ) ) {
			$this->schedule_refresh();
		}
	}

	/**
	 * Return only validated, already-resolved local state to BFAL.
	 *
	 * This provider never performs HTTP and never mutates storage.
	 *
	 * @return array BFAL-valid record, or an empty array.
	 */
	public function provide_release_data() {
		$record = $this->store->get_valid_record();
		return empty( $record ) ? array() : $record;
	}

	/**
	 * Handle BFAL's asynchronous refresh request.
	 *
	 * @param string                           $channel Supported release channel.
	 * @param Better_Font_Awesome_Library|null $library BFAL instance.
	 */
	public function request_release_data_refresh( $channel = '', $library = null ) {
		if ( $library instanceof Better_Font_Awesome_Library ) {
			$this->library = $library;
		}

		if (
			class_exists( 'Better_Font_Awesome_Release_Data_Validator' ) &&
			Better_Font_Awesome_Release_Data_Validator::RELEASE_CHANNEL !== $channel
		) {
			return;
		}

		$this->schedule_refresh();
	}

	/**
	 * Schedule one asynchronous refresh with duplicate suppression.
	 *
	 * Administrator requests bypass retry timing, but never bypass a running
	 * worker lock. An administrator request may replace a later pending event.
	 *
	 * @param bool $force Whether an administrator requested an immediate retry.
	 * @return bool Whether a new event was scheduled.
	 */
	public function schedule_refresh( $force = false ) {
		$now     = time();
		$state   = $this->store->get_state();
		$run_at  = $force ? $now + 1 : max( $now + 1, (int) $state['next_retry_at'] );
		$marker  = $this->new_schedule_marker( $run_at, $force );
		$created = $this->add_non_autoloaded_option( self::SCHEDULE_OPTION, $marker );

		if ( ! $created ) {
			$existing = get_option( self::SCHEDULE_OPTION, array() );

			if ( $this->schedule_marker_is_stale( $existing, $now ) ) {
				$this->atomic_delete_option( self::SCHEDULE_OPTION, $existing );
				$created = $this->add_non_autoloaded_option( self::SCHEDULE_OPTION, $marker );
			} elseif ( $force && $this->can_replace_schedule( $existing, $run_at ) ) {
				$created = $this->atomic_update_option( self::SCHEDULE_OPTION, $existing, $marker );
				if ( $created ) {
					$this->unschedule_marker( $existing );
				}
			}
		}

		if ( ! $created ) {
			return false;
		}

		$scheduled = wp_schedule_single_event(
			$run_at,
			self::CRON_HOOK,
			array( $marker['token'], $force ),
			true
		);

		if ( true !== $scheduled ) {
			$this->atomic_delete_option( self::SCHEDULE_OPTION, $marker );
			return false;
		}

		$state['scheduled_for'] = $run_at;
		if ( 'never' === $state['status'] || $force ) {
			$state['status'] = 'scheduled';
		}
		$this->store->save_state( $state );

		return true;
	}

	/**
	 * Consume a scheduled event and run the explicit HTTP worker.
	 *
	 * @param string $token Schedule ownership token.
	 * @param bool   $force Whether retry backoff is overridden.
	 * @return array|WP_Error|null Refresh result.
	 */
	public function run_scheduled_refresh( $token = '', $force = false ) {
		if ( '' === $token ) {
			return null;
		}

		$marker = get_option( self::SCHEDULE_OPTION, array() );
		if ( ! is_array( $marker ) || ! isset( $marker['token'] ) || ! hash_equals( (string) $marker['token'], $token ) ) {
			return null;
		}

		if ( ! $this->atomic_delete_option( self::SCHEDULE_OPTION, $marker ) ) {
			return null;
		}

		return $this->run_refresh( (bool) $force );
	}

	/**
	 * WordPress action callback for the explicit refresh worker.
	 *
	 * @param string $token Schedule ownership token.
	 * @param bool   $force Whether retry backoff is overridden.
	 */
	public function handle_cron_refresh( $token = '', $force = false ) {
		$this->run_scheduled_refresh( $token, $force );
	}

	/**
	 * Perform one explicit bounded BFAL refresh attempt.
	 *
	 * @param bool $force Whether retry timing is overridden.
	 * @return array|WP_Error Refresh result.
	 */
	public function run_refresh( $force = false ) {
		$refresh_callback = $this->get_refresh_callback();
		if ( ! $refresh_callback ) {
			return new WP_Error( 'bfa_worker_unavailable', 'The Font Awesome metadata worker is unavailable.' );
		}

		$state = $this->store->get_state();
		$now   = time();
		if ( ! $force && (int) $state['next_retry_at'] > $now ) {
			$this->schedule_refresh();
			return new WP_Error( 'bfa_refresh_backoff', 'The Font Awesome metadata refresh is waiting for its retry time.' );
		}

		$owner = $this->acquire_lock();
		if ( false === $owner ) {
			return new WP_Error( 'bfa_refresh_locked', 'Another Font Awesome metadata refresh is already running.' );
		}

		$state['attempt_count']   = (int) $state['attempt_count'] + 1;
		$state['last_attempt_at'] = $now;
		$state['scheduled_for']   = 0;
		$state['status']          = 'refreshing';
		$this->store->save_state( $state );

		try {
			$result = call_user_func( $refresh_callback );
			if ( is_wp_error( $result ) ) {
				$this->record_failure( $result );
				return $result;
			}

			$validation = Better_Font_Awesome_Release_Data_Validator::validate_release( $result, 'api' );
			if ( empty( $validation['valid'] ) ) {
				$error = $this->validation_error( $validation );
				$this->record_failure( $error );
				return $error;
			}

			$fetched_at  = time();
			$fresh_until = $fetched_at + self::FRESH_INTERVAL + $this->jitter( self::FRESH_JITTER_MAX, 'fresh' );
			$record      = $this->store->build_record( $validation['record'], $fetched_at, $fresh_until );
			if ( ! $this->store->persist_record( $record ) ) {
				$error = new WP_Error( 'bfa_durable_write_failed', 'Validated Font Awesome metadata could not be stored durably.' );
				$this->record_failure( $error );
				return $error;
			}

			$state                    = $this->store->get_state();
			$state['fetched_at']      = $fetched_at;
			$state['failure_count']   = 0;
			$state['next_retry_at']   = 0;
			$state['last_error_code'] = '';
			$state['last_error']      = '';
			$state['status']          = 'fresh';
			$state['scheduled_for']   = 0;
			$this->store->save_state( $state );

			return $result;
		} finally {
			$this->release_lock( $owner );
			if ( 'failed' === $this->store->get_state()['status'] ) {
				$this->schedule_refresh();
			}
		}
	}

	/**
	 * Get the reviewed BFAL refresh callback when the installed version supports it.
	 *
	 * The method name remains dynamic so static analysis can also run against the
	 * supported emergency rollback dependency, BFAL 2.0.3.
	 *
	 * @return callable|false BFAL refresh callback, or false when unavailable.
	 */
	private function get_refresh_callback() {
		if ( ! $this->library ) {
			return false;
		}

		$method   = $this->refresh_method_name();
		$callback = array( $this->library, $method );

		return is_callable( $callback ) ? $callback : false;
	}

	/**
	 * Get the reviewed BFAL refresh method name.
	 *
	 * @return string BFAL method name.
	 */
	private function refresh_method_name(): string {
		return 'refresh_release_data';
	}

	/**
	 * Return sanitized refresh state with current freshness applied.
	 *
	 * @return array Refresh state.
	 */
	public function get_status() {
		$state  = $this->store->get_state();
		$record = $this->store->get_valid_record();

		if ( ! empty( $record ) && $this->store->record_needs_refresh( $record, self::REFRESH_LEAD_TIME ) && 'failed' !== $state['status'] ) {
			$state['status'] = 'stale';
		} elseif ( ! empty( $record ) && 'failed' !== $state['status'] && 'refreshing' !== $state['status'] ) {
			$state['status'] = 'fresh';
		}

		return $state;
	}

	/**
	 * Activate refresh scheduling for one site or every existing network site.
	 *
	 * @param bool $network_wide Whether this is a network activation.
	 */
	public static function activate( $network_wide = false ) {
		self::for_sites(
			$network_wide,
			function () {
				$manager = new self();
				$manager->schedule_refresh();
			}
		);
	}

	/**
	 * Clear pending work while preserving durable data and failure history.
	 *
	 * @param bool $network_wide Whether this is a network deactivation.
	 */
	public static function deactivate( $network_wide = false ) {
		self::for_sites(
			$network_wide,
			function () {
				$manager = new self();
				$manager->clear_scheduled_work();
			}
		);
	}

	/**
	 * Initialize scheduling for a newly created multisite site.
	 *
	 * @param WP_Site $site New site.
	 */
	public static function initialize_site( $site ) {
		if ( ! is_multisite() ) {
			return;
		}

		switch_to_blog( (int) $site->blog_id );
		$manager = new self();
		$manager->schedule_refresh();
		restore_current_blog();
	}

	/**
	 * Clear this site's pending cron and ownership markers.
	 */
	public function clear_scheduled_work() {
		$marker = get_option( self::SCHEDULE_OPTION, array() );
		if ( is_array( $marker ) && ! empty( $marker ) ) {
			$this->unschedule_marker( $marker );
		}

		wp_clear_scheduled_hook( self::CRON_HOOK );
		delete_option( self::SCHEDULE_OPTION );
		delete_option( self::LOCK_OPTION );
	}

	/**
	 * Acquire the fleet-safe worker lock.
	 *
	 * @return string|false Ownership token, or false when another worker owns it.
	 */
	protected function acquire_lock() {
		$now  = time();
		$lock = array(
			'schema_version' => self::SCHEMA_VERSION,
			'owner'          => wp_generate_uuid4(),
			'acquired_at'    => $now,
			'expires_at'     => $now + self::LOCK_TTL,
		);

		if ( $this->add_non_autoloaded_option( self::LOCK_OPTION, $lock ) ) {
			return $lock['owner'];
		}

		$existing = get_option( self::LOCK_OPTION, array() );
		if (
			is_array( $existing ) &&
			isset( $existing['expires_at'] ) &&
			(int) $existing['expires_at'] <= $now &&
			$this->atomic_delete_option( self::LOCK_OPTION, $existing ) &&
			$this->add_non_autoloaded_option( self::LOCK_OPTION, $lock )
		) {
			return $lock['owner'];
		}

		return false;
	}

	/**
	 * Release only the lock owned by this worker.
	 *
	 * @param string $owner Ownership token.
	 * @return bool Whether the owned lock was released.
	 */
	protected function release_lock( $owner ) {
		$lock = get_option( self::LOCK_OPTION, array() );
		if ( ! is_array( $lock ) || ! isset( $lock['owner'] ) || ! hash_equals( (string) $lock['owner'], (string) $owner ) ) {
			return false;
		}

		return $this->atomic_delete_option( self::LOCK_OPTION, $lock );
	}

	/**
	 * Store sanitized failure state and calculate the next retry.
	 *
	 * @param WP_Error $error Sanitized BFAL or BFA error.
	 */
	private function record_failure( $error ) {
		$state                  = $this->store->get_state();
		$state['failure_count'] = (int) $state['failure_count'] + 1;
		$delay                  = $this->retry_delay( $state['failure_count'] );
		$state['next_retry_at'] = time() + $delay;
		$state['status']        = 'failed';
		$state['scheduled_for'] = 0;

		$code = sanitize_key( $error->get_error_code() );
		if ( '' === $code ) {
			$code = 'bfa_refresh_failed';
		}
		$message                  = sanitize_text_field( $error->get_error_message() );
		$state['last_error_code'] = substr( $code, 0, 100 );
		$state['last_error']      = substr( $message, 0, 200 );
		$this->store->save_state( $state );
	}

	/**
	 * Calculate capped exponential retry delay with bounded jitter.
	 *
	 * @param int $failure_count Consecutive failures.
	 * @return int Delay in seconds.
	 */
	private function retry_delay( $failure_count ) {
		$steps = array( HOUR_IN_SECONDS, 6 * HOUR_IN_SECONDS, DAY_IN_SECONDS );
		$index = min( max( 1, (int) $failure_count ) - 1, count( $steps ) - 1 );
		return min( self::RETRY_CAP, $steps[ $index ] + $this->jitter( self::RETRY_JITTER_MAX, 'retry' ) );
	}

	/**
	 * Return bounded filterable jitter for deterministic tests.
	 *
	 * @param int    $maximum Maximum seconds.
	 * @param string $context Jitter context.
	 * @return int Jitter seconds.
	 */
	private function jitter( $maximum, $context ) {
		$jitter = wp_rand( 0, $maximum );
		/**
		 * Filter BFA metadata timing jitter.
		 *
		 * @param int    $jitter  Proposed jitter in seconds.
		 * @param string $context Freshness or retry context.
		 * @param int    $maximum Maximum accepted jitter.
		 */
		$jitter = (int) apply_filters( 'better_font_awesome_metadata_jitter', $jitter, $context, $maximum );
		return max( 0, min( $maximum, $jitter ) );
	}

	/**
	 * Attempt one non-autoloaded option insert.
	 *
	 * @param string $name  Option name.
	 * @param mixed  $value Option value.
	 * @return bool Whether the option was inserted.
	 *
	 * @phpstan-impure
	 */
	private function add_non_autoloaded_option( $name, $value ) {
		return add_option( $name, $value, '', false );
	}

	/**
	 * Convert validator output to a sanitized WP_Error.
	 *
	 * @param array $validation Validator result.
	 * @return WP_Error Sanitized validation error.
	 */
	private function validation_error( $validation ) {
		$error   = isset( $validation['error'] ) && is_array( $validation['error'] ) ? $validation['error'] : array();
		$code    = isset( $error['code'] ) ? sanitize_key( $error['code'] ) : 'bfa_validation_error';
		$message = isset( $error['message'] ) ? sanitize_text_field( $error['message'] ) : 'Font Awesome metadata validation failed.';
		return new WP_Error( $code, $message );
	}

	/**
	 * Build an atomic schedule marker.
	 *
	 * @param int  $run_at Scheduled timestamp.
	 * @param bool $force  Whether this is an administrator override.
	 * @return array Schedule marker.
	 */
	private function new_schedule_marker( $run_at, $force ) {
		return array(
			'schema_version' => self::SCHEMA_VERSION,
			'token'          => wp_generate_uuid4(),
			'created_at'     => time(),
			'run_at'         => (int) $run_at,
			'force'          => (bool) $force,
		);
	}

	/**
	 * Check whether an existing schedule marker can be recovered.
	 *
	 * @param mixed $marker Existing marker.
	 * @param int   $now    Current timestamp.
	 * @return bool Whether the marker is stale.
	 */
	private function schedule_marker_is_stale( $marker, $now ) {
		if ( ! is_array( $marker ) || ! isset( $marker['token'], $marker['run_at'], $marker['force'] ) ) {
			return true;
		}

		$args = array( (string) $marker['token'], (bool) $marker['force'] );
		$next = wp_next_scheduled( self::CRON_HOOK, $args );
		if ( false === $next ) {
			return ! isset( $marker['created_at'] ) || (int) $marker['created_at'] + self::SCHEDULE_GRACE < $now;
		}

		return (int) $marker['run_at'] + self::SCHEDULE_GRACE < $now;
	}

	/**
	 * Check whether an administrator event can replace a later event.
	 *
	 * @param mixed $existing Existing marker.
	 * @param int   $run_at   Proposed timestamp.
	 * @return bool Whether replacement is allowed.
	 */
	private function can_replace_schedule( $existing, $run_at ) {
		return is_array( $existing ) && isset( $existing['run_at'] ) && (int) $existing['run_at'] > $run_at;
	}

	/**
	 * Unschedule the exact event represented by a marker.
	 *
	 * @param array $marker Schedule marker.
	 */
	private function unschedule_marker( $marker ) {
		if ( ! isset( $marker['run_at'], $marker['token'], $marker['force'] ) ) {
			return;
		}

		wp_unschedule_event(
			(int) $marker['run_at'],
			self::CRON_HOOK,
			array( (string) $marker['token'], (bool) $marker['force'] )
		);
	}

	/**
	 * Atomically delete one exact option value.
	 *
	 * @param string $name     Option name.
	 * @param mixed  $expected Expected value.
	 * @return bool Whether the exact value was deleted.
	 */
	private function atomic_delete_option( $name, $expected ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Ownership-safe compare-and-delete requires one atomic SQL statement.
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s",
				$name,
				maybe_serialize( $expected )
			)
		);
		wp_cache_delete( $name, 'options' );
		return 1 === $deleted;
	}

	/**
	 * Atomically replace one exact option value.
	 *
	 * @param string $name     Option name.
	 * @param mixed  $expected Expected value.
	 * @param mixed  $replacement Replacement value.
	 * @return bool Whether the exact value was replaced.
	 */
	private function atomic_update_option( $name, $expected, $replacement ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Ownership-safe compare-and-swap requires one atomic SQL statement.
		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s",
				maybe_serialize( $replacement ),
				$name,
				maybe_serialize( $expected )
			)
		);
		wp_cache_delete( $name, 'options' );
		return 1 === $updated;
	}

	/**
	 * Run a callback in each existing site when network-wide.
	 *
	 * @param bool     $network_wide Whether to iterate the network.
	 * @param callable $callback     Site callback.
	 */
	private static function for_sites( $network_wide, $callback ) {
		if ( ! is_multisite() || ! $network_wide ) {
			call_user_func( $callback );
			return;
		}

		$site_ids = get_sites(
			array(
				'fields' => 'ids',
				'number' => 0,
			)
		);

		foreach ( $site_ids as $site_id ) {
			switch_to_blog( (int) $site_id );
			call_user_func( $callback );
			restore_current_blog();
		}
	}
}
