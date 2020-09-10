<?php

class Better_Font_Awesome_Test extends WP_UnitTestCase {

	protected $bfa;
	protected $bfa_lib;

	public function setUp() {
        $this->bfa = Better_Font_Awesome_Plugin::get_instance();
        error_log( print_r('+++++++++++', true) );
        error_log( print_r($this->bfa->bfa_lib, true) );
        error_log( print_r('+++++++++++', true) );
        error_log( print_r($this->bfa->get_bfal_instance, true) );
        error_log( print_r('+++++++++++', true) );
        error_log( print_r($this->bfa->get_bfal_instance(), true) );
        $this->bfa_lib = $this->bfa->get_bfal_instance();
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
		$this->assertEquals( '5.14.0', $this->bfa_lib->get_version() );
  	}
}
