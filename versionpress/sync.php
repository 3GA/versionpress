<?php
// Enable WP_DEBUG mode
define('WP_DEBUG', true);
// Enable Debug logging to the /wp-content/debug.log file
define('WP_DEBUG_LOG', true);

require_once(dirname(__FILE__) . '/../../wp-load.php');
//require_once(dirname(__FILE__) . '/synchronizers/PostsAndCommentsBaseSynchronizer.php');
require_once(dirname(__FILE__) . '/synchronizers/Synchronizer.php');
require_once(dirname(__FILE__) . '/synchronizers/CommentSynchronizer.php');
//require_once(dirname(__FILE__) . '/synchronizers/OptionsSynchronizer.php');
require_once(dirname(__FILE__) . '/utils/Git.php');
require_once(dirname(__FILE__) . '/utils/Strings.php');


global $wpdb, $table_prefix, $storageFactory, $schemaInfo;

$postStorage = $storageFactory->getStorage('comments');
$wpdb->show_errors();

$postSynchronizer = new CommentSynchronizer($postStorage, $wpdb, $schemaInfo);
$postSynchronizer->synchronize();

//Git::commit('Test commit', dirname(__FILE__) . '/db');