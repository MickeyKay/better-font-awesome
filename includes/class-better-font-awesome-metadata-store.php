<?php
/**
 * Durable Font Awesome metadata storage and migration.
 *
 * @package Better_Font_Awesome
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Store validated release data and separate refresh state.
 */
class Better_Font_Awesome_Metadata_Store {

	/** Durable release record option. */
	const RECORD_OPTION = 'better_font_awesome_release_record';

	/** Refresh status option. */
	const STATE_OPTION = 'better_font_awesome_release_state';

	/** Storage migration version option. */
	const SCHEMA_OPTION = 'better_font_awesome_metadata_schema';

	/** BFA storage schema version. */
	const SCHEMA_VERSION = 1;

	/**
	 * Migrate the established BFAL transient once without replacing valid data.
	 *
	 * @param int $fresh_interval Normal freshness interval in seconds.
	 */
	public function maybe_migrate_transient( $fresh_interval ) {
		if ( (int) get_option( self::SCHEMA_OPTION, 0 ) >= self::SCHEMA_VERSION ) {
			return;
		}

		$now       = time();
		$durable   = $this->get_valid_record();
		$transient = get_transient( 'bfa-release-data' );

		if ( empty( $durable ) && false !== $transient && class_exists( 'Better_Font_Awesome_Release_Data_Validator' ) ) {
			$validation = Better_Font_Awesome_Release_Data_Validator::validate_release( $transient, 'transient' );
			if ( ! empty( $validation['valid'] ) ) {
				$timeout     = wp_using_ext_object_cache() ? 0 : (int) get_option( '_transient_timeout_bfa-release-data', 0 );
				$fresh_until = $now < $timeout ? $timeout : $now;
				$fetched_at  = max( 1, $fresh_until - $fresh_interval );
				$record      = $this->build_record( $validation['record'], $fetched_at, $fresh_until );
				if ( ! $this->persist_record( $record ) ) {
					return;
				}
				$durable = $record;
			}
		}

		if ( ! empty( $durable ) && isset( $durable['source'] ) && 'transient' === $durable['source'] ) {
			$state               = $this->get_state();
			$state['fetched_at'] = (int) $durable['fetched_at'];
			$state['status']     = (int) $durable['fresh_until'] <= $now ? 'stale' : 'fresh';
			if ( ! $this->save_state( $state ) ) {
				return;
			}
		}

		$this->save_non_autoloaded_option( self::SCHEMA_OPTION, self::SCHEMA_VERSION );
	}

	/**
	 * Return the durable record after BFAL validation and checksum verification.
	 *
	 * @return array Valid record, or an empty array.
	 */
	public function get_valid_record() {
		if ( ! class_exists( 'Better_Font_Awesome_Release_Data_Validator' ) ) {
			return array();
		}

		$record = get_option( self::RECORD_OPTION, array() );
		if (
			! is_array( $record ) ||
			! isset( $record['storage_schema_version'], $record['fetched_at'], $record['fresh_until'], $record['checksum'] ) ||
			self::SCHEMA_VERSION !== (int) $record['storage_schema_version'] ||
			! is_int( $record['fetched_at'] ) ||
			! is_int( $record['fresh_until'] ) ||
			! is_string( $record['checksum'] )
		) {
			return array();
		}

		$validation = Better_Font_Awesome_Release_Data_Validator::validate_record( $record );
		if ( empty( $validation['valid'] ) ) {
			return array();
		}

		if ( ! hash_equals( $this->release_checksum( $record['release'] ), $record['checksum'] ) ) {
			return array();
		}

		return $record;
	}

	/**
	 * Build a durable BFAL-valid record with BFA metadata.
	 *
	 * @param array $record      BFAL record.
	 * @param int   $fetched_at  Fetch time.
	 * @param int   $fresh_until Freshness deadline.
	 * @return array Durable record.
	 */
	public function build_record( $record, $fetched_at, $fresh_until ) {
		$record['storage_schema_version'] = self::SCHEMA_VERSION;
		$record['fetched_at']             = (int) $fetched_at;
		$record['fresh_until']            = (int) $fresh_until;
		$record['checksum']               = $this->release_checksum( $record['release'] );
		return $record;
	}

	/**
	 * Persist a complete record with autoload disabled.
	 *
	 * @param array $record Durable record.
	 * @return bool Whether the complete record is now stored.
	 */
	public function persist_record( $record ) {
		$this->save_non_autoloaded_option( self::RECORD_OPTION, $record );
		return get_option( self::RECORD_OPTION, array() ) === $record;
	}

	/**
	 * Test whether a record is stale or within a refresh lead window.
	 *
	 * @param array $record    Durable record.
	 * @param int   $lead_time Refresh lead time in seconds.
	 * @return bool Whether a refresh should be scheduled.
	 */
	public function record_needs_refresh( $record, $lead_time ) {
		return ! isset( $record['fresh_until'] ) || (int) $record['fresh_until'] <= time() + $lead_time;
	}

	/**
	 * Return state with stable defaults.
	 *
	 * @return array Refresh state.
	 */
	public function get_state() {
		$defaults = array(
			'schema_version'  => self::SCHEMA_VERSION,
			'fetched_at'      => 0,
			'attempt_count'   => 0,
			'last_attempt_at' => 0,
			'failure_count'   => 0,
			'next_retry_at'   => 0,
			'scheduled_for'   => 0,
			'status'          => 'never',
			'last_error_code' => '',
			'last_error'      => '',
		);
		$state    = get_option( self::STATE_OPTION, array() );
		return wp_parse_args( is_array( $state ) ? $state : array(), $defaults );
	}

	/**
	 * Persist refresh state with autoload disabled.
	 *
	 * @param array $state Refresh state.
	 * @return bool Whether the complete state is now stored.
	 */
	public function save_state( $state ) {
		$state['schema_version'] = self::SCHEMA_VERSION;
		return $this->save_non_autoloaded_option( self::STATE_OPTION, $state );
	}

	/**
	 * Add or update a non-autoloaded option.
	 *
	 * @param string $name  Option name.
	 * @param mixed  $value Option value.
	 * @return bool Whether the complete value is now stored.
	 */
	private function save_non_autoloaded_option( $name, $value ) {
		if ( ! add_option( $name, $value, '', false ) ) {
			update_option( $name, $value, false );
		}

		return get_option( $name, null ) === $value;
	}

	/**
	 * Calculate the durable release checksum.
	 *
	 * @param array $release Validated release data.
	 * @return string SHA-256 checksum.
	 */
	private function release_checksum( $release ) {
		return hash( 'sha256', maybe_serialize( $release ) );
	}
}
