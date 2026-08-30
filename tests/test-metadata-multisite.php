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
		$this->in_site(
			$second_site,
			function () {
				$this->persist_release( '5.15.3' );
			}
		);

		Better_Font_Awesome_Metadata_Manager::activate( true );
		$this->assertIsArray( get_option( Better_Font_Awesome_Metadata_Manager::SCHEDULE_OPTION ) );
		$this->assertIsArray(
			$this->in_site(
				$second_site,
				function () {
					return get_option( Better_Font_Awesome_Metadata_Manager::SCHEDULE_OPTION );
				}
			)
		);

		Better_Font_Awesome_Metadata_Manager::deactivate( true );
		$this->assertFalse( get_option( Better_Font_Awesome_Metadata_Manager::SCHEDULE_OPTION ) );
		$this->assertSame( '5.15.4', ( new Better_Font_Awesome_Metadata_Store() )->get_valid_record()['release']['version'] );
		$second_state = $this->in_site(
			$second_site,
			function () {
				return array(
					'marker'  => get_option( Better_Font_Awesome_Metadata_Manager::SCHEDULE_OPTION ),
					'version' => ( new Better_Font_Awesome_Metadata_Store() )->get_valid_record()['release']['version'],
				);
			}
		);
		$this->assertFalse( $second_state['marker'] );
		$this->assertSame( '5.15.3', $second_state['version'] );
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
		$this->assertIsArray(
			$this->in_site(
				$site_id,
				function () {
					return get_option( Better_Font_Awesome_Metadata_Manager::SCHEDULE_OPTION );
				}
			)
		);
	}

	/**
	 * Network lifecycle and new-site handling never cross network boundaries.
	 */
	public function test_two_network_lifecycle_is_scoped_to_current_network() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires the WordPress multisite test configuration.' );
		}

		$original_blog      = get_current_blog_id();
		$current_network    = get_current_network_id();
		$current_network_id = self::factory()->blog->create( array( 'site_id' => $current_network ) );
		$other_network      = self::factory()->network->create();
		$other_network_id   = self::factory()->blog->create( array( 'site_id' => $other_network ) );

		foreach ( array( $original_blog, $current_network_id, $other_network_id ) as $site_id ) {
			$this->in_site(
				$site_id,
				function () {
					( new Better_Font_Awesome_Metadata_Manager() )->clear_scheduled_work();
				}
			);
		}

		Better_Font_Awesome_Metadata_Manager::initialize_site( get_site( $other_network_id ) );
		$this->assertFalse( $this->site_schedule_marker( $other_network_id ) );

		Better_Font_Awesome_Metadata_Manager::activate( true );
		$this->assertIsArray( $this->site_schedule_marker( $original_blog ) );
		$this->assertIsArray( $this->site_schedule_marker( $current_network_id ) );
		$this->assertFalse( $this->site_schedule_marker( $other_network_id ) );

		$other_marker = $this->in_site(
			$other_network_id,
			function () {
				$manager = new Better_Font_Awesome_Metadata_Manager();
				$this->assertTrue( $manager->schedule_refresh() );
				return get_option( Better_Font_Awesome_Metadata_Manager::SCHEDULE_OPTION );
			}
		);

		Better_Font_Awesome_Metadata_Manager::deactivate( true );
		$this->assertFalse( $this->site_schedule_marker( $original_blog ) );
		$this->assertFalse( $this->site_schedule_marker( $current_network_id ) );
		$this->assertSame( $other_marker, $this->site_schedule_marker( $other_network_id ) );
		$this->assertSame( $original_blog, get_current_blog_id() );
	}

	/**
	 * Run a callback in one site and always restore the original blog context.
	 *
	 * @param int      $site_id  Site ID.
	 * @param callable $callback Site callback.
	 * @return mixed Callback result.
	 */
	private function in_site( $site_id, $callback ) {
		switch_to_blog( (int) $site_id );
		try {
			return call_user_func( $callback );
		} finally {
			restore_current_blog();
		}
	}

	/**
	 * Return one site's schedule marker.
	 *
	 * @param int $site_id Site ID.
	 * @return array|false Schedule marker or false.
	 */
	private function site_schedule_marker( $site_id ) {
		return $this->in_site(
			$site_id,
			function () {
				return get_option( Better_Font_Awesome_Metadata_Manager::SCHEDULE_OPTION );
			}
		);
	}
}
