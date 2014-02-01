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

	public function testNoInputOption() {
		$output = array();
		exec("php {$this->testDir}/webmon.php -s -t60", $output, $returnValue);
		$messageFound = in_array('Please use either --inputfile or --url option. One of them is required.', $output);

		$this->assertTrue($messageFound);
	}
}
?>
