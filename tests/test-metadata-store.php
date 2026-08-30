<?php
/**
 * Durable metadata storage tests.
 *
 * @package Better_Font_Awesome
 */

require_once __DIR__ . '/MetadataTestCase.php';

/**
 * Validate storage, migration, and integrity behavior.
 */
class Better_Font_Awesome_Metadata_Store_Test extends Better_Font_Awesome_Metadata_Test_Case {

	/**
	 * Durable records and state remain non-autoloaded and versioned.
	 */
	public function test_durable_options_are_versioned_and_non_autoloaded() {
		global $wpdb;

		$record = $this->persist_release();
		$store  = new Better_Font_Awesome_Metadata_Store();
		$state  = $store->get_state();
		$store->save_state( $state );
		$store->maybe_migrate_transient( DAY_IN_SECONDS );

		$this->assertSame( 1, $record['storage_schema_version'] );
		$this->assertSame( 1, get_option( Better_Font_Awesome_Metadata_Store::SCHEMA_OPTION ) );

		foreach (
			array(
				Better_Font_Awesome_Metadata_Store::RECORD_OPTION,
				Better_Font_Awesome_Metadata_Store::STATE_OPTION,
				Better_Font_Awesome_Metadata_Store::SCHEMA_OPTION,
			) as $option_name
		) {
			$autoload = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT autoload FROM {$wpdb->options} WHERE option_name = %s",
					$option_name
				)
			);
			$this->assertNotContains( $autoload, array( 'yes', 'on', 'auto-on' ), $option_name );
		}
	}

	/**
	 * A valid compatibility transient is promoted and preserved.
	 */
	public function test_valid_transient_is_promoted_without_deletion() {
		$release = $this->valid_release( '5.15.3' );
		set_transient( 'bfa-release-data', $release, DAY_IN_SECONDS );

		$store = new Better_Font_Awesome_Metadata_Store();
		$store->maybe_migrate_transient( DAY_IN_SECONDS );
		$record = $store->get_valid_record();

		$this->assertSame( '5.15.3', $record['release']['version'] );
		$this->assertSame( 'transient', $record['source'] );
		$this->assertSame( $release, get_transient( 'bfa-release-data' ) );
		$this->assertSame( 1, get_option( Better_Font_Awesome_Metadata_Store::SCHEMA_OPTION ) );
	}

	/**
	 * A failed durable write leaves migration incomplete and retries safely.
	 */
	public function test_failed_transient_record_write_retries_and_completes_once() {
		global $wpdb;

		$release = $this->valid_release( '5.15.3' );
		set_transient( 'bfa-release-data', $release, DAY_IN_SECONDS );
		$record_write_attempts = 0;
		$fail_record_writes = true;
		$intercept_record_write = function ( $query ) use ( $wpdb, &$record_write_attempts, &$fail_record_writes ) {
			if ( preg_match( '/^(INSERT|UPDATE)/', $query ) && false !== strpos( $query, Better_Font_Awesome_Metadata_Store::RECORD_OPTION ) ) {
				++$record_write_attempts;
				if ( $fail_record_writes ) {
					return "INSERT INTO {$wpdb->options} (invalid_bfa_test_column) VALUES (1)";
				}
			}
			return $query;
		};
		add_filter( 'query', $intercept_record_write );
		$previous_suppress_errors = $wpdb->suppress_errors( true );
		$store = new Better_Font_Awesome_Metadata_Store();

		try {
			$store->maybe_migrate_transient( DAY_IN_SECONDS );

			$this->assertSame( array(), $store->get_valid_record() );
			$this->assertFalse( get_option( Better_Font_Awesome_Metadata_Store::STATE_OPTION ) );
			$this->assertFalse( get_option( Better_Font_Awesome_Metadata_Store::SCHEMA_OPTION ) );
			$this->assertSame( $release, get_transient( 'bfa-release-data' ) );

			$fail_record_writes = false;
			$store->maybe_migrate_transient( DAY_IN_SECONDS );
			$record = $store->get_valid_record();
			$state  = get_option( Better_Font_Awesome_Metadata_Store::STATE_OPTION );
			$this->assertSame( '5.15.3', $record['release']['version'] );
			$this->assertSame( 'transient', $record['source'] );
			$this->assertSame( $record['fetched_at'], $state['fetched_at'] );
			$this->assertSame( 'fresh', $state['status'] );
			$this->assertSame( 1, get_option( Better_Font_Awesome_Metadata_Store::SCHEMA_OPTION ) );
			$this->assertSame( $release, get_transient( 'bfa-release-data' ) );

			$attempts_after_success = $record_write_attempts;
			$state_after_success    = $state;
			$store->maybe_migrate_transient( DAY_IN_SECONDS );
			$this->assertSame( $attempts_after_success, $record_write_attempts );
			$this->assertSame( $state_after_success, get_option( Better_Font_Awesome_Metadata_Store::STATE_OPTION ) );
		} finally {
			$wpdb->suppress_errors( $previous_suppress_errors );
			remove_filter( 'query', $intercept_record_write );
		}
	}

	/**
	 * Required completion-write failures never advance the migration schema.
	 *
	 * @dataProvider migration_completion_option_provider
	 * @param string $failed_option Required option whose first write fails.
	 */
	public function test_required_completion_write_failure_does_not_mark_migration_complete( $failed_option ) {
		global $wpdb;

		$release = $this->valid_release( '5.15.3' );
		set_transient( 'bfa-release-data', $release, DAY_IN_SECONDS );
		$write_attempts = 0;
		$fail_writes = true;
		$intercept_write = function ( $query ) use ( $wpdb, $failed_option, &$write_attempts, &$fail_writes ) {
			if ( preg_match( '/^(INSERT|UPDATE)/', $query ) && false !== strpos( $query, $failed_option ) ) {
				++$write_attempts;
				if ( $fail_writes ) {
					return "INSERT INTO {$wpdb->options} (invalid_bfa_test_column) VALUES (1)";
				}
			}
			return $query;
		};
		add_filter( 'query', $intercept_write );
		$previous_suppress_errors = $wpdb->suppress_errors( true );
		$store = new Better_Font_Awesome_Metadata_Store();

		try {
			$store->maybe_migrate_transient( DAY_IN_SECONDS );
			$this->assertFalse( get_option( Better_Font_Awesome_Metadata_Store::SCHEMA_OPTION ) );
			$this->assertSame( $release, get_transient( 'bfa-release-data' ) );

			$fail_writes = false;
			$store->maybe_migrate_transient( DAY_IN_SECONDS );
			$record = $store->get_valid_record();
			$state  = get_option( Better_Font_Awesome_Metadata_Store::STATE_OPTION );
			$this->assertSame( '5.15.3', $record['release']['version'] );
			$this->assertSame( $record['fetched_at'], $state['fetched_at'] );
			$this->assertSame( 'fresh', $state['status'] );
			$this->assertSame( 1, get_option( Better_Font_Awesome_Metadata_Store::SCHEMA_OPTION ) );
			$this->assertSame( $release, get_transient( 'bfa-release-data' ) );

			$attempts_after_success = $write_attempts;
			$store->maybe_migrate_transient( DAY_IN_SECONDS );
			$this->assertSame( $attempts_after_success, $write_attempts );
		} finally {
			$wpdb->suppress_errors( $previous_suppress_errors );
			remove_filter( 'query', $intercept_write );
		}
	}

	/**
	 * Return required migration-completion options.
	 *
	 * @return array Completion options.
	 */
	public function migration_completion_option_provider() {
		return array(
			'migrated state' => array( Better_Font_Awesome_Metadata_Store::STATE_OPTION ),
			'schema marker'  => array( Better_Font_Awesome_Metadata_Store::SCHEMA_OPTION ),
		);
	}

	/**
	 * A malformed transient is never promoted.
	 */
	public function test_malformed_transient_is_rejected() {
		$transient = array( 'version' => 'latest' );
		set_transient( 'bfa-release-data', $transient, DAY_IN_SECONDS );

		$store = new Better_Font_Awesome_Metadata_Store();
		$store->maybe_migrate_transient( DAY_IN_SECONDS );

		$this->assertSame( array(), $store->get_valid_record() );
		$this->assertSame( $transient, get_transient( 'bfa-release-data' ) );
		$this->assertSame( 1, get_option( Better_Font_Awesome_Metadata_Store::SCHEMA_OPTION ) );
	}

	/**
	 * A valid durable record always wins over compatibility transient data.
	 */
	public function test_migration_never_overwrites_a_newer_durable_record() {
		$this->persist_release( '5.15.4' );
		set_transient( 'bfa-release-data', $this->valid_release( '5.15.3' ), DAY_IN_SECONDS );

		$store = new Better_Font_Awesome_Metadata_Store();
		$store->maybe_migrate_transient( DAY_IN_SECONDS );

		$this->assertSame( '5.15.4', $store->get_valid_record()['release']['version'] );
		$this->assertSame( '5.15.3', get_transient( 'bfa-release-data' )['version'] );
	}

	/**
	 * A modified durable payload fails checksum validation.
	 */
	public function test_tampered_durable_record_is_rejected() {
		$record = $this->persist_release();
		$record['release']['version'] = '5.15.3';
		update_option( Better_Font_Awesome_Metadata_Store::RECORD_OPTION, $record, false );

		$store = new Better_Font_Awesome_Metadata_Store();
		$this->assertSame( array(), $store->get_valid_record() );
	}
}
