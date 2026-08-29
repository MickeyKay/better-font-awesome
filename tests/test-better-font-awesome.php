<?php

class Better_Font_Awesome_WP_Die_Exception extends Exception {

	public $args;

	public function __construct( $message, $args ) {
		parent::__construct( $message );
		$this->args = $args;
	}
}

class Better_Font_Awesome_Test extends WP_UnitTestCase {

	protected $bfa;
	protected $bfa_lib;

	protected $core_font_awesome_version = '5.14.0';

	public function setUp(): void {
		parent::setUp();

		$this->bfa     = Better_Font_Awesome_Plugin::get_instance( [] );
		$this->bfa_lib = $this->bfa->get_bfa_lib_instance( [] );
	}

	public function tearDown(): void {
		remove_filter( 'wp_die_handler', array( $this, 'filter_wp_die_handler' ) );
		$_POST    = array();
		$_REQUEST = array();
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	public function filter_wp_die_handler() {
		return array( $this, 'handle_wp_die' );
	}

	public function handle_wp_die( $message, $title, $args ) {
		throw new Better_Font_Awesome_WP_Die_Exception( $message, $args );
	}

	public function test_props_that_should_never_change() {

		$props = array(
			'option_name'     => 'better-font-awesome_options',
			'option_defaults' => array(
				'include_v4_shim'    => '',
				'remove_existing_fa' => '',
				'hide_admin_notices' => '',
			),
		);

		foreach ( $props as $prop_name => $value ) {
			$this->assertEquals( $value, $this->bfa->get( $prop_name ) );
		}
	}

	public function test_options_are_initialized_with_defaults() {
		$this->assertSame( $this->bfa->get( 'option_defaults' ), $this->bfa->get( 'options' ) );
	}

	public function test_settings_are_sanitized_as_checkboxes() {
		$this->assertSame(
			array(
				'include_v4_shim'    => 1,
				'remove_existing_fa' => 0,
			),
			$this->bfa->sanitize(
				array(
					'include_v4_shim'    => '1',
					'remove_existing_fa' => 'invalid',
				)
			)
		);
	}

	public function test_settings_save_requires_manage_options_capability() {
		$original_options = get_option( $this->bfa->get( 'option_name' ) );
		$user_id          = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		wp_set_current_user( $user_id );
		$_POST = array(
			'bfa_nonce'         => wp_create_nonce( Better_Font_Awesome_Plugin::SLUG . '-options' ),
			'include_v4_shim'   => '1',
			'remove_existing_fa' => '1',
		);
		$_REQUEST = $_POST;
		add_filter( 'wp_die_handler', array( $this, 'filter_wp_die_handler' ) );

		try {
			$this->bfa->save_options();
			$this->fail( 'Expected save_options() to reject a subscriber.' );
		} catch ( Better_Font_Awesome_WP_Die_Exception $exception ) {
			$this->assertSame( 403, $exception->args['response'] );
			$this->assertStringContainsString( 'not allowed', $exception->getMessage() );
		}

		$this->assertSame( $original_options, get_option( $this->bfa->get( 'option_name' ) ) );
	}

	public function test_administrator_can_save_checkbox_settings_with_valid_nonce() {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );

		wp_set_current_user( $user_id );
		$_POST = array(
			'bfa_nonce'         => wp_create_nonce( Better_Font_Awesome_Plugin::SLUG . '-options' ),
			'include_v4_shim'   => '1',
			'remove_existing_fa' => '0',
			'hide_admin_notices' => '1',
		);
		$_REQUEST = $_POST;
		add_filter( 'wp_die_handler', array( $this, 'filter_wp_die_handler' ) );

		ob_start();
		try {
			$this->bfa->save_options();
			$this->fail( 'Expected save_options() to terminate the AJAX request.' );
		} catch ( Better_Font_Awesome_WP_Die_Exception $exception ) {
			$output = ob_get_clean();
			$this->assertSame( '', $exception->getMessage() );
			$this->assertStringContainsString( 'Settings saved.', $output );
		}

		$this->assertSame(
			array(
				'include_v4_shim'    => true,
				'remove_existing_fa' => false,
				'hide_admin_notices' => true,
			),
			get_option( $this->bfa->get( 'option_name' ) )
		);
	}

