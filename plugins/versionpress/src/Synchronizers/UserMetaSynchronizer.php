<?php

namespace VersionPress\Synchronizers;

use VersionPress\Database\DbSchemaInfo;
use VersionPress\Storages\Storage;
use wpdb;

class UserMetaSynchronizer extends SynchronizerBase {

    /** @var wpdb */
    private $database;

    /** @var DbSchemaInfo */
    private $dbSchema;

    function __construct(Storage $storage, wpdb $database, DbSchemaInfo $dbSchema) {
        parent::__construct($storage, $database, $dbSchema, 'usermeta');
        $this->database = $database;
        $this->dbSchema = $dbSchema;
    }

    protected function transformEntities($entities) {
        $transformedEntities = array();
        foreach ($entities as $userId => $entity) {
            foreach ($entity as $meta_key => $meta_value) {
                $dividerPosition = strrpos($meta_key, '#');

                if ($dividerPosition === false)
                    continue;

                $key = substr($meta_key, 0, $dividerPosition);
                $id = substr($meta_key, $dividerPosition + 1);


                $transformedEntity = array();
                $transformedEntity['vp_id'] = $id;
                $transformedEntity['vp_user_id'] = $userId;
                $transformedEntity['meta_key'] = $key;
                $transformedEntity['meta_value'] = $meta_value;
                $transformedEntities[] = $transformedEntity;
            }
        }

        return parent::transformEntities($transformedEntities);
    }
}
