<?php

// Support PHP 7+.
// @see  https://stackoverflow.com/questions/42811164/class-phpunit-framework-testcase-not-found
class_alias('\PHPUnit_Framework_TestCase', 'PHPUnit\Framework\TestCase');

class Test_Base extends PHPUnit_Framework_TestCase {

	public function testOnePlusOne() {
		$this->assertEquals(1+1,2);
  	}

}
