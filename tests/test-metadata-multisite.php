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

	/** @var array Original network-active plugins. */
	private $original_network_plugins = array();

	/**
	 * Preserve network activation and clear lifecycle callbacks.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->original_network_plugins = (array) get_site_option( 'active_sitewide_plugins', array() );
		$this->remove_new_site_callbacks();
	}

	/**
	 * Restore network activation and lifecycle callbacks.
	 */
	public function tearDown(): void {
		$this->remove_new_site_callbacks();
		update_site_option( 'active_sitewide_plugins', $this->original_network_plugins );
		parent::tearDown();
	}

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
	 * Site-only activation neither registers nor executes network site setup.
	 */
	public function test_site_only_activation_does_not_schedule_new_inactive_site() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires the WordPress multisite test configuration.' );
		}

		$original_blog = get_current_blog_id();
		$this->set_plugin_network_active( false );
		$this->initialize_plugin();
		$this->assertFalse( has_action( 'wp_initialize_site', array( 'Better_Font_Awesome_Metadata_Manager', 'initialize_site' ) ) );
		$this->assertFalse( has_action( 'wp_initialize_site', array( 'Better_Font_Awesome_Plugin', 'initialize_site_metadata' ) ) );

		$site_id = self::factory()->blog->create( array( 'site_id' => get_current_network_id() ) );
		$this->assertSame( $original_blog, get_current_blog_id() );
		$this->assertSame( $this->empty_site_metadata_state(), $this->site_metadata_state( $site_id ) );
	}

	/**
	 * Site-specific activation schedules only its explicitly activated site.
	 */
	public function test_site_specific_activation_schedules_current_site() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires the WordPress multisite test configuration.' );
		}

		$this->set_plugin_network_active( false );
		( new Better_Font_Awesome_Metadata_Manager() )->clear_scheduled_work();
		Better_Font_Awesome_Plugin::activate( false );

		$state = $this->site_metadata_state( get_current_blog_id() );
		$this->assertIsArray( $state['marker'] );
		$this->assertTrue( $state['event'] );
	}

	/**
	 * Network activation schedules new sites in its network and restores context.
	 */
	public function test_network_activation_schedules_new_site_and_restores_context() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires the WordPress multisite test configuration.' );
		}

		$original_blog = get_current_blog_id();
		$this->set_plugin_network_active( true );
		$this->initialize_plugin();
		$this->assertNotFalse( has_action( 'wp_initialize_site', array( 'Better_Font_Awesome_Plugin', 'initialize_site_metadata' ) ) );

		$site_id = self::factory()->blog->create( array( 'site_id' => get_current_network_id() ) );
		$state   = $this->site_metadata_state( $site_id );
		$this->assertIsArray( $state['marker'] );
		$this->assertTrue( $state['event'] );
		$this->assertSame( $original_blog, get_current_blog_id() );
	}

	/**
	 * A registered network callback rechecks activation before touching a site.
	 */
	public function test_new_site_callback_rechecks_network_activation() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires the WordPress multisite test configuration.' );
		}

		$original_blog = get_current_blog_id();
		$this->set_plugin_network_active( true );
		$this->initialize_plugin();
		$this->assertNotFalse( has_action( 'wp_initialize_site', array( 'Better_Font_Awesome_Plugin', 'initialize_site_metadata' ) ) );

		$this->set_plugin_network_active( false );
		$site_id = self::factory()->blog->create( array( 'site_id' => get_current_network_id() ) );
		$this->assertSame( $this->empty_site_metadata_state(), $this->site_metadata_state( $site_id ) );
		$this->assertSame( $original_blog, get_current_blog_id() );
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
		$this->set_plugin_network_active( true );
		$this->initialize_plugin();
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
	 * Set the current network's canonical plugin activation state.
	 *
	 * @param bool $active Whether BFA is network active.
	 */
	private function set_plugin_network_active( $active ) {
		$plugins = (array) get_site_option( 'active_sitewide_plugins', array() );
		$plugin  = plugin_basename( dirname( __DIR__ ) . '/better-font-awesome.php' );
		if ( $active ) {
			$plugins[ $plugin ] = time();
		} else {
			unset( $plugins[ $plugin ] );
		}
		update_site_option( 'active_sitewide_plugins', $plugins );
	}

	/**
	 * Remove callbacks that can survive singleton resets in one PHP process.
	 */
	private function remove_new_site_callbacks() {
		remove_action( 'wp_initialize_site', array( 'Better_Font_Awesome_Metadata_Manager', 'initialize_site' ) );
		remove_action( 'wp_initialize_site', array( 'Better_Font_Awesome_Plugin', 'initialize_site_metadata' ) );
	}

	/**
	 * Return the expected untouched metadata state for an inactive site.
	 *
	 * @return array Empty state.
	 */
	private function empty_site_metadata_state() {
		return array(
			'marker' => false,
			'event'  => false,
			'lock'   => false,
			'state'  => false,
			'record' => false,
		);
	}

	/**
	 * Return all scheduler and storage state owned by one site.
	 *
	 * @param int $site_id Site ID.
	 * @return array Site metadata state.
	 */
	private function site_metadata_state( $site_id ) {
		return $this->in_site(
			$site_id,
			function () {
				return array(
					'marker' => get_option( Better_Font_Awesome_Metadata_Manager::SCHEDULE_OPTION ),
					'event'  => $this->site_has_refresh_event(),
					'lock'   => get_option( Better_Font_Awesome_Metadata_Manager::LOCK_OPTION ),
					'state'  => get_option( Better_Font_Awesome_Metadata_Manager::STATE_OPTION ),
					'record' => get_option( Better_Font_Awesome_Metadata_Manager::RECORD_OPTION ),
				);
			}
		);
	}

	/**
	 * Check the current site's real cron array for any BFA refresh event.
	 *
	 * @return bool Whether a refresh event exists.
	 */
	private function site_has_refresh_event() {
		$cron = _get_cron_array();
		foreach ( $cron as $hooks ) {
			if ( isset( $hooks[ Better_Font_Awesome_Metadata_Manager::CRON_HOOK ] ) ) {
				return true;
			}
		}

		return false;
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
