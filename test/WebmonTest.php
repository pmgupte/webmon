<?php
class WebmonTest extends PHPUnit_Framework_TestCase {
	private $testDir;
	private $webmonCmd;
	private $dataJson = 'data.json';

	protected function setUp() {
		$this->testDir = dirname(__DIR__);
		$this->webmonCmd = "php {$this->testDir}/webmon.php";
	}

	public function testNoParam() {
		$output = array();
		exec($this->webmonCmd, $output, $returnValue);
		$optionsFound = in_array('Options: [-i|-u] [-s] [-t]', $output);

		$this->assertTrue($optionsFound);
	}

	public function testNoInputOption() {
		$output = array();
		exec($this->webmonCmd . " -s -t60", $output, $returnValue);
		$messageFound = in_array('Please use either --inputfile or --url option. One of them is required.', $output);

		$this->assertTrue($messageFound);
	}

	public function testDataJsonCreation() {
		if (file_exists($this->dataJson)) {
			unlink($this->dataJson);
		}

		exec($this->webmonCmd . " -u http://example.com -s -t30", $output, $returnValue);

		$this->assertTrue(file_exists($this->dataJson));
	}

	public function testDataJsonValid() {
		if (file_exists($this->dataJson)) {
			unlink($this->dataJson);
		}

		exec($this->webmonCmd . " -u http://example.com -s -t30", $output, $returnValue);

		$json = file_get_contents($this->dataJson);
		$jsonDecoded = json_decode($json, true);
		$this->assertTrue(is_array($jsonDecoded));
	}

	public function testNonExistingURL() {
		exec($this->webmonCmd . " -t10 -uhttp://some-non-existing-website-url.com", $output, $returnValue);
		$passed = $this->matchesInArray("Couldn't resolve host 'some-non-existing-website-url.com'", $output) || $this->matchesInArray("name lookup timed out", $output);
		$this->assertTrue($passed);
	}

	public function testNonExistingIP() {
		exec($this->webmonCmd . " -t10 -uhttp://999.0.0.0", $output, $returnValue);
		$this->assertTrue($this->matchesInArray("Couldn't resolve host '999.0.0.0'", $output));
	}

	private function matchesInArray($needle, $array) {
		foreach ($array as $value) {
			if (false !== strpos($value, $needle)) {
				return true;
			}
		}

		return false;
	}
}
?>
