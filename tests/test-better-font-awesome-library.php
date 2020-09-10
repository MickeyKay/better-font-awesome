<?php
/**
 * BFA Library tests live here for now until we can rig up testing in that repo.
 */
class Better_Font_Awesome_Library_Test extends WP_UnitTestCase {

	protected $bfa_lib;

	public function setUp() {
        $this->bfa_lib = Better_Font_Awesome_Library::get_instance();
    }

    public function test_get_version() {
		$this->assertEquals( '5.14.0', $this->bfa_lib->get_version() );
  	}
}
