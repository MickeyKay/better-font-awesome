<?php
/**
 * Metadata multisite policy tests.
 *
 * @package Better_Font_Awesome
 */

require_once __DIR__ . '/MetadataTestCase.php';

/**
 * Verify site-scoped storage and network lifecycle behavior.
 */
class Better_Font_Awesome_Metadata_Multisite_Test extends Better_Font_Awesome_Metadata_Test_Case {

	/**
	 * Each site owns its options and events, while network lifecycle covers all sites.
	 */
	public function test_network_lifecycle_uses_site_scoped_data_and_schedules() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires the WordPress multisite test configuration.' );
		}

		$first_site = get_current_blog_id();
		$this->persist_release( '5.15.4' );
		$second_site = self::factory()->blog->create();
		switch_to_blog( $second_site );
		$this->persist_release( '5.15.3' );
		restore_current_blog();

		Better_Font_Awesome_Metadata_Manager::activate( true );
		$this->assertIsArray( get_option( Better_Font_Awesome_Metadata_Manager::SCHEDULE_OPTION ) );
		switch_to_blog( $second_site );
		$this->assertIsArray( get_option( Better_Font_Awesome_Metadata_Manager::SCHEDULE_OPTION ) );
		restore_current_blog();

		Better_Font_Awesome_Metadata_Manager::deactivate( true );
		$this->assertFalse( get_option( Better_Font_Awesome_Metadata_Manager::SCHEDULE_OPTION ) );
		$this->assertSame( '5.15.4', ( new Better_Font_Awesome_Metadata_Store() )->get_valid_record()['release']['version'] );
		switch_to_blog( $second_site );
		$this->assertFalse( get_option( Better_Font_Awesome_Metadata_Manager::SCHEDULE_OPTION ) );
		$this->assertSame( '5.15.3', ( new Better_Font_Awesome_Metadata_Store() )->get_valid_record()['release']['version'] );
		restore_current_blog();
		$this->assertSame( $first_site, get_current_blog_id() );
	}

	/**
	 * Newly initialized sites receive their own refresh event.
	 */
	public function test_new_multisite_site_is_scheduled() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires the WordPress multisite test configuration.' );
		}

		$site_id = self::factory()->blog->create();
		$site    = get_site( $site_id );
		Better_Font_Awesome_Metadata_Manager::initialize_site( $site );
		switch_to_blog( $site_id );
		$this->assertIsArray( get_option( Better_Font_Awesome_Metadata_Manager::SCHEDULE_OPTION ) );
		restore_current_blog();
	}
}
