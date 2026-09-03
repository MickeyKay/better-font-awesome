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
	 * Font Awesome release channel owned by the BFAL singleton.
	 *
	 * @var string
	 */
	private $release_channel;

	/**
	 * Register the worker hook.
	 */
	public function __construct() {
		$this->store           = new Better_Font_Awesome_Metadata_Store();
		$this->release_channel = '';
		add_action( self::CRON_HOOK, array( $this, 'handle_cron_refresh' ), 10, 2 );
	}

	/**
	 * Attach the BFAL instance after BFA initializes it.
	 *
	 * @param Better_Font_Awesome_Library $library BFAL instance.
	 */
	public function set_library( $library ) {
		$this->library = $library;
		$channel       = $this->library_release_channel( $library );
		if ( $this->is_supported_channel( $channel ) ) {
			$this->release_channel = $channel;
		}
	}

	/**
	 * Run storage migration and recover missing refresh schedules.
	 */
	public function boot() {
		$this->store->maybe_migrate_transient( self::FRESH_INTERVAL, $this->release_channel );

		$record = $this->store->get_valid_record( $this->release_channel );
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
		$record = $this->store->get_valid_record( $this->release_channel );
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
			$this->set_library( $library );
		}

		if ( ! $this->is_supported_channel( $channel ) || $this->release_channel !== $channel ) {
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
	 * @return bool|WP_Error True when scheduled, false when owned work suppresses a duplicate, or an error when scheduling fails.
	 */
	public function schedule_refresh( $force = false ) {
		$now = time();
		if ( $this->worker_lock_is_active( $now ) ) {
			return false;
		}

		$stored_state = get_option( self::STATE_OPTION, null );
		$state        = $this->store->get_state();
		$run_at       = $force ? $now : max( $now, (int) $state['next_retry_at'] );
		$marker       = $this->new_schedule_marker( $run_at, $force );
		$created      = $this->add_non_autoloaded_option( self::SCHEDULE_OPTION, $marker );

		if ( ! $created ) {
			$existing = get_option( self::SCHEDULE_OPTION, array() );

			if ( $this->schedule_marker_is_stale( $existing, $now ) ) {
				$created = $this->atomic_update_option( self::SCHEDULE_OPTION, $existing, $marker );
				if ( $created ) {
					$this->unschedule_marker( $existing );
				}
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

		/*
		 * A worker may acquire its lock after the first check but before this
		 * marker is inserted. Recheck before enqueueing so a valid worker and a
		 * future HTTP-capable event can never both become eligible.
		 */
		if ( $this->worker_lock_is_active( $now ) ) {
			$this->atomic_delete_option( self::SCHEDULE_OPTION, $marker );
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
			if ( $this->other_refresh_work_is_owned( $marker, $now ) ) {
				return false;
			}

			return new WP_Error(
				'bfa_refresh_schedule_failed',
				__( 'Font Awesome metadata refresh could not be scheduled.', 'better-font-awesome' )
			);
		}

		$state['scheduled_for'] = $run_at;
		if ( 'never' === $state['status'] || $force ) {
			$state['status'] = 'scheduled';
		}

		/*
		 * The persisted event can run before this request resumes. Annotate only
		 * the exact state observed before publication so a completed worker keeps
		 * ownership of its success, failure, and retry state.
		 */
		if ( null === $stored_state ) {
			$this->add_non_autoloaded_option( self::STATE_OPTION, $state );
		} else {
			$this->atomic_update_option( self::STATE_OPTION, $stored_state, $state );
		}

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

		/*
		 * Claim worker ownership while the schedule marker still suppresses
		 * concurrent schedulers. The scheduler's post-claim lock check closes
		 * the inverse interleaving where it read the lock before this insert.
		 */
		$owner = $this->acquire_lock( $token );
		if ( false === $owner ) {
			return new WP_Error( 'bfa_refresh_locked', 'Another Font Awesome metadata refresh is already running.' );
		}

		if ( ! $this->atomic_delete_option( self::SCHEDULE_OPTION, $marker ) ) {
			$this->release_lock( $owner );
			return null;
		}
		$this->unschedule_marker( $marker );

		return $this->run_refresh( (bool) $force, $owner );
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
	 * @param bool   $force Whether retry timing is overridden.
	 * @param string $owner Existing worker ownership token for a consumed event.
	 * @return array|WP_Error Refresh result.
	 */
	public function run_refresh( $force = false, $owner = '' ) {
		$refresh_callback = $this->get_refresh_callback();
		if ( ! $refresh_callback ) {
			if ( '' !== $owner ) {
				$this->release_lock( $owner );
			}
			return new WP_Error( 'bfa_worker_unavailable', 'The Font Awesome metadata worker is unavailable.' );
		}

		$state = $this->store->get_state();
		$now   = time();
		if ( ! $force && (int) $state['next_retry_at'] > $now ) {
			if ( '' !== $owner ) {
				$this->release_lock( $owner );
			}
			$this->schedule_refresh();
			return new WP_Error( 'bfa_refresh_backoff', 'The Font Awesome metadata refresh is waiting for its retry time.' );
		}

		if ( '' === $owner ) {
			$owner = $this->acquire_lock();
			if ( false === $owner ) {
				return new WP_Error( 'bfa_refresh_locked', 'Another Font Awesome metadata refresh is already running.' );
			}
		}
		if ( ! $this->renew_lock( $owner ) ) {
			$this->release_lock( $owner );
			return $this->ownership_lost_error();
		}

		$state['attempt_count']   = (int) $state['attempt_count'] + 1;
		$state['last_attempt_at'] = $now;
		$state['scheduled_for']   = 0;
		$state['status']          = 'refreshing';
		$this->store->save_state( $state );
		$schedule_retry = false;

		try {
			$result = call_user_func( $refresh_callback );
			if ( is_wp_error( $result ) ) {
				if ( ! $this->renew_lock( $owner ) ) {
					return $this->ownership_lost_error();
				}
				$schedule_retry = $this->record_failure( $result );
				return $result;
			}

			$validation = $this->validate_refresh_result( $result );
			if ( empty( $validation['valid'] ) ) {
				$error = $this->validation_error( $validation );
				if ( ! $this->renew_lock( $owner ) ) {
					return $this->ownership_lost_error();
				}
				$schedule_retry = $this->record_failure( $error );
				return $error;
			}

			$fetched_at  = time();
			$fresh_until = $fetched_at + self::FRESH_INTERVAL + $this->jitter( self::FRESH_JITTER_MAX, 'fresh' );
			$record      = $this->store->build_record( $validation['record'], $fetched_at, $fresh_until );
			if ( ! $this->renew_lock( $owner ) ) {
				return $this->ownership_lost_error();
			}
			if ( ! $this->store->persist_record( $record ) ) {
				$error = new WP_Error( 'bfa_durable_write_failed', 'Validated Font Awesome metadata could not be stored durably.' );
				if ( ! $this->renew_lock( $owner ) ) {
					return $this->ownership_lost_error();
				}
				$schedule_retry = $this->record_failure( $error );
				return $error;
			}

			if ( ! $this->renew_lock( $owner ) ) {
				return $this->ownership_lost_error();
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
			if ( $schedule_retry ) {
				$this->schedule_refresh();
			}
		}
	}

	/**
	 * Get the reviewed BFAL refresh callback when the installed version supports it.
	 *
	 * The method name remains dynamic so static analysis can also run against the
	 * supported rollback dependency, BFAL 2.1.0.
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
	 * Read the immutable channel from a BFAL-compatible library object.
	 *
	 * @param mixed $library Library object.
	 * @return string Selected channel, or an empty string when unavailable.
	 */
	private function library_release_channel( $library ) {
		$method = 'get_release_channel';
		if ( ! is_object( $library ) || ! is_callable( array( $library, $method ) ) ) {
			return '';
		}

		$channel = call_user_func( array( $library, $method ) );
		return is_string( $channel ) ? $channel : '';
	}

	/**
	 * Check whether the installed BFAL dependency supports a channel.
	 *
	 * @param mixed $channel Candidate release channel.
	 * @return bool Whether the channel has an available validator.
	 */
	private function is_supported_channel( $channel ) {
		return (
			'5.x' === $channel && class_exists( 'Better_Font_Awesome_Release_Data_Validator' )
		) || (
			'7.x' === $channel && class_exists( 'Better_Font_Awesome_Release_Data_V2_Validator' )
		);
	}

	/**
	 * Validate a worker result for the singleton's selected channel.
	 *
	 * @param mixed $result BFAL worker result.
	 * @return array Validation result.
	 */
	private function validate_refresh_result( $result ) {
		if ( '7.x' === $this->release_channel && class_exists( 'Better_Font_Awesome_Release_Data_V2_Validator' ) ) {
			return Better_Font_Awesome_Release_Data_V2_Validator::validate_record( $result );
		}

		if ( '5.x' === $this->release_channel && class_exists( 'Better_Font_Awesome_Release_Data_Validator' ) ) {
			return Better_Font_Awesome_Release_Data_Validator::validate_release( $result, 'api' );
		}

		return array(
			'valid' => false,
			'error' => array(
				'code'    => 'bfa_channel_unsupported',
				'message' => 'The selected Font Awesome release channel is unsupported.',
			),
		);
	}

	/**
	 * Return sanitized refresh state with current freshness applied.
	 *
	 * @return array Refresh state.
	 */
	public function get_status() {
		$state  = $this->store->get_state();
		$record = $this->store->get_valid_record( $this->release_channel );

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
		if (
			! is_multisite() ||
			(int) get_current_network_id() !== (int) $site->network_id
		) {
			return;
		}

		switch_to_blog( (int) $site->blog_id );
		try {
			$manager = new self();
			$manager->schedule_refresh();
		} finally {
			restore_current_blog();
		}
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
	 * @param string $schedule_token Scheduled worker ownership token.
	 * @return string|false Ownership token, or false when another worker owns it.
	 */
	protected function acquire_lock( $schedule_token = '' ) {
		$now    = time();
		$marker = get_option( self::SCHEDULE_OPTION, array() );
		if ( '' === $schedule_token && ! empty( $marker ) ) {
			return false;
		}
		if (
			'' !== $schedule_token &&
			(
				! is_array( $marker ) ||
				! isset( $marker['token'] ) ||
				! hash_equals( (string) $marker['token'], $schedule_token )
			)
		) {
			return false;
		}

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
			! $this->lock_value_is_active( $existing, $now ) &&
			$this->atomic_delete_option( self::LOCK_OPTION, $existing ) &&
			$this->add_non_autoloaded_option( self::LOCK_OPTION, $lock )
		) {
			return $lock['owner'];
		}

		return false;
	}

	/**
	 * Check for a valid worker lease and recover an expired or malformed lock.
	 *
	 * The lock lifetime is intentionally much longer than BFAL's bounded HTTP
	 * timeout. Once a lease expires, its worker is no longer eligible and an
	 * ordinary request may create replacement work without performing HTTP.
	 *
	 * @param int $now Current Unix timestamp.
	 * @return bool Whether a valid worker currently owns refresh work.
	 */
	private function worker_lock_is_active( $now ) {
		$lock = get_option( self::LOCK_OPTION, array() );
		if ( empty( $lock ) ) {
			return false;
		}

		if ( $this->lock_value_is_active( $lock, $now ) ) {
			return true;
		}

		if ( $this->atomic_delete_option( self::LOCK_OPTION, $lock ) ) {
			return false;
		}

		$current = get_option( self::LOCK_OPTION, array() );
		return $this->lock_value_is_active( $current, $now );
	}

	/**
	 * Check whether another valid marker or worker owns work after a failed enqueue.
	 *
	 * @param array $failed_marker Marker owned by the failed scheduling attempt.
	 * @param int   $now           Current Unix timestamp.
	 * @return bool Whether another request owns eligible refresh work.
	 */
	private function other_refresh_work_is_owned( $failed_marker, $now ) {
		if ( $this->worker_lock_is_active( $now ) ) {
			return true;
		}

		$current = get_option( self::SCHEDULE_OPTION, array() );
		if ( ! is_array( $current ) || empty( $current['token'] ) ) {
			return false;
		}
		if ( isset( $failed_marker['token'] ) && hash_equals( (string) $failed_marker['token'], (string) $current['token'] ) ) {
			return false;
		}

		return ! $this->schedule_marker_is_stale( $current, $now );
	}

	/**
	 * Validate one worker lock value at a point in time.
	 *
	 * @param mixed $lock Lock option value.
	 * @param int   $now  Current Unix timestamp.
	 * @return bool Whether the value represents an active worker lease.
	 */
	private function lock_value_is_active( $lock, $now ) {
		return is_array( $lock ) &&
			! empty( $lock['owner'] ) &&
			isset( $lock['expires_at'] ) &&
			(int) $lock['expires_at'] > $now;
	}

	/**
	 * Atomically renew the exact current worker lease before persisting results.
	 *
	 * Expired owners cannot renew. A compare-and-swap against the complete lock
	 * value prevents a stale worker from displacing a replacement owner.
	 *
	 * @param string $owner Ownership token.
	 * @return bool Whether this worker retained and renewed ownership.
	 *
	 * @phpstan-impure
	 */
	protected function renew_lock( $owner ) {
		$now  = time();
		$lock = get_option( self::LOCK_OPTION, array() );
		if (
			! $this->lock_value_is_active( $lock, $now ) ||
			! isset( $lock['owner'] ) ||
			! hash_equals( (string) $lock['owner'], (string) $owner )
		) {
			return false;
		}

		$renewed               = $lock;
		$renewed['expires_at'] = max( (int) $lock['expires_at'] + 1, $now + self::LOCK_TTL );

		return $this->atomic_update_option( self::LOCK_OPTION, $lock, $renewed );
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
	 * @return bool Whether the complete failure and backoff state was stored.
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
		return $this->store->save_state( $state );
	}

	/**
	 * Return the stable internal result for a worker that lost ownership.
	 *
	 * @return WP_Error Ownership-loss result.
	 */
	private function ownership_lost_error() {
		return new WP_Error(
			'bfa_refresh_ownership_lost',
			'Font Awesome metadata refresh ownership was lost before its result could be stored.'
		);
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
		if ( false !== $next ) {
			return false;
		}

		return ! isset( $marker['created_at'] ) || (int) $marker['created_at'] + self::SCHEDULE_GRACE < $now;
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
				'fields'     => 'ids',
				'network_id' => get_current_network_id(),
				'number'     => 0,
			)
		);

		foreach ( $site_ids as $site_id ) {
			switch_to_blog( (int) $site_id );
			try {
				call_user_func( $callback );
			} finally {
				restore_current_blog();
			}
		}
	}
}
