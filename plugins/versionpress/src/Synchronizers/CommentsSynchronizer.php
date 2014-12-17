<?php
namespace VersionPress\Synchronizers;

use VersionPress\Database\DbSchemaInfo;
use VersionPress\Storages\Storage;
use wpdb;

/**
 * Comments synchronizer, the simplest VPID one (simply uses base class implementation)
 */
class CommentsSynchronizer extends SynchronizerBase {
    function __construct(Storage $storage, wpdb $database, DbSchemaInfo $dbSchema) {
        parent::__construct($storage, $database, $dbSchema, 'comment');
    }
}
