<?php
define('VERSIONPRESS_PLUGIN_DIR', dirname(__FILE__) . '/plugins/versionpress');
define('VERSIONPRESS_MIRRORING_DIR', VERSIONPRESS_PLUGIN_DIR . '/db');

if(file_exists(VERSIONPRESS_PLUGIN_DIR . '/.active'))
    require_once(VERSIONPRESS_PLUGIN_DIR . '/init.php');
