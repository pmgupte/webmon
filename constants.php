<?php
/*
 * Constants required for webmon/index.php script
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

?>