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
	const dataJsonFile = './data.json';
	const debugLogFile = './webmon.log';
	const fileASuffix = '_a.txt';
	const fileBSuffix = '_b.txt';
	const statusNew = 'New';
	const statusNoChange = 'No Change';
	const statusChanged = 'Changed';
	const debugLevelInfo = 'INFO';
	const debugLevelWarning = 'WARNING';
	const debugLevelError = 'ERROR';
	const curlReferer = 'Webmon Script';

	const noSeedsException = 'No seeds found in seed file.';
	const noCurlException = 'Need PHP cURL installed and enabled.';

	private $userDefinedInputFile = null;
	private $userDefinedStatusOnly = null;
	private $userDefinedVerbose = null;
	private $userDefinedTimeout = 30;

	/**
	 * Constructor.
	 * @param inputFile String full path of input seed file
	 * @param statusOnly Boolean true means only report the status (do not show diff)
	 * 
	 */
	public function __construct(Array $userDefinedOptions) {
		$this->preCheck();

		$this->userDefinedInputFile = $userDefinedOptions['inputFile'];
		$this->userDefinedStatusOnly = $userDefinedOptions['statusOnly'];
		$this->userDefinedVerbose = $userDefinedOptions['verbose'];
		$this->userDefinedTimeout = $userDefinedOptions['timeout'];

		$this->showUserOptions($userDefinedOptions);
	}

	private function showUserOptions(Array $userDefinedOptions) {
		foreach ($userDefinedOptions as $key => $value) {
			$this->debug("$key : $value");
		}
	}

	public function run() {
		/*
		 * Touch required files
		 */

		touch($this->userDefinedInputFile);
		touch($this->dataJsonFile);
		touch($this->debugLogFile);

		$seeds = file($this->userDefinedInputFile, FILE_SKIP_EMPTY_LINES && FILE_IGNORE_NEW_LINES);
		$seedsCount = count($seeds);

		if (0 === count($seeds)) {
			throw new Exception($this->noSeedsException);
		}

		// Load data from last run
		$data = json_decode(file_get_contents($this->dataJsonFile), true);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, $this->userDefinedTimeout);

		$htmlDom = new DOMDocument;
		libxml_use_internal_errors(true);

		foreach ($seeds as $seed) {
			$seed = trim($seed);

			if ('#' === $seed[0]) continue; // this seed is commented out. skip it.

			$this->debug("Fetching: $seed");
			curl_setopt($ch, CURLOPT_URL, $seed);
			curl_setopt($ch, CURLOPT_REFERER, $this->curlReferer);
			$httpResponse = curl_exec($ch);

			if (!$httpResponse) {
				$this->debug(curl_error($ch));
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
						$this->debug("...", $this->statusChanged);
						// web page changed. find the diff
						if (!$this->userDefinedStatusOnly) {
							$data[$seed]['contents'] = base64_decode($data[$seed]['contents']);

							$filename = '/tmp/' . str_replace(array(':', '/'), '_', $seed);
							file_put_contents($filename . $this->fileASuffix, $data[$seed]['contents']);
							file_put_contents($filename . $this->fileBSuffix, $body);

							$this->showDiff($filename . $this->fileASuffix, $filename . $this->fileBSuffix);
						}

						// update the status in data file
						$data[$seed]['status'] = $this->statusChanged;
						$data[$seed]['checksum'] = $newChecksum;
						$data[$seed]['contents'] = base64_encode($body);
					} else {
						// no change. just update status
						$this->debug("...", $this->statusNoChange);
						$data[$seed]['status'] = $this->statusNoChange;
					}

					$data[$seed]['lastChecked'] = microtime();
				} else {
					// this is first processing of this seed
					$this->debug("...", $this->statusNew);
					$data[$seed] = array(
						'status' => $this->statusNew,
						'checksum' => $newChecksum,
						'contents' => base64_encode($body),
						'lastChecked' => microtime()
					);
				} // if-else on isset data[seed]
			} // foreach on bodyTags
		} // foreach on seeds

		// save updated data
		file_put_contents($this->dataJsonFile, json_encode($data));

		libxml_clear_errors();
		curl_close($ch);
		echo "\n*** Done ***\n";	
	} // run

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

	public function debug($message, $level = null) {
		if (empty($level)) {
			 $level = $this->debugLevelInfo;
		}
		$timestamp = date('Y-m-d H:i:s');

		if (is_string($message)) {
			file_put_contents($this->debugLogFile, "[$timestamp] $level: $message\n", FILE_APPEND);
			if ($this->userDefinedVerbose) {
				echo "[$timestamp] $level: $message\n";
			}
		} else {
			file_put_contents($this->debugLogFile, "[$timestamp] $level: ", FILE_APPEND);
			file_put_contents($this->debugLogFile, $message, FILE_APPEND);
			file_put_contents($this->debugLogFile, "\n", FILE_APPEND);
			if ($this->userDefinedVerbose) {
				echo "[$timestamp] $level: ";
				echo var_export($message, true), "\n";
			}
		}
	} // debug

	private function preCheck() {
		if (!function_exists('curl_init')) {
			throw new Exception($this->noCurlExecption);
		}
	} // precheck

	public static function help() {
		$myName = __FILE__;

		echo "Syntax: $myName <options>\n";
		echo "Options: [-v] [-i] [-s] [-t]\n";
		echo "-v, --verbose\tVerbose\n";
		echo "-i, --inputfile\tInput file containing list of web pages to check. One URL per line. Defaults to ./seeds\n";
		echo "-s, --statusonly\tReport only status, do not show diff\n";
		echo "-t, --timeout\tTimeout period in seconds\n";
		echo "-h, --help\tShows this help text\n";
		exit(1);
	}
}

$longopts = array(
	"verbose",
	"inputfile::",
	"statusonly",
	"timeout::",
	"help"
	);
$options = getopt("vi:st::h", $longopts);

if (!is_array($options)) {
	echo "There was some error reading options.\n";
	Webmon::help();
}

// default values
$userDefinedOptions = array();
$userDefinedOptions['verbose'] = false;
$userDefinedOptions['inputFile'] = './seeds';
$userDefinedOptions['statusOnly'] = false;
$userDefinedOptions['timeout'] = 30;

foreach ($options as $option => $value) {
	switch($option) {
		case 'v':
		case 'verbose':
			$userDefinedOptions['verbose'] = true;
			break;
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