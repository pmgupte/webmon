<?php
class WebmonTest extends PHPUnit_Framework_TestCase {
	private $testDir;

	protected function setUp() {
		$this->testDir = dirname(__DIR__);
	}

	public function testNoParam() {
		$output = array();
		exec("php {$this->testDir}/webmon.php", $output, $returnValue);
		$optionsFound = in_array('Options: [-i|-u] [-s] [-t]', $output);

		$this->assertTrue($optionsFound);
	}
}
?>
