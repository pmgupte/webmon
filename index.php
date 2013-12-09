<?php
/*
 * Primary Checks
 */
if (!function_exists('curl_init')) {
	die('Need PHP cURL installed and enabled.');
}

/*
 * Constants required for this script
 */
define('FILE_SEEDS', './seeds');
define('FILE_DATA_JSON', './data.json');
define('FILE_DEBUG_LOG', './webmon.log');
define('STATUS_NEW', 'New');
define('STATUS_NO_CHANGE', 'No Change');
define('STATUS_CHANGED', 'Changed');
define('DEBUG_LEVEL_INFO', 'INFO');
define('DEBUG_LEVEL_WARNING', 'WARNING');
define('DEBUG_LEVEL_ERROR', 'ERROR');

//TODO: set this flag according to -v flag on command line.
define('VERBOSE', true);
/*
 * Touch required files
 */
touch(FILE_SEEDS);
touch(FILE_DATA_JSON);

$seeds = file(FILE_SEEDS, FILE_SKIP_EMPTY_LINES && FILE_IGNORE_NEW_LINES);
$seedsCount = count($seeds);
debug("$seedsCount URLs found in seeds file.");

if (0 === count($seeds)) {
	die('Exiting.');
}

// Load data from last run
$data = json_decode(file_get_contents(FILE_DATA_JSON), true);

$ch = curl_init();
$htmlDom = new DOMDocument;
libxml_use_internal_errors(true);

foreach ($seeds as $seed) {
	debug("* Fetching: $seed");
	curl_setopt($ch, CURLOPT_URL, $seed);
	curl_setopt($ch, CURLOPT_REFERER, 'Webmon Script');
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	$httpResponse = curl_exec($ch);

	$htmlDom->loadHTML($httpResponse);
	$bodyTags = $htmlDom->getElementsByTagName('body');

	foreach ($bodyTags as $bodyTag) {
		$body = $bodyTag->nodeValue;
		$newChecksum = md5($body);

		if (isset($data[$seed])) {
			// we have processed this seed at least once before
			if ($newChecksum !== $data[$seed]['checksum']) {
				debug("...", STATUS_CHANGED);
				// web page changed. update stored data
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
		file_put_contents(FILE_DEBUG_LOG, "[$timestamp] $level: $message", FILE_APPEND);
		if (VERBOSE) {
			echo "\n", "[$timestamp] $level: $message";
		}
	} else {
		file_put_contents(FILE_DEBUG_LOG, "[$timestamp] $level: ", FILE_APPEND);
		file_put_contents(FILE_DEBUG_LOG, $message, FILE_APPEND);
		if (VERBOSE) {
			echo "\n", "[$timestamp] $level: ";
			echo var_export($message, true);
		}
	}
} // debug
?>
