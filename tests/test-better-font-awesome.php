<?php

class Better_Font_Awesome_Test extends WP_UnitTestCase {

	protected $bfa;
	protected $bfa_lib;

	public function setUp() {
        $this->bfa = Better_Font_Awesome_Plugin::get_instance( [] );
        $this->bfa_lib = $this->bfa->get_bfa_lib_instance( [] );
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

  	public function test_bfal_exists() {
		$this->assertTrue( $this->bfa->bfal_exists() );
  	}

  	/**
  	 * BFA Library Tests
  	 *
  	 * Including here for now until we get BFAL up and running with local tests.
  	 */

  	public function test_bfal_version() {
		$this->assertEquals( '5.15.2', $this->bfa_lib->get_version() );
  	}

  	public function test_get_stylesheet_url() {
  		$this->assertEquals( 'https://use.fontawesome.com/releases/v5.15.2/css/all.css', $this->bfa_lib->get_stylesheet_url() );
  	}

  	public function test_get_stylesheet_url_v4_shim() {
  		$this->assertEquals( 'https://use.fontawesome.com/releases/v5.15.2/css/v4-shims.css', $this->bfa_lib->get_stylesheet_url_v4_shim() );
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
  			$this->assertInternalType( 'string', $asset['path'] );
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
  			]
  		];

  		foreach ( $shortcodes as $shortcode ) {
  			$this->assertEquals( $this->bfa_lib->render_shortcode( $shortcode['atts'] ), $shortcode['output'] );
  		}
  	}

  	public function test_get_transient_expiration() {
  		$this->assertEquals( $this->bfa_lib->get_transient_expiration(), DAY_IN_SECONDS );
  	}

}
