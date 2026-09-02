<?php
/**
 * Stable BFAL rollback lifecycle tests.
 *
 * @package Better_Font_Awesome
 */

/**
 * Verify focused compatibility with the stable BFAL rollback dependency.
 */
class Better_Font_Awesome_BFAL_Rollback_Test extends WP_UnitTestCase {

	/**
	 * BFAL 2.1.0 keeps the established FA5 channel and asynchronous worker.
	 */
	public function test_stable_bfal_2_1_0_uses_fa5_and_schedules_async_metadata_work() {
		if ( 'rollback' !== getenv( 'BFA_BFAL_VALIDATION_MODE' ) ) {
			$this->markTestSkipped( 'Runs only in the explicit BFAL 2.1.0 rollback suite.' );
		}

		wp_clear_scheduled_hook( Better_Font_Awesome_Metadata_Manager::CRON_HOOK );
		delete_option( Better_Font_Awesome_Metadata_Manager::SCHEDULE_OPTION );
		delete_option( Better_Font_Awesome_Metadata_Manager::LOCK_OPTION );

		Better_Font_Awesome_Plugin::activate( false );

		$plugin  = Better_Font_Awesome_Plugin::get_instance();
		$library = $plugin->get_bfa_lib_instance();
		$marker  = get_option( Better_Font_Awesome_Metadata_Manager::SCHEDULE_OPTION );

		$this->assertSame( '5.x', $library->get_release_channel() );
		$this->assertSame( '5.14.0', $library->get_version() );
		$this->assertTrue( class_exists( 'Better_Font_Awesome_Release_Data_Validator' ) );
		$this->assertTrue( method_exists( 'Better_Font_Awesome_Library', 'refresh_release_data' ) );
		$this->assertIsArray( $marker );
		$this->assertFalse( get_option( Better_Font_Awesome_Metadata_Manager::LOCK_OPTION ) );
		$this->assertNotFalse( wp_next_scheduled( Better_Font_Awesome_Metadata_Manager::CRON_HOOK, array( $marker['token'], false ) ) );
	}
}
