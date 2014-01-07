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

/*
 * Constants required for webmon/index.php script
 */
define('FILE_DATA_JSON', './data.json');
define('FILE_DEBUG_LOG', './webmon.log');
define('FILE_A_SUFFIX', '_a.txt');
define('FILE_B_SUFFIX', '_b.txt');
define('STATUS_NEW', 'New');
define('STATUS_NO_CHANGE', 'No Change');
define('STATUS_CHANGED', 'Changed');
define('DEBUG_LEVEL_INFO', 'INFO');
define('DEBUG_LEVEL_WARNING', 'WARNING');
define('DEBUG_LEVEL_ERROR', 'ERROR');
?>
