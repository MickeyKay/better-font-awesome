<?php

class Better_Font_Awesome_Test extends WP_UnitTestCase {

	protected $bfa;

	public function setUp() {
        $this->bfa = Better_Font_Awesome_Plugin::get_instance( [] );
    }

    public function test_props_that_should_never_change() {

		$props = array(
			'option_name'     => 'better-font-awesome_options',
			'option_defaults' => array(
				'version'            => 'latest',
				'minified'           => 1,
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
}
