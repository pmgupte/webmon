<?php
if (!function_exists('curl_init')) {
	die('Need PHP cURL installed and enabled.');
}

$seeds = file('./seeds', FILE_SKIP_EMPTY_LINES);
$seedsCount = count($seeds);
echo "\n$seedsCount URLs found in seeds file.";

if (0 === count($seeds)) {
	die('Exiting.');
}

//TODO: Load MD5 checksums calculated during last run.

$ch = curl_init();
$htmlDom = new DOMDocument;
libxml_use_internal_errors(true);

foreach ($seeds as $seed) {
	echo "\n* Fetching: $seed";
	curl_setopt($ch, CURLOPT_URL, $seed);
	curl_setopt($ch, CURLOPT_REFERER, 'Webmon Script');
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	$httpResponse = curl_exec($ch);

	$htmlDom->loadHTML($httpResponse);
	$body = $htmlDom->getElementsByTagName('body');
	var_dump($body);

	foreach ($body as $b) {
		echo $b->nodeValue, PHP_EOL;
		
		//TODO: calculate MD5 of the contents, and compare it with last MD5 (if exists)
		//TODO: if checksums differ, calculate the difference and output it.
		//TODO: update MD5 checksum
	}
}

libxml_clear_errors();
curl_close($ch);
?>
