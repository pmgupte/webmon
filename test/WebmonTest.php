<?php
class WebmonTest extends PHPUnit_Framework_TestCase {
	public function testNoParam() {
		$output = array();
		exec("../webmon.php", $output, $returnValue);
		$optionsFound = in_array('Options: [-i|-u] [-s] [-t]', $output);

		$this->assertTrue($optionsFound);
	}
}
?>
