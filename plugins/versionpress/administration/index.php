<?php
$isInitialized = is_file(VERSIONPRESS_PLUGIN_DIR . '/.active');

function initialize() {
    require_once(VERSIONPRESS_PLUGIN_DIR . '/VersionPressInstaller.php');

    global $wpdb, $table_prefix;

    @mkdir(VERSIONPRESS_MIRRORING_DIR, 0777, true);
    $dbSchema = new DbSchemaInfo(VERSIONPRESS_PLUGIN_DIR . '/src/database/schema.neon', $table_prefix);
    $storageFactory = new EntityStorageFactory(VERSIONPRESS_MIRRORING_DIR);
    $installer = new VersionPressInstaller($wpdb, $dbSchema, $storageFactory, $table_prefix);
    $installer->onProgressChanged[] = 'show_message';
    $installer->install();

    touch(VERSIONPRESS_PLUGIN_DIR . '/.active');
}


if(isset($_GET['init']) && !$isInitialized) {
    initialize();
?>
    <script type="text/javascript">
        window.location = '<?php echo admin_url('admin.php?page=versionpress/administration/index.php'); ?>';
    </script>
<?php
} elseif(!$isInitialized) {
?>
    <form method="POST" action="<?php echo admin_url('admin.php?page=versionpress/administration/index.php&init'); ?>">
        <input type="submit" value="Initialize">
    </form>
<?php
} else {
    if (isset($_GET['revert'])) {
        Git::revert($_GET['revert']);
        require_once __DIR__ . '/../../versionpress/sync.php';
    }
?>
    <h1>VersionPress</h1>
    <table class="wp-list-table widefat fixed posts">
        <tr>
            <th class="manage-column column-date">Date</th>
            <th class="manage-column column-date">ID</th>
            <th class="manage-column">Message</th>
            <th class="manage-column column-categories"></th>
        </tr>
        <tbody id="the-list">
        <?php
        $commits = Git::log();
        foreach($commits as $commit) {
            echo "
        <tr class=\"post-1 type-post status-publish format-standard hentry category-uncategorized alternate level-0\">
            <td>$commit[date]</td>
            <td>$commit[id]</td>
            <td>$commit[message]</td>
            <td style=\"text-align: right\">
                <a href='" . admin_url('admin.php?page=versionpress/administration/index.php&revert=' . $commit['id']) . "' style='text-decoration:none;'>
                Revert&nbsp;only&nbsp;this
                </a>
                |
                <a href='" . admin_url('admin.php?page=versionpress/administration/index.php&revert-all=' . $commit['id']) . "' style='text-decoration:none;'>
                Revert&nbsp;all
                </a>
            </td>
        </tr>";
        }
        ?>
        </tbody>
    </table>
<?php
}
?>
