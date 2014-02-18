#!/usr/bin/php
<?php
/**
* Webmon - program to monitor web pages for change and detect the change
* Copyright (C) 2013 Prabhas Gupte
* 
* This file is part of Webmon.
* 
* Webmon is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
* 
* Webmon is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
* 
* You should have received a copy of the GNU General Public License
* along with Webmon.  If not, see <http://www.gnu.org/licenses/gpl.txt>
*/

class Webmon {
	const DATA_JSON_FILE = './data.json';
	const FILE_A_SUFFIX = '_a.txt';
	const FILE_B_SUFFIX = '_b.txt';
	const STATUS_NEW = 'New';
	const STATUS_NO_CHANGE = 'No Change';
	const STATUS_CHANGED = 'Changed';
	const CURL_REFERER = 'Webmon Script';

	const NO_SEEDS_EXCEPTION = 'No seeds found in seed file.';
	const NO_CURL_EXCEPTION = 'Need PHP cURL installed and enabled.';

	const VERSION = '1.1.2';

	private $userDefinedInputFile = null;
	private $userDefinedStatusOnly = null;
	private $userDefinedTimeout = 30;
	private $userDefinedUrl = null;

	private $seeds = array();
	private $data = array();

	private $colors = array(
		'foreground' => array(
			'black' => '0;30',
			'darkGrey' => '1;30',
			'blue' => '0;34',
			'lightBlue' => '1;34',
			'green' => '0;32',
			'lightGreen' => '1;32',
			'cyan' => '0;36',
			'lightCyan' => '1;36',
			'red' => '0;31',
			'lightRed' => '1;31',
			'purple' => '0;35',
			'lightPurple' => '1;35',
			'brown' => '0;33',
			'yellow' => '1;33',
			'lightGrey' => '0;37',
			'white' => '1;37'
		),
		'background' => array(
			'black' => '40',
			'red' => '41',
			'green' => '42',
			'yellow' => '43',
			'blue' => '44',
			'magenta' => '45',
			'cyan' => '46',
			'lightGrey' => '47'
		)
	);

	/**
	 * Constructor.
	 * @access public
	 * @param inputFile String full path of input seed file
	 * @param statusOnly Boolean true means only report the status (do not show diff)
	 * @return none
	 */
	public function __construct(Array $userDefinedOptions) {
		echo "Webmon Version ", self::VERSION, "\n";
		$this->preCheck();
		$this->checkForUpdate();

		touch(self::DATA_JSON_FILE);
		$this->data = json_decode(file_get_contents(self::DATA_JSON_FILE), true);

		if (isset($userDefinedOptions['inputFile'])) {
			$this->userDefinedInputFile = $userDefinedOptions['inputFile'];
			$this->seeds = file($this->userDefinedInputFile, FILE_SKIP_EMPTY_LINES && FILE_IGNORE_NEW_LINES);
			$this->seeds = $this->cleanSeeds($this->seeds);
		}

		$this->showDetailedInfo = isset($userDefinedOptions['detailed']) && $userDefinedOptions['detailed'] === true;

		$this->userDefinedStatusOnly = $userDefinedOptions['statusOnly'];
		$this->userDefinedTimeout = $userDefinedOptions['timeout'];
		if (isset($userDefinedOptions['url'])) {
			$this->userDefinedUrl = $userDefinedOptions['url'];
		}

		if ($this->userDefinedUrl !== null) {
			$this->seeds[] = $this->userDefinedUrl;
		}

		if (0 === count($this->seeds)) {
			throw new Exception(self::NO_SEEDS_EXCEPTION);
		}
	}

