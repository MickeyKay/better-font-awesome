<?php
/**
 * Stable BFAL rollback lifecycle tests.
 *
 * @package Better_Font_Awesome
 */

/**
 * Verify that rollback compatibility does not create asynchronous no-op work.
 */
class Better_Font_Awesome_BFAL_Rollback_Test extends WP_UnitTestCase {

	/**
	 * BFAL 2.0.3 activation and startup do not schedule unsupported work.
	 */
	public function test_stable_bfal_activation_does_not_schedule_async_metadata_work() {
		if ( 'rollback' !== getenv( 'BFA_BFAL_VALIDATION_MODE' ) ) {
			$this->markTestSkipped( 'Runs only in the explicit BFAL 2.0.3 rollback suite.' );
		}

		wp_clear_scheduled_hook( Better_Font_Awesome_Metadata_Manager::CRON_HOOK );
		delete_option( Better_Font_Awesome_Metadata_Manager::SCHEDULE_OPTION );
		delete_option( Better_Font_Awesome_Metadata_Manager::LOCK_OPTION );

		Better_Font_Awesome_Plugin::activate( false );

		$this->assertFalse( class_exists( 'Better_Font_Awesome_Release_Data_Validator' ) );
		$this->assertFalse( method_exists( 'Better_Font_Awesome_Library', 'refresh_release_data' ) );
		$this->assertFalse( get_option( Better_Font_Awesome_Metadata_Manager::SCHEDULE_OPTION ) );
		$this->assertFalse( get_option( Better_Font_Awesome_Metadata_Manager::LOCK_OPTION ) );
		$this->assertFalse( wp_next_scheduled( Better_Font_Awesome_Metadata_Manager::CRON_HOOK ) );
		$this->assertFalse( has_action( 'wp_initialize_site', array( 'Better_Font_Awesome_Metadata_Manager', 'initialize_site' ) ) );
	}
}
