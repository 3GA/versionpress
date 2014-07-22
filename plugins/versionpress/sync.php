<?php
// Enable WP_DEBUG mode
define('WP_DEBUG', true);
// Enable Debug logging to the /wp-content/debug.log file
define('WP_DEBUG_LOG', true);

require_once(dirname(__FILE__) . '/../../../wp-load.php');

global $wpdb, $table_prefix, $storageFactory, $schemaInfo;
$wpdb->show_errors();

function fixCommentCount(wpdb $wpdb) {
    $sql = "update {$wpdb->prefix}posts set comment_count =
     (select count(*) from {$wpdb->prefix}comments where comment_post_ID = {$wpdb->prefix}posts.ID and comment_approved = 1);";
    $wpdb->query($sql);
}

$synchronizationProcess = new SynchronizationProcess(new SynchronizerFactory($storageFactory, $wpdb, $schemaInfo));

$synchronizationQueue = ['options', 'users', 'usermeta', 'posts', 'comments', 'terms', 'term_taxonomy', 'term_relationships'];
$synchronizationProcess->synchronize($synchronizationQueue);
fixCommentCount($wpdb);
Git::commit('Synchronized');