	/**
	 * checkForUpdate
	 * Checks for availability of newer version
	 * @access private
	 * @param none
	 * @return none
	 */
	private function checkForUpdate() {
		$updateHandle = curl_init('https://github.com/pmgupte/webmon/releases/latest');
		curl_setopt($updateHandle, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($updateHandle, CURLOPT_RETURNTRANSFER, true);
		$updateCheckResponse = curl_exec($updateHandle);
		$updateInfo = curl_getinfo($updateHandle);
		curl_close($updateHandle);
		$updateInfo = pathinfo($updateInfo['url']);

		list($currentMajor, $currentMinor, $currentPatch) = explode('.', self::VERSION);
		list($newMajor, $newMinor, $newPatch) = explode('.', $updateInfo['basename']);
		$isUpperVersion = ($newMajor > $currentMajor || $newMinor > $currentMinor || $newPatch > $currentPatch);

		if ($updateInfo['basename'] !== self::VERSION && $isUpperVersion) {
			echo "New version available! Upgrading from ", self::VERSION, " to ", $updateInfo['basename'], "\n";
			$nextReleaseURL = 'http://github.com/pmgupte/webmon/archive/' . $updateInfo['basename'] . '.tar.gz';
			$homeDir = getcwd();
			chdir('/tmp');
			exec('wget -q ' . $nextReleaseURL, $output, $returnValue);
			exec('tar -xvzf ' . $updateInfo['basename'] . '.tar.gz');
			exec("cp /tmp/webmon-{$updateInfo['basename']}/webmon.php $homeDir/webmon.php");
			exec('rm ' . $updateInfo['basename'] . '.tar.gz');
			exec('rm -rf webmon-' . $updateInfo['basename']);
			chdir($homeDir);

			if (!isset($this->data['meta'])) {
				$this->data['meta'] = array();
			} 
			$this->data['meta']['version'] = $updateInfo['basename'];
			$this->data['meta']['upgradeDate'] = date('Y-m-d H:i:s');

			echo "Webmon Upgraded from version ", self::VERSION, " to ", $updateInfo['basename'], "!\n";
			echo "Exiting now. Please re-run webmon to use new version.";
		} else {
			echo "You are running latest version of Webmon.\n";
		}
	}

	/**
	 * cleanSeeds
	 * removes commented seeds
	 * @access private
	 * @param seeds Array array of seeds read from input file
	 * @return Array array of cleaned seeds
	 */
	private function cleanSeeds($seeds) {
		$cleanSeeds = array();

		foreach ($seeds as $seed) {
			if ($seed[0] !== '#') {
				$cleanSeeds[] = trim($seed);
			}
		}

		return $cleanSeeds;
	}

	/**
	 * run
	 * @access public
	 * @param none
	 * @return none
	 */
	public function run() {
		if ($this->userDefinedInputFile !== null) {
			touch($this->userDefinedInputFile);
		}

		$curlHandle = curl_init();
		curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curlHandle, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($curlHandle, CURLOPT_TIMEOUT, $this->userDefinedTimeout);
		curl_setopt($curlHandle, CURLOPT_FILETIME, true);

		$htmlDom = new DOMDocument;
		libxml_use_internal_errors(true);

		foreach ($this->seeds as $seed) {
			$this->debug("Fetching: $seed", 'black', 'yellow');
			curl_setopt($curlHandle, CURLOPT_URL, $seed);
			curl_setopt($curlHandle, CURLOPT_REFERER, self::CURL_REFERER);
			$httpResponse = curl_exec($curlHandle);

			if (!$httpResponse) {
				$this->debug(curl_error($curlHandle), 'white', 'red');
				continue;
			}

			$info = curl_getinfo($curlHandle);
			$info = $this->removeUnwantedKeys($info);

			$htmlDom->loadHTML($httpResponse);
			$bodyTags = $htmlDom->getElementsByTagName('body');

			foreach ($bodyTags as $bodyTag) {
				$body = $bodyTag->nodeValue;
				$newChecksum = md5($body);

				if (isset($this->data[$seed])) {
					// we have processed this seed at least once before
					if ($newChecksum !== $this->data[$seed]['checksum']) {
						$this->debug(self::STATUS_CHANGED, 'red');
						// web page changed. find the diff
						if (!$this->userDefinedStatusOnly) {
							$this->data[$seed]['contents'] = gzdecode(base64_decode($this->data[$seed]['contents']));

							$filename = '/tmp/' . str_replace(array(':', '/'), '_', $seed);
							file_put_contents($filename . self::FILE_A_SUFFIX, $this->data[$seed]['contents']);
							file_put_contents($filename . self::FILE_B_SUFFIX, $body);

							$this->showDiff($filename . self::FILE_A_SUFFIX, $filename . self::FILE_B_SUFFIX);

							unlink($filename . self::FILE_A_SUFFIX);
							unlink($filename . self::FILE_B_SUFFIX);
						}

						// update the status in data file
						$this->data[$seed]['status'] = self::STATUS_CHANGED;
						$this->data[$seed]['checksum'] = $newChecksum;
						$this->data[$seed]['contents'] = base64_encode(gzencode($body, 9));
					} else {
						// no change. just update status
						$this->debug(self::STATUS_NO_CHANGE, 'green');
						$this->data[$seed]['status'] = self::STATUS_NO_CHANGE;
					}

					$this->showInfoDiff($this->data[$seed]['info'], $info);
				} else {
					// this is first processing of this seed
					$this->debug(self::STATUS_NEW, 'green');
					$this->data[$seed] = array(
						'status' => self::STATUS_NEW,
						'checksum' => $newChecksum,
						'contents' => base64_encode(gzencode($body, 9))
					);
					$this->showInfoDiff(array(), $info);
				} // if-else on isset data[seed]
				$this->data[$seed]['lastChecked'] = microtime();
				$this->data[$seed]['info'] = $info;
			} // foreach on bodyTags
		} // foreach on seeds

		// save updated data
		file_put_contents(self::DATA_JSON_FILE, json_encode($this->data));

		libxml_clear_errors();
		curl_close($curlHandle);
		echo "\n*** Done ***\n";	
	} // run

	/**
	 * removeUnwantedKeys
	 * removes some keys from cUrl info array
	 * @access private
	 * @param Array info - array containing cUrl info
	 * @return Array - array after removal of unwanted keys
	 */
	private function removeUnwantedKeys($info) {
		$unwantedKeys = array('url', 'size_upload', 'speed_upload', 'upload_content_length');

		foreach($unwantedKeys as $key) {
			if (isset($info[$key])) {
				unset($info[$key]);
			}
		}

		return $info;
	}

	/**
	 * showInfoDiff
	 * shows difference between cUrl info found in current and last run
	 * @access private
	 * @param Array oldInfo array of cUrl info found in last run
	 * @param Array newInfo array of cUrl info found in current run
	 * @return none
	 */
	private function showInfoDiff($oldInfo, $newInfo) {
		if ($this->showDetailedInfo) {
			foreach($newInfo as $key => $value) {
				$line = "$key is $value.";
				if (isset($oldInfo[$key]) && $oldInfo[$key] !== null) {
					$line .= " It was {$oldInfo[$key]}";
				}
				$this->debug($line, 'yellow');
			}
		}
	}

	/**
	 * showDiff
	 * @access private
	 * @param oldFile string full path to file containing old contents
	 * @param newFile string full path to file containing new contents
	 * @return none
	 */
	private function showDiff($oldFile, $newFile) {
		$contentsA = file($oldFile);
		$contentsB = file($newFile);

		$negativeDiff = array_diff($contentsA, $contentsB);
		$positiveDiff = array_diff($contentsB, $contentsA);

		$countA = count($contentsA);
		$countB = count($contentsB);
		$counter = ($countA > $countB) ? $countA : $countB;

		echo "+++ positive diff\n--- negative diff\n";
		for ($i=0; $i<$counter; $i++) {
			if (!isset($contentsA[$i])) { $contentsA[$i] = ''; }
			if (!isset($contentsB[$i])) { $contentsB[$i] = ''; }
			$prefix = '  '; // two spaces
			$line = '';

			// new and old line is matching. no line diff.
			if ($contentsA[$i] === $contentsB[$i]) {
				$line = $contentsA[$i];
			} else {
				// if A[i] present in negative diff, print it with '-' prefix
				// if B[i] present in A, print it without prefix
				// else if B[i] present in positive diff, print it with '+' prefix
				if (in_array($contentsA[$i], $negativeDiff)) {
					$prefix = '- ';
					$line = $contentsA[$i];
				}
				if (in_array($contentsB[$i], $contentsA)) {
					$line = $contentsB[$i];
				} else if (in_array($contentsB[$i], $positiveDiff)) {
					$prefix = '+ ';
					$line = $contentsB[$i];
				}
			}

			echo $prefix, $line;
		}
	} // showDiff

	/**
	 * debug
	 * @access public
	 * @param message string message to print
	 * @return none
	 */
	public function debug($message, $fgColor = null, $bgColor = null) {
		$timestamp = date('Y-m-d H:i:s');
		$coloredString = "";

		if (isset($this->colors['foreground'][$fgColor])) {
			$coloredString .= "\033[" . $this->colors['foreground'][$fgColor] . "m";
		}

		if (isset($this->colors['background'][$bgColor])) {
			$coloredString .= "\033[" . $this->colors['background'][$bgColor] . "m";
		}

		if (!is_string($message)) {
			$message = var_export($message, true);
		}

		$coloredString .= "[$timestamp]: $message\033[m\n";

		echo $coloredString;
	} // debug

	/**
	 * preCheck
	 * @access private
	 * @param none
	 * @return none
	 */
	private function preCheck() {
		if (!function_exists('curl_init')) {
			throw new Exception(self::NO_CURL_EXCEPTION);
		}
	} // precheck

	/**
	 * help
	 * @access public
	 * @param none
	 * @return none
	 */
	public static function help() {
		$myName = __FILE__;

		echo "Syntax: $myName <options>\n";
		echo "Options: [-i|-u] [-s] [-t]\n";
		echo "-i, --inputfile\tInput file containing list of web pages to check. One URL per line.\n";
		echo "-u, --url\tURL to check.\n";
		echo "-s, --statusonly\tReport only status, do not show diff\n";
		echo "-t, --timeout\tTimeout period in seconds\n";
		echo "-d, --detailed\tDetailed information\n";
		echo "-h, --help\tShows this help text\n";
	}
} // End of Webmon class

/*
 * Actual script execution starts here.
 */

$longopts = array(
	"inputfile:",
	"url:",
	"statusonly",
	"timeout::",
	"detailed::",
	"help"
	);
$options = getopt("i:u:sd::t::h", $longopts);

if (!is_array($options)) {
	echo "There was some error reading options.\n";
	Webmon::help();
}

$optionKeys = array_keys($options);
if (	!in_array('i', $optionKeys) && 
	!in_array('inputfile', $optionKeys) &&
	!in_array('u', $optionKeys) &&
	!in_array('url', $optionKeys)) {
	echo "Please use either --inputfile or --url option. One of them is required.\n";
	Webmon::help();
	exit(1);
} else if (	(in_array('i', $optionKeys) || in_array('inputfile', $optionKeys)) &&
		(in_array('u', $optionKeys) || in_array('url', $optionKeys))) {
	echo "Option --url will be added to list from option --inputfile.\n";
}

// default values
$userDefinedOptions = array();
$userDefinedOptions['statusOnly'] = false;
$userDefinedOptions['timeout'] = 30;

foreach ($options as $option => $value) {
	switch($option) {
		case 'i':
		case 'inputfile':
			$userDefinedOptions['inputFile'] = $value;
			break;
		case 'u':
		case 'url':
			$userDefinedOptions['url'] = $value;
			break;
		case 's':
		case 'statusonly':
			$userDefinedOptions['statusOnly'] = true;
			break;
		case 'd':
		case 'detailed':
			$userDefinedOptions['detailed'] = true;
			break;
		case 't':
		case 'timeout':
			$userDefinedOptions['timeout'] = $value;
			break;
		case 'h':
		case 'help':
		default:
			Webmon::help();
			break;
	}
}

$webmon = new Webmon($userDefinedOptions);
$webmon->run();
?>