  	public function test_bfal_exists() {
		$this->assertTrue( $this->bfa->bfal_exists() );
  	}

  	/**
  	 * BFA Library Tests
  	 *
  	 * Including here for now until we get BFAL up and running with local tests.
  	 */

  	public function test_bfal_version() {
		$this->assertEquals( $this->core_font_awesome_version, $this->bfa_lib->get_version() );
  	}

  	public function test_get_stylesheet_url() {
  		$this->assertEquals( 'https://use.fontawesome.com/releases/v' . $this->core_font_awesome_version . '/css/all.css', $this->bfa_lib->get_stylesheet_url() );
  	}

  	public function test_get_stylesheet_url_v4_shim() {
  		$this->assertEquals( 'https://use.fontawesome.com/releases/v' . $this->core_font_awesome_version . '/css/v4-shims.css', $this->bfa_lib->get_stylesheet_url_v4_shim() );
  	}

  	public function test_get_icons() {
  		$expected_icon_keys = [
  			'title',
  			'slug',
  			'style',
  			'base_class',
  			'searchTerms',
  		];

  		$icons = $this->bfa_lib->get_icons();

  		foreach ( $icons as $icon ) {
  			foreach ( $expected_icon_keys as $expected_icon_key ) {
  				$this->assertArrayHasKey( $expected_icon_key, $icon);
  			}
  		}
  	}

  	public function test_get_release_icons() {
  		$expected_icon_keys = [
  			'id',
  			'label',
  			'membership',
  			'styles',
  		];

  		$release_icons = $this->bfa_lib->get_release_icons();

  		foreach ( $release_icons as $release_icon ) {
  			foreach ( $expected_icon_keys as $expected_icon_key ) {
  				$this->assertArrayHasKey( $expected_icon_key, $release_icon);
  			}
  		}
  	}

  	public function test_get_release_assets() {
  		$assets = $this->bfa_lib->get_release_assets();

  		$release_icons = $this->bfa_lib->get_release_icons();

  		foreach ( $assets as $asset ) {
			$this->assertIsString( $asset['path'] );
  			$this->assertNotEmpty( $asset['path'] );
  		}
  	}

  	public function test_get_prefix() {
  		$this->assertEquals( 'fa', $this->bfa_lib->get_prefix() );
  	}

  	public function test_render_shortcode() {
  		$shortcodes = [
  			// Minimal props populated.
  			[
  				'atts' => [
  					'name' => 'bicycle',
  				],
  				'output' => '<i class="fa fa-bicycle " ></i>',
  			],
  			// All props populated.
  			[
  				'atts' => [
  					'name'             => 'ethereum',
  					'style'            => 'brands',
  					'class'            => '2x',
  					'unprefixed_class' => 'my-custom-class',
  				],
  				'output' => '<i class="fab fa-ethereum fa-2x my-custom-class " ></i>',
  			],
  			// Minimal props populated.
  			[
  				'atts' => [
  					'name' => 'bicycle',
  				],
  				'output' => '<i class="fa fa-bicycle " ></i>',
  			],
  			// Properly strip/replace prefixes
  			[
  				'atts' => [
  					'name'  => 'icon-bicycle',
  					'class' => 'icon-rotate fa-2x',
  				],
  				'output' => '<i class="fa fa-bicycle fa-rotate fa-2x " ></i>',
  			],
  			// Properly escapes dangerous input.
  			[
  				'atts' => [
  					'name'  => '"< hack-name',
  					'class' => '"< hack-class',
  					'title' => '"< hack-title',
  				],
  				'output' => '<i class="fa fa-&quot;&lt; hack-name fa-&quot;&lt; fa-hack-class " title="&quot;&lt; hack-title" ></i>',
  			],
  		];

  		foreach ( $shortcodes as $shortcode ) {
  			$this->assertEquals( $this->bfa_lib->render_shortcode( $shortcode['atts'] ), $shortcode['output'] );
  		}
  	}

  	public function test_get_transient_expiration() {
  		$this->assertEquals( $this->bfa_lib->get_transient_expiration(), DAY_IN_SECONDS );
  	}

}
