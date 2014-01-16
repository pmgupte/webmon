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

	private $userDefinedInputFile = null;
	private $userDefinedStatusOnly = null;
	private $userDefinedVerbose = null;
	private $userDefinedTimeout = 30;

	/**
	 * Constructor.
	 * @access public
	 * @param inputFile String full path of input seed file
	 * @param statusOnly Boolean true means only report the status (do not show diff)
	 * @return none
	 */
	public function __construct(Array $userDefinedOptions) {
		$this->preCheck();

		$this->userDefinedInputFile = $userDefinedOptions['inputFile'];
		$this->userDefinedStatusOnly = $userDefinedOptions['statusOnly'];
		$this->userDefinedTimeout = $userDefinedOptions['timeout'];
	}

	/**
	 * run
	 * @access public
	 * @param none
	 * @return none
	 */
	public function run() {
		touch($this->userDefinedInputFile);
		touch(self::DATA_JSON_FILE);

		$seeds = file($this->userDefinedInputFile, FILE_SKIP_EMPTY_LINES && FILE_IGNORE_NEW_LINES);

		if (0 === count($seeds)) {
			throw new Exception(self::NO_SEEDS_EXCEPTION);
		}

		// Load data from last run
		$data = json_decode(file_get_contents(self::DATA_JSON_FILE), true);

		$curlHandle = curl_init();
		curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curlHandle, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($curlHandle, CURLOPT_TIMEOUT, $this->userDefinedTimeout);

		$htmlDom = new DOMDocument;
		libxml_use_internal_errors(true);

		foreach ($seeds as $seed) {
			$seed = trim($seed);

			if ('#' === $seed[0]) continue; // this seed is commented out. skip it.

			$this->debug("Fetching: $seed");
			curl_setopt($curlHandle, CURLOPT_URL, $seed);
			curl_setopt($curlHandle, CURLOPT_REFERER, self::CURL_REFERER);
			$httpResponse = curl_exec($curlHandle);

			if (!$httpResponse) {
				$this->debug(curl_error($curlHandle));
				continue;
			}

			$htmlDom->loadHTML($httpResponse);
			$bodyTags = $htmlDom->getElementsByTagName('body');

			foreach ($bodyTags as $bodyTag) {
				$body = $bodyTag->nodeValue;
				$newChecksum = md5($body);

				if (isset($data[$seed])) {
					// we have processed this seed at least once before
					if ($newChecksum !== $data[$seed]['checksum']) {
						$this->debug(self::STATUS_CHANGED);
						// web page changed. find the diff
						if (!$this->userDefinedStatusOnly) {
							$data[$seed]['contents'] = base64_decode($data[$seed]['contents']);

							$filename = '/tmp/' . str_replace(array(':', '/'), '_', $seed);
							file_put_contents($filename . self::FILE_A_SUFFIX, $data[$seed]['contents']);
							file_put_contents($filename . self::FILE_B_SUFFIX, $body);

							$this->showDiff($filename . self::FILE_A_SUFFIX, $filename . self::FILE_B_SUFFIX);
						}

						// update the status in data file
						$data[$seed]['status'] = self::STATUS_CHANGED;
						$data[$seed]['checksum'] = $newChecksum;
						$data[$seed]['contents'] = base64_encode($body);
					} else {
						// no change. just update status
						$this->debug(self::STATUS_NO_CHANGE);
						$data[$seed]['status'] = self::STATUS_NO_CHANGE;
					}

					$data[$seed]['lastChecked'] = microtime();
				} else {
					// this is first processing of this seed
					$this->debug(self::STATUS_NEW);
					$data[$seed] = array(
						'status' => $this->STATUS_NEW,
						'checksum' => $newChecksum,
						'contents' => base64_encode($body),
						'lastChecked' => microtime()
					);
				} // if-else on isset data[seed]
			} // foreach on bodyTags
		} // foreach on seeds

		// save updated data
		file_put_contents(self::DATA_JSON_FILE, json_encode($data));

		libxml_clear_errors();
		curl_close($curlHandle);
		echo "\n*** Done ***\n";	
	} // run

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
	public function debug($message) {
		$timestamp = date('Y-m-d H:i:s');

		if (!is_string($message)) {
			$message = var_export($message);
		}
		echo "[$timestamp]: $message\n";
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
		echo "Options: [-i] [-s] [-t]\n";
		echo "-i, --inputfile\tInput file containing list of web pages to check. One URL per line. Defaults to ./seeds\n";
		echo "-s, --statusonly\tReport only status, do not show diff\n";
		echo "-t, --timeout\tTimeout period in seconds\n";
		echo "-h, --help\tShows this help text\n";
	}
}

$longopts = array(
	"inputfile::",
	"statusonly",
	"timeout::",
	"help"
	);
$options = getopt("i:st::h", $longopts);

if (!is_array($options)) {
	echo "There was some error reading options.\n";
	Webmon::help();
}

// default values
$userDefinedOptions = array();
$userDefinedOptions['inputFile'] = './seeds';
$userDefinedOptions['statusOnly'] = false;
$userDefinedOptions['timeout'] = 30;

foreach ($options as $option => $value) {
	switch($option) {
		case 'i':
		case 'inputfile':
			$userDefinedOptions['inputFile'] = $value;
			break;
		case 's':
		case 'statusonly':
			$userDefinedOptions['statusOnly'] = true;
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