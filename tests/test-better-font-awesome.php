<?php

// Support PHP 7+.
// @see  https://stackoverflow.com/questions/42811164/class-phpunit-framework-testcase-not-found
class_alias('\PHPUnit_Framework_TestCase', 'PHPUnit\Framework\TestCase');


class Better_Font_Awesome_Test extends WP_UnitTestCase {

	protected $bfa;

	public function setUp() {
        $this->bfa = Better_Font_Awesome_Plugin::get_instance();
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

  	protected $bfa_lib = $bfa->get_bfal_instance();

  	public function test_bfal_version() {
		$this->assertEquals( '5.14.0', $bfa_lib->get_version() );
  	}
}
