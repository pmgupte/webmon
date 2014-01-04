#!/usr/bin/php
<?php
/*
 * Primary Checks
 */
if (!function_exists('curl_init')) {
	die('Need PHP cURL installed and enabled.');
}

require('constants.php');

/*
 * Touch required files
 */
touch(FILE_SEEDS);
touch(FILE_DATA_JSON);
touch(FILE_DEBUG_LOG);

$seeds = file(FILE_SEEDS, FILE_SKIP_EMPTY_LINES && FILE_IGNORE_NEW_LINES);
$seedsCount = count($seeds);
debug("$seedsCount URLs found in seeds file.");

if (0 === count($seeds)) {
	die('Exiting.');
}

// Load data from last run
$data = json_decode(file_get_contents(FILE_DATA_JSON), true);

$ch = curl_init();
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$htmlDom = new DOMDocument;
libxml_use_internal_errors(true);

foreach ($seeds as $seed) {
	$seed = trim($seed);

	if ('#' === $seed[0]) continue; // this seed is commented out. skip it.

	debug("* Fetching: $seed");
	curl_setopt($ch, CURLOPT_URL, $seed);
	curl_setopt($ch, CURLOPT_REFERER, 'Webmon Script');
	$httpResponse = curl_exec($ch);

	if (!$httpResponse) {
		debug(curl_error($ch));
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
				debug("...", STATUS_CHANGED);
				// web page changed. find the diff
				$data[$seed]['contents'] = base64_decode($data[$seed]['contents']);

				$filename = '/tmp/' . str_replace(array(':', '/'), '_', $seed);
				file_put_contents($filename . FILE_A_SUFFIX, $data[$seed]['contents']);
				file_put_contents($filename . FILE_B_SUFFIX, $body);

				$contentsA = file($filename . FILE_A_SUFFIX);
				$contentsB = file($filename . FILE_B_SUFFIX);

				$negativeDiff = array_diff($contentsA, $contentsB);
				$positiveDiff = array_diff($contentsB, $contentsA);

				$countA = count($contentsA);
				$countB = count($contentsB);
				$counter = ($countA > $countB) ? $countA : $countB;

				echo "+++ positive diff\n--- negative diff\n";
				for ($i=0; $i<$counter; $i++) {
					if (!isset($contentsA[$i])) { $contentsA[$i] = ''; }
					if (!isset($contentsB[$i])) { $contentsB[$i] = ''; }

					if ($contentsA[$i] === $contentsB[$i]) {
						echo '  ' . $contentsA[$i];
					} else if (in_array($contentsA[$i], $contentsB)) {
						if (!in_array($contentsB[$i], $contentsA)) {
							echo '+ ' . $contentsB[$i];
						} else {
							echo '  ' . $contentsA[$i];
						}
					} else if (in_array($contentsA[$i], $negativeDiff)) {
						echo '- ' . $contentsA[$i];
					}
				}

				// update the status in data file
				$data[$seed]['status'] = STATUS_CHANGED;
				$data[$seed]['checksum'] = $newChecksum;
				$data[$seed]['contents'] = base64_encode($body);

			} else {
				// no change. just update status
				debug("...", STATUS_NO_CHANGE);
				$data[$seed]['status'] = STATUS_NO_CHANGE;
			}

			$data[$seed]['lastChecked'] = microtime();
		} else {
			// this is first processing of this seed
			debug("...", STATUS_NEW);
			$data[$seed] = array(
				'status' => STATUS_NEW,
				'checksum' => $newChecksum,
				'contents' => base64_encode($body),
				'lastChecked' => microtime()
			);
		} // if-else on isset data[seed]
	} // foreach on bodyTags
} // foreach on seeds

// save updated data
file_put_contents(FILE_DATA_JSON, json_encode($data));

libxml_clear_errors();
curl_close($ch);
echo "\n*** Done ***\n";

/******************************************************************************
 * Helper Functions
 *****************************************************************************/
function debug($message, $level=DEBUG_LEVEL_INFO) {
	$timestamp = date('Y-m-d H:i:s');

	if (is_string($message)) {
		file_put_contents(FILE_DEBUG_LOG, "[$timestamp] $level: $message\n", FILE_APPEND);
		if (VERBOSE) {
			echo "[$timestamp] $level: $message\n";
		}
	} else {
		file_put_contents(FILE_DEBUG_LOG, "[$timestamp] $level: ", FILE_APPEND);
		file_put_contents(FILE_DEBUG_LOG, $message, FILE_APPEND);
		file_put_contents(FILE_DEBUG_lOG, "\n", FILE_APPEND);
		if (VERBOSE) {
			echo "[$timestamp] $level: ";
			echo var_export($message, true), "\n";
		}
	}
} // debug
?>
