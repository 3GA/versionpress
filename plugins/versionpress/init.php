<?php

require_once(VERSIONPRESS_PLUGIN_DIR . '/src/storages/EntityStorage.php');
require_once(VERSIONPRESS_PLUGIN_DIR . '/src/storages/ObservableStorage.php');
require_once(VERSIONPRESS_PLUGIN_DIR . '/src/storages/DirectoryStorage.php');
require_once(VERSIONPRESS_PLUGIN_DIR . '/src/storages/EntityStorageFactory.php');
require_once(VERSIONPRESS_PLUGIN_DIR . '/src/storages/CommentStorage.php');
require_once(VERSIONPRESS_PLUGIN_DIR . '/src/storages/PostStorage.php');
require_once(VERSIONPRESS_PLUGIN_DIR . '/src/storages/SingleFileStorage.php');
require_once(VERSIONPRESS_PLUGIN_DIR . '/src/storages/OptionsStorage.php');
require_once(VERSIONPRESS_PLUGIN_DIR . '/src/storages/TermsStorage.php');
require_once(VERSIONPRESS_PLUGIN_DIR . '/src/storages/TermTaxonomyStorage.php');
require_once(VERSIONPRESS_PLUGIN_DIR . '/src/storages/UserStorage.php');
require_once(VERSIONPRESS_PLUGIN_DIR . '/src/storages/UserMetaStorage.php');
require_once(VERSIONPRESS_PLUGIN_DIR . '/src/database/DbSchemaInfo.php');
require_once(VERSIONPRESS_PLUGIN_DIR . '/src/database/ExtendedWpdb.php');
require_once(VERSIONPRESS_PLUGIN_DIR . '/src/database/MirroringDatabase.php');
require_once(VERSIONPRESS_PLUGIN_DIR . '/src/utils/IniSerializer.php');
require_once(VERSIONPRESS_PLUGIN_DIR . '/src/utils/Git.php');
require_once(VERSIONPRESS_PLUGIN_DIR . '/src/utils/Neon.php');
require_once(VERSIONPRESS_PLUGIN_DIR . '/src/utils/Arrays.php');
require_once(VERSIONPRESS_PLUGIN_DIR . '/src/utils/Strings.php');
require_once(VERSIONPRESS_PLUGIN_DIR . '/src/utils/Uuid.php');
require_once(VERSIONPRESS_PLUGIN_DIR . '/src/Mirror.php');
require_once(VERSIONPRESS_PLUGIN_DIR . '/src/ChangeInfo.php');

global $wpdb, $table_prefix, $storageFactory, $schemaInfo;
$storageFactory = new EntityStorageFactory(VERSIONPRESS_MIRRORING_DIR);
$mirror = new Mirror($storageFactory);
$schemaFile = VERSIONPRESS_PLUGIN_DIR . '/src/database/schema.neon';
$schemaInfo = new DbSchemaInfo($schemaFile, $table_prefix);
$wpdb = new MirroringDatabase(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST, $mirror, $schemaInfo);

// Hook for saving taxonomies into files
// WordPress creates plain INSERT query and executes it using wpdb::query method instead of wpdb::insert.
// It's too difficult to parse every INSERT query, that's why the WordPress hook is used.
add_action('save_post', createUpdatePostTermsHook($storageFactory->getStorage('posts'), $wpdb));

function createUpdatePostTermsHook(EntityStorage $storage, wpdb $wpdb) {

    return function ($postId) use ($storage, $wpdb) {
        global $table_prefix;

        $post = get_post($postId);
        $postType = $post->post_type;
        $taxonomies = get_object_taxonomies($postType);

        $vpIdTableName = $table_prefix . 'vp_id';

        $postVpId = $wpdb->get_var("SELECT HEX(vp_id) FROM $vpIdTableName WHERE id = $postId AND `table` = 'posts'");

        $postUpdateData = array('vp_id' => $postVpId);

        foreach ($taxonomies as $taxonomy) {
            $terms = get_the_terms($postId, $taxonomy);
            if ($terms)
                $postUpdateData[$taxonomy] = array_map(function ($term) use ($wpdb, $vpIdTableName) {
                    return $wpdb->get_var("SELECT HEX(vp_id) FROM $vpIdTableName WHERE id = {$term->term_id} AND `table` = 'terms'");
                }, $terms);
        }

        if (count($taxonomies) > 0)
            $storage->save($postUpdateData);
    };
}

$buildCommitMessage = function (ChangeInfo $changeInfo) {
    // Samples:
    // Created post with ID 1
    // Edited post with ID 2
    // Deleted post with ID 3
    static $verbs = array(
        'create' => 'Created',
        'edit' => 'Edited',
        'delete' => 'Deleted'
    );

    return sprintf("%s %s with ID %s.", $verbs[$changeInfo->type], $changeInfo->entityType, $changeInfo->entityId);
};

// Checks if some entity has been changed. If so, it tries to commit.
register_shutdown_function(function () use ($mirror, $buildCommitMessage) {
    if ($mirror->wasAffected()) {
        $changeList = $mirror->getChangeList();

        $commitMessage = join(" ", array_map($buildCommitMessage, $changeList));

        Git::commit($commitMessage);
    }
});